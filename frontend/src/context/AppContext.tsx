import React, { createContext, useContext, useState, useEffect, useRef } from 'react';
import {
  Project,
  Property,
  MediaItem,
  Inquiry,
  CMSConfig,
  ClientTheme,
  ClientThemeId,
  DeviceViewport,
  InquiryStatus,
  Seller,
  Buyer,
  Dealer,
  Transaction,
  PropertyFileItem,
  PlotMarker,
  AuthUser,
  UserRole,
  DocumentVerificationStatus,
  PaymentEntry,
  CommissionEntry,
  AgencyVideo,
  VideoComment
} from '../types';
import {
  initialCMSConfig,
  clientThemes,
  initialProjects,
  initialProperties,
  initialMedia,
  initialTestimonials,
  initialPlotMarkers
} from '../data/publicSeed';
import { initialAgencyVideos } from '../data/agencyVideosSeed';
import { APP_MODE, DEMO_PIN, IS_DEMO, allowsPublicSeedFallback } from '../config/appMode';
import { checkDuplicateProperty, hasPermission, userHasPermission, maskCNIC, calculateMediaSaleProfit } from '../utils/logic';
import { logAudit } from '../domain/auditLog';
import { normalizeRole, authorize, ROLE_LABELS, toLegacyRoleLabel, type Permission } from '../domain/permissions';
import {
  TRANSACTION_STAGES,
  validateTransition,
  validateVoid,
  validateDealPayload,
  validatePayment,
  validateRefund,
  dealMoney as dealMoneyOf,
  impliedStatusByPayment,
  nextDealNumber,
  genId,
  accrueCommission,
} from '../domain/transactions';
import { api, getAuthToken, setAuthToken, setApiAgencyId } from '../services/api';
import { decideMediaDeletion } from '../domain/mediaPolicy';
import { anonymousUser, defaultAgencyId, userFromServer } from '../domain/authSession';

/** Read a collection from localStorage, returning fallback on failure. */
function readStored<T>(key: string, fallback: T[]): T[] {
  if (!IS_DEMO) return allowsPublicSeedFallback(APP_MODE) ? fallback : [];
  const saved = localStorage.getItem(`taisartownhub_v2_${key}`);
  if (saved) {
    try {
      const parsed = JSON.parse(saved);
      return Array.isArray(parsed) ? (parsed as T[]) : fallback;
    } catch (e) {
      return fallback;
    }
  }
  return fallback;
}

export interface ToastMessage {
  id: string;
  type: 'success' | 'error' | 'info';
  message: string;
}

interface AppContextType {
  // Navigation & Page State
  currentView: string;
  setCurrentView: (view: string) => void;
  selectedProjectId: string | null;
  setSelectedProjectId: (id: string | null) => void;
  selectedPropertyId: string | null;
  setSelectedPropertyId: (id: string | null) => void;
  adminTab: string;
  setAdminTab: (tab: string) => void;
  
  // Auth & RBAC
  isAdminAuthenticated: boolean;
  isSessionLoading: boolean;
  refreshSession: () => Promise<void>;
  currentUser: AuthUser;
  authUsers: AuthUser[];
  activeRole: UserRole;
  switchAuthUser: (role: UserRole, pin?: string) => Promise<boolean>;
  loginAsDemo: (role: UserRole, pin?: string) => Promise<boolean>;
  verifyPresenterPin: (pin: string) => boolean;
  loginAdmin: (password: string) => Promise<boolean>;
  loginWithCredentials: (email: string, password: string) => Promise<{ success: boolean; error?: string }>;
  registerDealerAccount: (data: {
    fullName: string;
    email: string;
    password?: string;
    phone: string;
    officeAddress?: string;
    agencyOrBranch: string;
    sectorFocus?: string;
  }) => Promise<{ success: boolean; error?: string }>;
  logoutAdmin: () => void;
  
  // Theme & Presentation Mode
  currentTheme: ClientTheme;
  setClientThemeId: (themeId: ClientThemeId) => void;
  isPresentationMode: boolean;
  togglePresentationMode: () => void;
  deviceViewport: DeviceViewport;
  setDeviceViewport: (viewport: DeviceViewport) => void;
  
  // Core Real Estate Collections
  projects: Project[];
  addProject: (project: Omit<Project, 'id' | 'createdDate'>) => void;
  updateProject: (id: string, updates: Partial<Project>) => void;
  deleteProject: (id: string) => void;
  restoreProject: (id: string) => void;
  
  properties: Property[];
  addProperty: (property: Omit<Property, 'id' | 'createdDate'>) => { success: boolean; error?: string };
  updateProperty: (id: string, updates: Partial<Property>) => { success: boolean; error?: string };
  deleteProperty: (id: string) => void;
  restoreProperty: (id: string) => void;
  
  // Real Estate ERP Collections
  sellers: Seller[];
  addSeller: (seller: Omit<Seller, 'id' | 'createdAt'>) => void;
  updateSeller: (id: string, updates: Partial<Seller>) => void;
  deleteSeller: (id: string) => void;
  restoreSeller: (id: string) => void;

  buyers: Buyer[];
  addBuyer: (buyer: Omit<Buyer, 'id' | 'createdAt'>) => void;
  updateBuyer: (id: string, updates: Partial<Buyer>) => void;
  deleteBuyer: (id: string) => void;
  restoreBuyer: (id: string) => void;

  dealers: Dealer[];
  addDealer: (dealer: Omit<Dealer, 'id' | 'createdAt' | 'totalEarnedPKR' | 'pendingCommissionPKR'>) => void;
  updateDealer: (id: string, updates: Partial<Dealer>) => void;
  deleteDealer: (id: string) => void;
  restoreDealer: (id: string) => void;
  payDealerCommission: (dealerId: string, amount: number, transactionId?: string) => void;

  transactions: Transaction[];
  /** Payment ledger (P1-2): one row per money movement. */
  paymentEntries: PaymentEntry[];
  /** Commission ledger (P1-3): accrual + payout history per deal. */
  commissionEntries: CommissionEntry[];
  addTransaction: (transaction: Omit<Transaction, 'id'>) => void;
  updateTransaction: (id: string, updates: Partial<Transaction>) => void;
  deleteTransaction: (id: string) => void;
  /** Record a payment on a deal (manage_payments gate); updates the ledger,
   *  derived payment status and the accrual milestone. */
  recordPayment: (transactionId: string, amount: number, method: PaymentEntry['method'], note?: string) => boolean;
  /** Record a refund against previously collected funds; cancellations may be refunded, voided deals may not. */
  recordRefund: (transactionId: string, amount: number, method: PaymentEntry['method'], note: string) => boolean;
  /** Void a deal with a mandatory reason (void_transactions gate); preserves
   *  the record with an audit trail instead of hard-deleting it. */
  voidTransaction: (id: string, reason: string) => void;

  propertyFiles: PropertyFileItem[];
  addPropertyFile: (file: Omit<PropertyFileItem, 'id' | 'uploadedAt'>) => void;
  verifyPropertyFile: (id: string, status: DocumentVerificationStatus, notes?: string) => void;
  deletePropertyFile: (id: string) => void;
  restorePropertyFile: (id: string) => void;

  plotMarkers: PlotMarker[];
  updatePlotMarker: (id: string, updates: Partial<PlotMarker>) => void;

  // Media Library
  mediaItems: MediaItem[];
  addMediaItem: (item: Omit<MediaItem, 'id' | 'uploadedAt' | 'usageCount'>) => void;
  deleteMediaItem: (id: string) => { success: boolean; message?: string };
  restoreMediaItem: (id: string) => void;
  markMediaSold: (id: string, sale: { soldPricePKR: number; basePricePKR?: number; buyerName: string; buyerPhone: string; buyerNationalId?: string }) => { success: boolean; message?: string; profit?: number };

  // Agency Video Showcase & Reels (Media Gallery)
  agencyVideos: AgencyVideo[];
  likeAgencyVideo: (videoId: string) => void;
  addAgencyVideoComment: (videoId: string, userName: string, comment: string) => void;
  addAgencyVideo: (video: Omit<AgencyVideo, 'id' | 'likes' | 'comments' | 'views'>) => Promise<{ ok: boolean }>;
  
  // Inquiries & CMS
  inquiries: Inquiry[];
  addInquiry: (
    inquiry: Omit<Inquiry, 'id' | 'submittedAt' | 'status'> & {
      /** Bot protection (G-11): solved challenge fields forwarded verbatim. */
      captchaToken?: string;
      captchaAnswer?: string;
      companyWebsite?: string;
    }
  ) => Promise<boolean>;
  updateInquiryStatus: (id: string, status: InquiryStatus) => void;
  updateInquiryNotes: (id: string, notes: string) => void;
  deleteInquiry: (id: string) => void;
  
  cmsConfig: CMSConfig;
  updateCMSConfig: (updates: Partial<CMSConfig>) => void;
  
  // Toasts & Reset
  toasts: ToastMessage[];
  showToast: (type: 'success' | 'error' | 'info', message: string) => void;
  removeToast: (id: string) => void;
  resetToDefaultData: () => Promise<void>;
  backendConnected: boolean;

  // Accessible confirmation dialog (replaces window.confirm)
  pendingConfirm: {
    message: string;
    title?: string;
    confirmLabel?: string;
  } | null;
  confirmAction: (
    message: string,
    options?: { title?: string; confirmLabel?: string }
  ) => Promise<boolean>;
  resolveConfirm: (accepted: boolean) => void;
}

const STORAGE_KEY_PREFIX = 'taisartownhub_v2_';

// Legacy keys from the pre-v2.0.1 build that used to control auth/demo state.
// They are no longer read anywhere; scrub them once per boot so tampered or
// stale flags cannot linger in the visitor's storage.
const LEGACY_KEYS_TO_SCRUB = [
  'taisartownhub_v2_presentation_mode',
  'taisartownhub_v2_demo_user',
  'taisartownhub_v2_is_admin_authenticated',
];
try {
  for (const k of LEGACY_KEYS_TO_SCRUB) localStorage.removeItem(k);
} catch { /* storage unavailable */ }

const AppContext = createContext<AppContextType | undefined>(undefined);

// Helpers for URL route parsing
const parseAdminTabFromPath = (path: string): string => {
  if (path.startsWith('/admin/')) {
    const sub = path.replace('/admin/', '').split('/')[0].split('?')[0];
    if (sub === 'verification') return 'file_management';
    if (sub === 'locator') return 'plot_locator';
    return sub || 'overview';
  }
  return 'overview';
};

const VIEW_MAP: Record<string, string> = {
  '/': 'home',
  '/home': 'home',
  '/projects': 'projects',
  '/properties': 'properties',
  '/agencies': 'agencies',
  '/guide': 'guide',
  '/scheme45-guide': 'guide',
  '/gallery': 'gallery',
  '/media-gallery': 'gallery',
  '/maps': 'maps',
  '/taiser-maps': 'maps',
  '/services': 'services',
  '/about': 'about',
  '/contact': 'contact',
  '/login': 'login',
  '/auth': 'login',
  '/signin': 'login',
  '/register': 'register',
  '/admin': 'admin',
  '/admin-login': 'admin-login',
};

export const AppProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  // Navigation State
  const [currentView, setCurrentViewRaw] = useState<string>(() => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('property')) return 'property-detail';
    if (params.get('project')) return 'project-detail';
    const path = window.location.pathname;
    if (path === '/admin-login') return 'admin-login';
    if (path.startsWith('/admin')) return 'admin';
    if (path === '/projects' || path === '/project-detail') return 'properties';
    if (path.startsWith('/agencies/') && path !== '/agencies/') return 'agency-profile';
    return VIEW_MAP[path] || 'home';
  });
  const [selectedProjectId, setSelectedProjectIdState] = useState<string | null>(() => {
    const params = new URLSearchParams(window.location.search);
    return params.get('project') || null;
  });
  const [selectedPropertyId, setSelectedPropertyIdState] = useState<string | null>(() => {
    const params = new URLSearchParams(window.location.search);
    return params.get('property') || null;
  });
  const [adminTab, setAdminTabRaw] = useState<string>(() => {
    return parseAdminTabFromPath(window.location.pathname);
  });
  const adminTabRef = useRef(adminTab);

  const setAdminTab = React.useCallback((tab: string) => {
    adminTabRef.current = tab;
    setAdminTabRaw(tab);
    if (window.location.pathname.startsWith('/admin')) {
      const targetPath = tab === 'overview' ? '/admin' : `/admin/${tab}`;
      if (window.location.pathname !== targetPath) {
        window.history.pushState({ view: 'admin', tab }, '', targetPath);
      }
    }
  }, []);

  // Synchronous mirrors so URL updates can read the id being set in the same
  // event handler (state may not be committed yet when setCurrentView runs).
  const selectedProjectIdRef = useRef(selectedProjectId);
  const selectedPropertyIdRef = useRef(selectedPropertyId);
  const setSelectedProjectId = React.useCallback((id: string | null) => {
    selectedProjectIdRef.current = id;
    setSelectedProjectIdState(id);
  }, []);
  const setSelectedPropertyId = React.useCallback((id: string | null) => {
    selectedPropertyIdRef.current = id;
    setSelectedPropertyIdState(id);
  }, []);

  // Canonical view -> URL path map (single source of truth for routing).
  const VIEW_PATHS: Record<string, string> = {
    'home': '/',
    'projects': '/projects',
    'project-detail': '/projects',
    'properties': '/properties',
    'property-detail': '/properties',
    'agencies': '/agencies',
    'agency-profile': '/agencies',
    'guide': '/guide',
    'gallery': '/gallery',
    'maps': '/maps',
    'services': '/services',
    'about': '/about',
    'contact': '/contact',
    'login': '/login',
    'register': '/register',
    'admin': '/admin',
    'admin-login': '/admin-login',
  };
  const viewFromPath = React.useCallback((path: string): string => {
    if (path === '/admin-login') return 'admin-login';
    if (path.startsWith('/admin')) return 'admin';
    if (path.startsWith('/agencies/') && path !== '/agencies/') return 'agency-profile';
    return VIEW_MAP[path] || 'home';
  }, []);

  const setCurrentView = React.useCallback((view: string) => {
    setCurrentViewRaw(view);
    // Update URL without page reload. Detail views deep-link the selected
    // record via a query param so pages are shareable and refresh-safe.
    let path = VIEW_PATHS[view] || '/';
    if (view === 'property-detail' && selectedPropertyIdRef.current) {
      path = `/properties?property=${encodeURIComponent(selectedPropertyIdRef.current)}`;
    } else if (view === 'project-detail' && selectedProjectIdRef.current) {
      path = `/projects?project=${encodeURIComponent(selectedProjectIdRef.current)}`;
    } else if (view === 'agency-profile') {
      const currentPath = window.location.pathname.replace(/\/+$/, '') || '/';
      path = currentPath.startsWith('/agencies/') && currentPath !== '/agencies' ? currentPath : '/agencies';
    } else if (view === 'admin') {
      const tab = adminTabRef.current;
      path = tab && tab !== 'overview' ? `/admin/${tab}` : '/admin';
    }
    if (window.location.pathname + window.location.search !== path) {
      window.history.pushState({ view }, '', path);
    }
  }, []);

  // Handle browser back/forward navigation: restore view AND selected record
  // from the URL so refresh/deep-link/back all resolve to the same screen.
  React.useEffect(() => {
    const handlePopState = (event: PopStateEvent) => {
      const params = new URLSearchParams(window.location.search);
      setSelectedProjectIdState(params.get('project') || null);
      setSelectedPropertyIdState(params.get('property') || null);
      selectedProjectIdRef.current = params.get('project') || null;
      selectedPropertyIdRef.current = params.get('property') || null;
      const path = window.location.pathname;
      if (path.startsWith('/admin')) {
        const tab = parseAdminTabFromPath(path);
        adminTabRef.current = tab;
        setAdminTabRaw(tab);
      }
      setCurrentViewRaw(viewFromPath(path));
    };
    window.addEventListener('popstate', handlePopState);
    return () => window.removeEventListener('popstate', handlePopState);
  }, [viewFromPath]);

  // Presentation & Theme State
  const [currentThemeId, setCurrentThemeId] = useState<ClientThemeId>(() => {
    const saved = localStorage.getItem(`${STORAGE_KEY_PREFIX}theme_id`);
    return (saved as ClientThemeId) || 'taisartown-navy';
  });
  // Presentation mode NEVER comes from localStorage — it is an in-memory,
  // demo-only surface so visitors cannot flip it on themselves.
  const [isPresentationMode, setIsPresentationMode] = useState<boolean>(false);
  const [deviceViewport, setDeviceViewport] = useState<DeviceViewport>('desktop');

  const [isAdminAuthenticated, setIsAdminAuthenticated] = useState(false);
  const [isSessionLoading, setIsSessionLoading] = useState(() => Boolean(getAuthToken()));
  const [currentUser, setCurrentUser] = useState<AuthUser>(anonymousUser);
  const sessionVersionRef = useRef(0);

  // ---- ERP seed hydration (admin-only, dynamically imported chunk) ----
  // The ERP chunk (sellers/buyers/dealers/transactions/files/inquiries/auth users/
  // demo credentials) is fetched ONLY when an admin session starts. Public builds
  // never trigger it, so its bytes never reach public visitors.
  const erpReadyRef = useRef(false);
  const [erpReady, setErpReady] = useState(false);
  /** Staff edits are written to MySQL only after a real workspace load. */
  const staffPersistRef = useRef(false);
  const ensureErpLoaded = React.useCallback(async (): Promise<boolean> => {
    if (erpReadyRef.current) return true;
    try {
      const erp = await import('../data/erpSeed');
      const byId = new Map(erp.initialProperties.map((p) => [p.id, p]));
      // Upgrade locally-stored public-safe property copies with ERP private fields.
      setProperties((prev) =>
        prev.map((p) => {
          const erpVersion = byId.get(p.id);
          return erpVersion ? { ...erpVersion, ...p } : p;
        })
      );
      // Fill any empty collection with pristine seed data (stored edits survive).
      setSellers((prev) => (prev.length ? prev : erp.initialSellers));
      setBuyers((prev) => (prev.length ? prev : erp.initialBuyers));
      setDealers((prev) => (prev.length ? prev : erp.initialDealers));
      setTransactions((prev) => (prev.length ? prev : erp.initialTransactions));
      setPaymentEntries((prev) => (prev.length ? prev : erp.initialPaymentEntries));
      setCommissionEntries((prev) => (prev.length ? prev : erp.initialCommissionEntries));
      setPropertyFiles((prev) => (prev.length ? prev : erp.initialPropertyFiles));
      setInquiries((prev) => (prev.length ? prev : erp.initialInquiries));
      erpReadyRef.current = true;
      setErpReady(true);
      return true;
    } catch (e) {
      showToast('error', 'Could not load ERP records module.');
      return false;
    }
  }, []);

  // Known staff accounts arrive with the ERP chunk (demo/erp mode only).
  const [authUsers, setAuthUsers] = useState<AuthUser[]>([]);

  // Data Collections with LocalStorage caching
  const [projects, setProjects] = useState<Project[]>(() => readStored('projects', IS_DEMO ? initialProjects : []));
  const [properties, setProperties] = useState<Property[]>(() => readStored('properties', IS_DEMO ? initialProperties : []));

  const [sellers, setSellers] = useState<Seller[]>(() => readStored<Seller>('sellers', []));

  const [buyers, setBuyers] = useState<Buyer[]>(() => readStored<Buyer>('buyers', []));

  const [dealers, setDealers] = useState<Dealer[]>(() => readStored<Dealer>('dealers', []));

  const [transactions, setTransactions] = useState<Transaction[]>(() => readStored<Transaction>('transactions', []));

  // Ledgers (P1-2/P1-3): payment movements + commission accrual/payout history.
  const [paymentEntries, setPaymentEntries] = useState<PaymentEntry[]>(() => readStored<PaymentEntry>('payments', []));
  const [commissionEntries, setCommissionEntries] = useState<CommissionEntry[]>(() => readStored<CommissionEntry>('commissions', []));

  const [propertyFiles, setPropertyFiles] = useState<PropertyFileItem[]>(() => readStored<PropertyFileItem>('files', []));

  const [plotMarkers, setPlotMarkers] = useState<PlotMarker[]>(() => readStored('plots', IS_DEMO ? initialPlotMarkers : []));
  const [mediaItems, setMediaItems] = useState<MediaItem[]>(() => readStored('media', IS_DEMO ? initialMedia : []));
  const [agencyVideos, setAgencyVideos] = useState<AgencyVideo[]>(() => readStored('agency_videos', IS_DEMO ? initialAgencyVideos : []));

  const [inquiries, setInquiries] = useState<Inquiry[]>(() => readStored<Inquiry>('inquiries', []));

  const [cmsConfig, setCmsConfig] = useState<CMSConfig>(() => {
    const saved = IS_DEMO ? localStorage.getItem(`${STORAGE_KEY_PREFIX}cms`) : null;
    if (saved) {
      try { return JSON.parse(saved); } catch (e) { /* ignore */ }
    }
    return initialCMSConfig;
  });

  // Toasts
  const [toasts, setToasts] = useState<ToastMessage[]>([]);
  const [backendConnected, setBackendConnected] = useState<boolean>(false);

  // Theme preference is the only value kept in the browser. Business records live in MySQL.
  useEffect(() => {
    localStorage.setItem(`${STORAGE_KEY_PREFIX}theme_id`, currentThemeId);
  }, [currentThemeId]);

  useEffect(() => {
    [
      'projects', 'properties', 'sellers', 'buyers', 'dealers', 'files',
      'transactions', 'payments', 'commissions', 'media', 'agency_videos',
      'inquiries', 'cms', 'plots',
    ].forEach((key) => localStorage.removeItem(`${STORAGE_KEY_PREFIX}${key}`));
  }, []);

  // Keep dealer commission aggregates in sync with the ledger (P1-3 single source of truth).
  useEffect(() => {
    if (!erpReady) return;
    setDealers((prev) =>
      prev.map((d) => {
        const entries = commissionEntries.filter((c) => c.dealerId === d.id && !String(c.status).startsWith('Cancel'));
        const earned = entries.reduce((s, c) => s + (c.amountPKR || 0), 0);
        const paid = entries.reduce((s, c) => s + (c.paidPKR || 0), 0);
        return { ...d, totalEarnedPKR: earned, totalCommissionPaidPKR: paid, pendingCommissionPKR: Math.max(0, earned - paid) };
      })
    );
  }, [commissionEntries, erpReady]);

  // Toast Helpers
  const showToast = (type: 'success' | 'error' | 'info', message: string) => {
    const id = Date.now().toString() + Math.random().toString(36).substring(2, 5);
    setToasts((prev) => [...prev, { id, type, message }]);
    setTimeout(() => {
      removeToast(id);
    }, 4500);
  };

  const applyServerData = (data: Record<string, unknown>, staff: boolean) => {
    const list = <T,>(key: string): T[] | null => (Array.isArray(data[key]) ? (data[key] as T[]) : null);
    const projectsFromDb = list<Project>('projects');
    const propertiesFromDb = list<Property>('properties');
    const dealersFromDb = list<Dealer>('dealers');
    const videosFromDb = list<AgencyVideo>('videos');
    const plotsFromDb = list<PlotMarker>('plots');
    const mediaFromDb = list<MediaItem>('media');
    if (projectsFromDb) setProjects(projectsFromDb);
    if (propertiesFromDb) setProperties(propertiesFromDb);
    if (dealersFromDb) setDealers(dealersFromDb);
    if (videosFromDb) setAgencyVideos(videosFromDb);
    if (plotsFromDb) setPlotMarkers(plotsFromDb);
    if (mediaFromDb) setMediaItems(mediaFromDb);
    if (data.cms && typeof data.cms === 'object' && Object.keys(data.cms as object).length) {
      setCmsConfig(data.cms as CMSConfig);
    }
    if (!staff) return;
    setSellers(list<Seller>('sellers') ?? []);
    setBuyers(list<Buyer>('buyers') ?? []);
    setTransactions(list<Transaction>('transactions') ?? []);
    setPaymentEntries(list<PaymentEntry>('payments') ?? []);
    setCommissionEntries(list<CommissionEntry>('commissions') ?? []);
    setPropertyFiles(list<PropertyFileItem>('files') ?? []);
    setInquiries(list<Inquiry>('inquiries') ?? []);
    erpReadyRef.current = true;
    setErpReady(true);
    staffPersistRef.current = true;
  };

  /**
   * Guest/public hydration.
   *
   * Listings come ONLY from the governed Property API (agency-owned +
   * approved + public + sale). The legacy catalog endpoint may still supply
   * CMS/videos/media/testimonials, but its `properties` collection must never
   * override the governed listings.
   */
  const applyPublicData = (
    catalog: Record<string, unknown> | null,
    publicProperties: Property[] | null
  ) => {
    if (catalog) {
      const catalogWithoutListings = { ...catalog };
      delete catalogWithoutListings.properties;
      applyServerData(catalogWithoutListings, false);
    }
    if (publicProperties) setProperties(publicProperties);
  };

  const clearWorkspace = () => {
    staffPersistRef.current = false;
    erpReadyRef.current = false;
    setErpReady(false);
    setProjects([]);
    setProperties([]);
    setDealers([]);
    setSellers([]);
    setBuyers([]);
    setTransactions([]);
    setPaymentEntries([]);
    setCommissionEntries([]);
    setPropertyFiles([]);
    setInquiries([]);
    setMediaItems([]);
    setAgencyVideos([]);
    setPlotMarkers([]);
    setCmsConfig(initialCMSConfig);
  };

  const clearSession = () => {
    clearWorkspace();
    setAuthToken(null);
    setApiAgencyId(null);
    sessionStorage.removeItem('tth_user');
    setCurrentUser(anonymousUser);
    setIsAdminAuthenticated(false);
  };

  useEffect(() => {
    const onAccessDenied = (event: Event) => {
      const { expired, access } = (event as CustomEvent).detail;
      sessionVersionRef.current++;
      if (expired) {
        clearSession();
      } else {
        clearWorkspace();
        setCurrentUser((user) => ({ ...user, workspaceAccess: access }));
      }
      setIsSessionLoading(false);
    };
    window.addEventListener('tth:access-denied', onAccessDenied);
    return () => window.removeEventListener('tth:access-denied', onAccessDenied);
  }, []);

  const loadAuthenticatedWorkspace = async (payload: Record<string, unknown>, version: number) => {
    if (version !== sessionVersionRef.current) return;
    const user = userFromServer(payload);
    setApiAgencyId(defaultAgencyId(payload));
    if (user.workspaceAccess?.allowed) {
      const workspace = await api.getWorkspace();
      if (version !== sessionVersionRef.current) return;
      if (workspace) {
        applyServerData(workspace, true);
      } else {
        clearWorkspace();
        user.workspaceAccess = { allowed: false, status: 'unavailable', message: 'Your workspace could not be loaded. Check your connection and try again.' };
      }
    } else {
      clearWorkspace();
    }
    if (version !== sessionVersionRef.current) return;
    setCurrentUser(user);
    setIsAdminAuthenticated(true);
    setBackendConnected(true);
  };

  const refreshSession = async () => {
    const version = ++sessionVersionRef.current;
    staffPersistRef.current = false;
    setIsSessionLoading(true);
    try {
      const payload = getAuthToken() ? await api.getMe() : null;
      if (version !== sessionVersionRef.current) return;
      if (payload) {
        await loadAuthenticatedWorkspace(payload, version);
      } else {
        clearSession();
        showToast('error', 'Your session could not be verified. Please sign in again.');
      }
    } finally {
      if (version === sessionVersionRef.current) setIsSessionLoading(false);
    }
  };

  useEffect(() => {
    const version = ++sessionVersionRef.current;
    const boot = async () => {
      if (getAuthToken()) {
        const payload = await api.getMe();
        if (version !== sessionVersionRef.current) return;
        if (payload) {
          await loadAuthenticatedWorkspace(payload, version);
          if (version === sessionVersionRef.current) setIsSessionLoading(false);
          return;
        }
        clearSession();
      }
      const [catalog, publicProperties] = await Promise.all([
        api.getCatalog(),
        api.getPublicProperties(),
      ]);
      if (version !== sessionVersionRef.current) return;
      applyPublicData(catalog, publicProperties);
      setBackendConnected(Boolean(catalog) || publicProperties !== null);
      setIsSessionLoading(false);
    };
    void boot();
    return () => { sessionVersionRef.current++; };
  }, []);

  useEffect(() => {
    if (!staffPersistRef.current || !getAuthToken()) return;
    const handle = window.setTimeout(() => {
      void api.saveWorkspace({
        projects,
        properties,
        dealers,
        sellers,
        buyers,
        transactions,
        payments: paymentEntries,
        commissions: commissionEntries,
        files: propertyFiles,
        plots: plotMarkers,
        media: mediaItems,
        videos: agencyVideos,
        inquiries,
        cms: cmsConfig,
      }).then((ok) => {
        if (!ok) showToast('error', 'MySQL save failed. Check that the Laravel API is running.');
      });
    }, 400);
    return () => window.clearTimeout(handle);
  }, [
    projects, properties, dealers, sellers, buyers, transactions, paymentEntries,
    commissionEntries, propertyFiles, plotMarkers, mediaItems, agencyVideos, inquiries, cmsConfig,
  ]);

  // Accessible confirmation dialog state (replaces window.confirm()).
  const [pendingConfirm, setPendingConfirm] = useState<{
    message: string;
    title?: string;
    confirmLabel?: string;
    resolve: (accepted: boolean) => void;
  } | null>(null);

  const confirmAction = (
    message: string,
    options?: { title?: string; confirmLabel?: string }
  ): Promise<boolean> =>
    new Promise<boolean>((resolve) => {
      setPendingConfirm({
        message,
        title: options?.title,
        confirmLabel: options?.confirmLabel,
        resolve,
      });
    });

  const resolveConfirm = (accepted: boolean) => {
    setPendingConfirm((prev) => {
      prev?.resolve(accepted);
      return null;
    });
  };

  const removeToast = (id: string) => {
    setToasts((prev) => prev.filter((t) => t.id !== id));
  };

  /**
   * Domain-layer authorization gate (P0-4). Every mutating action must start
   * with `if (!requirePermission('x')) return;`. UI hiding (disabled buttons)
   * is cosmetic only — this is the enforcement point.
   */
  const requirePermission = (permission: Permission | Permission[], scope?: { dealerId?: string }): boolean => {
    if (!isAdminAuthenticated || currentUser.workspaceAccess?.allowed === false) {
      showToast('error', 'An approved agency session is required.');
      return false;
    }
    const gate = authorize(currentUser, permission, scope);
    if (!gate.ok) {
      showToast('error', gate.error);
      return false;
    }
    return true;
  };

  // Auth & RBAC Handlers
  // Canonical role normalization lives in src/domain/permissions.ts (P0-4).

  // Presenter PIN gate (demo builds only). Honest limitation: a client-side PIN
  // is a speed bump against casual snooping, not real access control. Demo
  // deployments must also use Vercel password protection and fictional data only.
  // H21: 3 consecutive failures lock the gate for 30 seconds (covers the login
  // modal and every role-switch path, since they all funnel through here).
  const pinGateRef = useRef({ fails: 0, lockedUntil: 0 });
  const PIN_MAX_ATTEMPTS = 3;
  const PIN_LOCK_MS = 30_000;

  const verifyPresenterPin = (pin: string): boolean => {
    if (!IS_DEMO) return false;
    if (!DEMO_PIN) return false; // PIN gate disabled when no env var is set
    const now = Date.now();
    const gate = pinGateRef.current;
    if (now < gate.lockedUntil) return false; // silent reject while locked
    if (String(pin) === DEMO_PIN) {
      gate.fails = 0;
      return true;
    }
    gate.fails += 1;
    if (gate.fails >= PIN_MAX_ATTEMPTS) {
      gate.fails = 0;
      gate.lockedUntil = now + PIN_LOCK_MS;
      showToast(
        'error',
        `Too many failed PIN attempts - the PIN gate is locked for ${PIN_LOCK_MS / 1000} seconds.`
      );
    }
    return false;
  };

  const switchAuthUser = async (role: UserRole, pin?: string): Promise<boolean> => {
    if (APP_MODE !== 'demo') {
      showToast('error', 'Role switching is disabled outside of DEMO MODE.');
      return false;
    }
    if (!verifyPresenterPin(pin ?? '')) {
      showToast('error', 'Invalid presenter PIN.');
      return false;
    }
    const loaded = await ensureErpLoaded();
    if (!loaded) return false;
    const erp = await import('../data/erpSeed');
    const user =
      erp.initialAuthUsers.find((u) => normalizeRole(u.role) === normalizeRole(role)) ||
      erp.initialAuthUsers[0];
    setCurrentUser(user);
    showToast('info', `DEMO MODE: switched active session to ${user.name} (${user.role}).`);
    logAudit({ actor: currentUser.name, role: currentUser.role, action: 'switch_role', target: user.id, detail: `Switched to ${user.name} (${user.role})` });
    return true;
  };

  const loginAsDemo = async (role: UserRole, pin?: string): Promise<boolean> => {
    if (APP_MODE !== 'demo') {
      showToast('error', 'Demo login is disabled outside of DEMO MODE.');
      return false;
    }
    if (!verifyPresenterPin(pin ?? '')) {
      showToast('error', 'Invalid presenter PIN.');
      return false;
    }
    const loaded = await ensureErpLoaded();
    if (!loaded) return false;
    const erp = await import('../data/erpSeed');
    const user =
      erp.initialAuthUsers.find((u) => normalizeRole(u.role) === normalizeRole(role)) ||
      erp.initialAuthUsers[0];
    setCurrentUser(user);
    setIsAdminAuthenticated(true);
    logAudit({ actor: user.name, role: user.role, action: 'demo_login', target: user.id, detail: `Demo login as ${user.role}` });
    showToast('success', `Demo login: ${user.name} (${user.role}).`);
    return true;
  };

  const loginWithCredentials = async (email: string, pass: string): Promise<{ success: boolean; error?: string }> => {
    if (!pass || pass.trim() === '') {
      showToast('error', 'Password is required.');
      return { success: false, error: 'Password is required.' };
    }
    const result = await api.login(email.trim().toLowerCase(), pass);
    if (!result.success || !result.token || !result.user) {
      const message = result.error || 'Invalid email or password.';
      showToast('error', message);
      return { success: false, error: message };
    }
    const version = ++sessionVersionRef.current;
    clearWorkspace();
    setAuthToken(result.token);
    await loadAuthenticatedWorkspace(result.user, version);
    setIsSessionLoading(false);
    return { success: true };
  };

  const loginAdmin = async (password: string): Promise<boolean> => {
    return (await loginWithCredentials('admin@taisartownhub.com', password)).success;
  };

  const registerDealerAccount = async (data: {
    fullName: string;
    email: string;
    password?: string;
    phone: string;
    officeAddress?: string;
    agencyOrBranch: string;
    sectorFocus?: string;
  }): Promise<{ success: boolean; error?: string }> => {
    const name = data.fullName.trim();
    const email = data.email.trim().toLowerCase();
    const phone = data.phone.trim();
    const password = data.password || '';
    if (name.length < 3 || data.agencyOrBranch.trim().length < 3 || !phone || !email || password.length < 8) {
      const message = 'Owner and agency names (at least 3 characters), phone, email and a password of at least 8 characters are required.';
      showToast('error', message);
      return { success: false, error: message };
    }
    const result = await api.register({
      contactName: name,
      name: data.agencyOrBranch.trim(),
      email,
      password,
      phone,
      office_address: data.officeAddress?.trim(),
      description: data.sectorFocus ? `Primary sector focus: ${data.sectorFocus}` : undefined,
    });
    if (!result.success || !result.token || !result.user) {
      const message = result.error || 'Registration failed.';
      showToast('error', message);
      return { success: false, error: message };
    }
    const version = ++sessionVersionRef.current;
    clearWorkspace();
    setAuthToken(result.token);
    await loadAuthenticatedWorkspace(result.user, version);
    setIsSessionLoading(false);
    showToast('success', 'Application received. Your agency needs platform approval before workspace access.');
    return { success: true };
  };

  const logoutAdmin = () => {
    const version = ++sessionVersionRef.current;
    void api.logout();
    clearSession();
    setIsSessionLoading(false);
    void Promise.all([api.getCatalog(), api.getPublicProperties()]).then(([catalog, publicProperties]) => {
      if (version === sessionVersionRef.current) applyPublicData(catalog, publicProperties);
    });
    showToast('info', 'Signed out. Private records have been cleared from this session.');
    setCurrentView('home');
  };

  // Presentation Mode Toggle (demo builds only; in-memory, not localStorage)
  const togglePresentationMode = () => {
    if (APP_MODE !== 'demo') {
      showToast('info', 'Presentation mode is a demo-build feature.');
      return;
    }
    setIsPresentationMode((prev) => {
      const next = !prev;
      showToast('info', next ? 'Client Presentation Mode activated! Clean layout and theme switcher enabled.' : 'Client Presentation Mode closed.');
      return next;
    });
  };

  const currentTheme = clientThemes.find((t) => t.id === currentThemeId) || clientThemes[0];

  const setClientThemeId = (themeId: ClientThemeId) => {
    setCurrentThemeId(themeId);
    const chosen = clientThemes.find((t) => t.id === themeId);
    if (chosen) {
      showToast('success', `Theme switched to "${chosen.name}"`);
    }
  };

  // Projects CRUD
  const addProject = (projectData: Omit<Project, 'id' | 'createdDate'>) => {
    if (!requirePermission('manage_projects')) return;
    const newProject: Project = {
      ...projectData,
      id: `proj-${Date.now()}`,
      createdDate: new Date().toISOString().split('T')[0],
    };
    setProjects((prev) => [newProject, ...prev]);
    showToast('success', `Project "${newProject.title}" published successfully!`);
  };

  const updateProject = (id: string, updates: Partial<Project>) => {
    if (!requirePermission('manage_projects')) return;
    setProjects((prev) =>
      prev.map((p) => (p.id === id ? { ...p, ...updates } : p))
    );
    showToast('success', 'Project details updated.');
  };

  const deleteProject = (id: string) => {
    if (!requirePermission('manage_projects')) return;
    const today = new Date().toISOString().split('T')[0];
    setProjects((prev) => prev.map((p) => (p.id === id ? { ...p, deletedAt: today, deletedBy: currentUser.name } : p)));
    showToast('info', 'Project moved to trash (soft-deleted, restorable).');
    logAudit({ actor: currentUser.name, role: currentUser.role, action: 'soft_delete_project', target: id });
  };

  const restoreProject = (id: string) => {
    if (!requirePermission('manage_projects')) return;
    setProjects((prev) => prev.map((p) => (p.id === id ? { ...p, deletedAt: undefined, deletedBy: undefined } : p)));
    showToast('success', 'Project restored from trash.');
    logAudit({ actor: currentUser.name, role: currentUser.role, action: 'restore_project', target: id });
  };

  // Properties CRUD with Duplicate Detection
  const addProperty = (propData: Omit<Property, 'id' | 'createdDate'>): { success: boolean; error?: string } => {
    if (!requirePermission(['create_properties', 'edit_properties'])) return { success: false, error: 'Permission denied.' };
    // Uniqueness validation on plot number + sector + block
    const dupCheck = checkDuplicateProperty(properties, propData.plotNumber, propData.sector, propData.block);
    if (dupCheck.isDuplicate) {
      const errorMsg = `Duplicate Error: Plot "${propData.plotNumber}" already exists in ${propData.sector} (${dupCheck.duplicateProperty?.title}). Please verify plot identifier.`;
      showToast('error', errorMsg);
      return { success: false, error: errorMsg };
    }

    const newProp: Property = {
      ...propData,
      id: `prop-${Date.now()}`,
      createdDate: new Date().toISOString().split('T')[0],
    };
    setProperties((prev) => [newProp, ...prev]);
    if (backendConnected) {
      showToast('info', `Property "${newProp.title}" saved locally — backend sync not yet wired for properties.`);
    } else {
      showToast('info', `Property "${newProp.title}" saved locally — will sync when backend is available.`);
    }
    return { success: true };
  };

  const updateProperty = (id: string, updates: Partial<Property>): { success: boolean; error?: string } => {
    if (!requirePermission('edit_properties')) return { success: false, error: 'Permission denied.' };
    const target = properties.find((p) => p.id === id);
    if (target) {
      const plotNum = updates.plotNumber !== undefined ? updates.plotNumber : target.plotNumber;
      const sector = updates.sector !== undefined ? updates.sector : target.sector;
      const block = updates.block !== undefined ? updates.block : target.block;

      const dupCheck = checkDuplicateProperty(properties, plotNum, sector, block, id);
      if (dupCheck.isDuplicate) {
        const errorMsg = `Duplicate Error: Plot "${plotNum}" already exists in ${sector}.`;
        showToast('error', errorMsg);
        return { success: false, error: errorMsg };
      }
    }

    setProperties((prev) =>
      prev.map((p) => (p.id === id ? { ...p, ...updates } : p))
    );
    showToast('success', 'Property listing updated.');
    return { success: true };
  };

  const deleteProperty = (id: string) => {
    if (!requirePermission('delete_properties')) return;
    const today = new Date().toISOString().split('T')[0];
    setProperties((prev) => prev.map((p) => (p.id === id ? { ...p, deletedAt: today, deletedBy: currentUser.name } : p)));
    showToast('info', 'Property moved to trash (soft-deleted, restorable).');
    logAudit({ actor: currentUser.name, role: currentUser.role, action: 'soft_delete_property', target: id });
  };

  const restoreProperty = (id: string) => {
    if (!requirePermission('delete_properties')) return;
    setProperties((prev) => prev.map((p) => (p.id === id ? { ...p, deletedAt: undefined, deletedBy: undefined } : p)));
    showToast('success', 'Property restored from trash.');
    logAudit({ actor: currentUser.name, role: currentUser.role, action: 'restore_property', target: id });
  };

  // Sellers Management
  const addSeller = (sellerData: Omit<Seller, 'id' | 'createdAt'>) => {
    if (!requirePermission('manage_sellers')) return;
    const newSeller: Seller = {
      ...sellerData,
      id: `sel-${Date.now()}`,
      createdAt: new Date().toISOString().split('T')[0],
    };
    setSellers((prev) => [newSeller, ...prev]);
    showToast('success', `Seller "${newSeller.fullName}" registered.`);
  };

  const updateSeller = (id: string, updates: Partial<Seller>) => {
    if (!requirePermission('manage_sellers')) return;
    setSellers((prev) => prev.map((s) => (s.id === id ? { ...s, ...updates } : s)));
    showToast('success', 'Seller profile updated.');
  };

  const deleteSeller = (id: string) => {
    if (!requirePermission('manage_sellers')) return;
    const referenced = transactions.some((t) => t.sellerId === id && !t.voided);
    if (referenced) {
      showToast('error', 'Cannot delete: seller is referenced by existing transactions. Archive instead.');
      return;
    }
    const today = new Date().toISOString().split('T')[0];
    setSellers((prev) => prev.map((s) => (s.id === id ? { ...s, deletedAt: today, deletedBy: currentUser.name } : s)));
    showToast('info', 'Seller moved to trash (soft-deleted, restorable).');
    logAudit({ actor: currentUser.name, role: currentUser.role, action: 'soft_delete_seller', target: id });
  };

  const restoreSeller = (id: string) => {
    if (!requirePermission('manage_sellers')) return;
    setSellers((prev) => prev.map((s) => (s.id === id ? { ...s, deletedAt: undefined, deletedBy: undefined } : s)));
    showToast('success', 'Seller restored from trash.');
    logAudit({ actor: currentUser.name, role: currentUser.role, action: 'restore_seller', target: id });
  };

  // Buyers Management
  const addBuyer = (buyerData: Omit<Buyer, 'id' | 'createdAt'>) => {
    if (!requirePermission(['manage_buyers', 'submit_buyer'])) return;
    const newBuyer: Buyer = {
      ...buyerData,
      id: `buy-${Date.now()}`,
      createdAt: new Date().toISOString().split('T')[0],
    };
    setBuyers((prev) => [newBuyer, ...prev]);
    showToast('success', `Buyer "${newBuyer.fullName}" registered.`);
  };

  const updateBuyer = (id: string, updates: Partial<Buyer>) => {
    if (!requirePermission(['manage_buyers', 'submit_buyer'])) return;
    setBuyers((prev) => prev.map((b) => (b.id === id ? { ...b, ...updates } : b)));
    showToast('success', 'Buyer profile updated.');
  };

  const deleteBuyer = (id: string) => {
    if (!requirePermission('manage_buyers')) return;
    const referenced = transactions.some((t) => t.buyerId === id && !t.voided);
    if (referenced) {
      showToast('error', 'Cannot delete: buyer is referenced by existing transactions. Archive instead.');
      return;
    }
    const today = new Date().toISOString().split('T')[0];
    setBuyers((prev) => prev.map((b) => (b.id === id ? { ...b, deletedAt: today, deletedBy: currentUser.name } : b)));
    showToast('info', 'Buyer moved to trash (soft-deleted, restorable).');
    logAudit({ actor: currentUser.name, role: currentUser.role, action: 'soft_delete_buyer', target: id });
  };

  const restoreBuyer = (id: string) => {
    if (!requirePermission('manage_buyers')) return;
    setBuyers((prev) => prev.map((b) => (b.id === id ? { ...b, deletedAt: undefined, deletedBy: undefined } : b)));
    showToast('success', 'Buyer restored from trash.');
    logAudit({ actor: currentUser.name, role: currentUser.role, action: 'restore_buyer', target: id });
  };

  // Dealers Management
  const addDealer = (dealerData: Omit<Dealer, 'id' | 'createdAt' | 'totalEarnedPKR' | 'pendingCommissionPKR'>) => {
    if (!requirePermission('manage_dealers')) return;
    const newDealer: Dealer = {
      ...dealerData,
      id: `deal-${Date.now()}`,
      totalEarnedPKR: 0,
      pendingCommissionPKR: 0,
      createdAt: new Date().toISOString().split('T')[0],
    };
    setDealers((prev) => [newDealer, ...prev]);
    showToast('success', `Dealer / Agent "${newDealer.fullName}" added.`);
  };

  const updateDealer = (id: string, updates: Partial<Dealer>) => {
    if (!requirePermission('manage_dealers')) return;
    setDealers((prev) => prev.map((d) => (d.id === id ? { ...d, ...updates } : d)));
    showToast('success', 'Dealer details updated.');
  };

  const deleteDealer = (id: string) => {
    if (!requirePermission('manage_dealers')) return;
    const referenced = transactions.some((t) => t.dealerId === id && !t.voided);
    if (referenced) {
      showToast('error', 'Cannot delete: dealer is referenced by existing transactions.');
      return;
    }
    const today = new Date().toISOString().split('T')[0];
    setDealers((prev) => prev.map((d) => (d.id === id ? { ...d, deletedAt: today, deletedBy: currentUser.name } : d)));
    showToast('info', 'Dealer moved to trash (soft-deleted, restorable).');
    logAudit({ actor: currentUser.name, role: currentUser.role, action: 'soft_delete_dealer', target: id });
  };

  const restoreDealer = (id: string) => {
    if (!requirePermission('manage_dealers')) return;
    setDealers((prev) => prev.map((d) => (d.id === id ? { ...d, deletedAt: undefined, deletedBy: undefined } : d)));
    showToast('success', 'Dealer restored from trash.');
    logAudit({ actor: currentUser.name, role: currentUser.role, action: 'restore_dealer', target: id });
  };

  const payDealerCommission = (dealerId: string, amount: number, transactionId?: string) => {
    // Only Super Admin executes payouts (P0-4: Admin has visibility, not payment authority).
    if (!requirePermission('pay_commissions')) return;
    // Validation
    if (!Number.isSafeInteger(amount) || amount <= 0) {
      showToast('error', 'Payout amount must be a positive whole-rupee PKR amount.');
      return;
    }
    const dealer = dealers.find((d) => d.id === dealerId);
    if (!dealer) {
      showToast('error', 'Dealer not found.');
      return;
    }

    // P1-3 commission-ledger payouts: pay ACCRUED entries only, newest first,
    // optionally scoped to one deal. Pending-accrual promises stay unpayable.
    const payableEntries = commissionEntries
      .filter((c) =>
        c.dealerId === dealerId &&
        (c.status === 'Accrued' || c.status === 'Partially Paid') &&
        (!transactionId || c.transactionId === transactionId))
      .sort((a, b) => (b.accruedOn || '').localeCompare(a.accruedOn || ''));

    const today = new Date().toISOString().split('T')[0];
    let remaining = amount;
    const entryUpdates: CommissionEntry[] = [];

    for (const entry of payableEntries) {
      if (remaining <= 0) break;
      const commissionAmount = Number(entry.amountPKR);
      const alreadyPaid = Number(entry.paidPKR ?? 0);
      const due = commissionAmount - alreadyPaid;
      if (!Number.isSafeInteger(commissionAmount) || !Number.isSafeInteger(alreadyPaid) || due <= 0) continue;
      const payNow = Math.min(due, remaining);
      const newPaid = alreadyPaid + payNow;
      entryUpdates.push({
        ...entry,
        paidPKR: newPaid,
        status: newPaid >= entry.amountPKR ? 'Paid' : 'Partially Paid',
        payouts: [
          ...(entry.payouts ?? []),
          { id: genId('payout'), amountPKR: payNow, paidAt: today, recordedBy: currentUser.name, note: transactionId ? 'Payout against specific deal.' : undefined },
        ],
      });
      remaining -= payNow;
    }

    const applied = amount - remaining;
    if (applied <= 0) {
      showToast(
        'info',
        'No ACCRUED commission is pending for this dealer. Commission becomes payable only after the buyer completes payment (or the deal completes).'
      );
      return;
    }

    // Commit ledger + keep the transaction row's commission status in sync.
    const updatedEntries = commissionEntries.map((e) => entryUpdates.find((u) => u.id === e.id) ?? e);
    setCommissionEntries(updatedEntries);
    setTransactions((prev) =>
      prev.map((t) => {
        const upd = entryUpdates.find((u) => u.transactionId === t.id);
        if (!upd) return t;
        return {
          ...t,
          commissionStatus: upd.status === 'Paid' ? 'Paid' : upd.status === 'Partially Paid' ? 'Partially Paid' : t.commissionStatus,
          commissionPaidPKR: upd.paidPKR,
        };
      })
    );

    // Dealer aggregates are re-derived from the ledger (single source of truth).
    const affected = updatedEntries.filter((e) => e.dealerId === dealerId && e.status !== 'Cancelled');
    const accrued = affected.reduce((sum, e) => sum + e.amountPKR, 0);
    const paid = affected.reduce((sum, e) => sum + e.paidPKR, 0);
    setDealers((prev) =>
      prev.map((d) =>
        d.id === dealerId
          ? {
              ...d,
              totalEarnedPKR: accrued,
              totalCommissionPaidPKR: paid,
              pendingCommissionPKR: Math.max(0, accrued - paid),
            }
          : d
      )
    );

    // Audit EVERY payout that moved money — partial and full alike. Logging
    // only in the full-payout branch silently skipped partial payouts from the
    // append-only audit trail (ledger committed, audit row absent).
    logAudit({
      actor: currentUser.name,
      role: currentUser.role,
      action: 'pay_commission',
      target: dealerId,
      detail: `PKR ${applied.toLocaleString()} paid to ${dealer.fullName}` +
        (remaining > 0 ? ` (requested PKR ${amount.toLocaleString()}; only ${applied.toLocaleString()} was accrued)` : ''),
    });
    if (remaining > 0) {
      showToast(
        'info',
        `Only PKR ${applied.toLocaleString()} of accrued commission was outstanding — recorded that amount for ${dealer.fullName}.`
      );
    } else {
      showToast('success', `Commission payout of PKR ${applied.toLocaleString()} recorded for ${dealer.fullName}.`);
    }
  };

  // Transactions Management
  const addTransaction = (txData: Omit<Transaction, 'id'>) => {
    if (!requirePermission('create_transactions')) return;

    // P1-4: shared domain validation (form ports to the domain rule set).
    const check = validateDealPayload(
      {
        propertyId: txData.propertyId,
        sellerId: txData.sellerId,
        buyerId: txData.buyerId,
        dealAmountPKR: txData.dealAmountPKR,
        advancePKR: txData.advancePKR,
      },
      { propertyId: txData.propertyId, sellerId: txData.sellerId, buyerId: txData.buyerId },
      { properties, sellers, buyers }
    );
    if (!check.ok) {
      showToast('error', check.error);
      return;
    }

    // Prevent duplicate active sale deals per property unless supervisor-approved.
    const hasActiveTransaction = transactions.some(
      (t) =>
        t.propertyId === txData.propertyId &&
        !t.voided &&
        t.status !== 'Cancelled' &&
        t.status !== 'Draft'
    );
    if (hasActiveTransaction && !userHasPermission(currentUser, 'approve_transactions')) {
      showToast('error', `An active transaction already exists for property ID "${txData.propertyId}". Complete or cancel it first.`);
      return;
    }

    const today = new Date().toISOString().split('T')[0];
    const year = today.slice(0, 4);
    const dealNumber = txData.dealNumber || nextDealNumber(year, transactions.map((t) => t.dealNumber || '').filter(Boolean));

    const newTx: Transaction = {
      ...txData,
      id: genId('tx'),
      dealNumber,
    };

    // Ledger rows are created with the deal (P1-2/P1-3 integrity): the advance
    // optimism of the form becomes a real payment movement, and the agreed
    // dealer commission becomes a tracked accrual entry (not money yet).
    const advance = Number(txData.advancePKR ?? txData.advancePaymentPKR ?? 0);
    const newPayments = [...paymentEntries];
    if (advance > 0) {
      newPayments.push({
        id: genId('pay'),
        transactionId: newTx.id,
        amountPKR: advance,
        method: (newTx.paymentMethod as PaymentEntry['method']) || 'Bank Transfer',
        paidAt: newTx.transactionDate || today,
        recordedBy: currentUser.name,
        note: 'Advance recorded at deal creation.',
      });
    }
    setPaymentEntries(newPayments);

    const commission = Number(newTx.commissionAmountPKR ?? newTx.commissionPKR ?? 0);
    if (newTx.dealerId && commission > 0) {
      const entry: CommissionEntry = {
        id: genId('cm'),
        transactionId: newTx.id,
        dealerId: newTx.dealerId,
        dealerName: newTx.dealerName,
        amountPKR: commission,
        paidPKR: 0,
        payouts: [],
        status: 'Pending Accrual',
        note: 'Agreed at deal creation; accrues at the Fully Paid/Completed milestone.',
      };
      setCommissionEntries((prev) => [entry, ...prev]);
    }

    setTransactions((prev) => [newTx, ...prev]);

    // Property status mirroring
    if (newTx.status === 'Completed') {
      setProperties((prev) =>
        prev.map((p) => (p.id === newTx.propertyId ? { ...p, status: 'Sold' } : p))
      );
    } else if (newTx.status === 'Agreement Signed') {
      setProperties((prev) =>
        prev.map((p) => (p.id === newTx.propertyId ? { ...p, status: 'Reserved' } : p))
      );
    }

    showToast('success', `Deal ${dealNumber} recorded.`);
  };

  const updateTransaction = (id: string, updates: Partial<Transaction>) => {
    // Cancelling is a distinct, more sensitive action than general updates.
    if (updates.status === 'Cancelled') {
      if (!requirePermission('cancel_transactions')) return;
    } else if (!requirePermission('update_transactions')) {
      return;
    }
    const existingTx = transactions.find((t) => t.id === id);
    if (!existingTx) {
      showToast('error', 'Transaction not found.');
      return;
    }
    if (existingTx.voided) {
      showToast('error', 'This deal is voided and read-only.');
      return;
    }
    if (['Completed', 'Cancelled'].includes(existingTx.status) && !userHasPermission(currentUser, 'approve_transactions')) {
      showToast('error', 'Completed and cancelled deals are read-only.');
      return;
    }

    // Prevent duplicate active sale deals per property unless supervisor-approved.
    const targetPropertyId = updates.propertyId || existingTx.propertyId;
    if (targetPropertyId && targetPropertyId !== existingTx.propertyId) {
      const hasActive = transactions.some(
        (t) =>
          t.id !== id &&
          t.propertyId === targetPropertyId &&
          !t.voided &&
          t.status !== 'Cancelled' &&
          t.status !== 'Draft'
      );
      if (hasActive && !userHasPermission(currentUser, 'approve_transactions')) {
        showToast('error', `An active transaction already exists for property ID "${targetPropertyId}".`);
        return;
      }
    }

    let updatedTx: Transaction = { ...existingTx, ...updates };
    const statusChanged = !!updates.status && updates.status !== existingTx.status;

    // P1-1: lifecycle transitions validated by the domain state machine
    // (supervisors holding approve_transactions may make corrective moves).
    if (updates.status && updates.status !== existingTx.status) {
      const gate = validateTransition(existingTx.status, updates.status);
      const isPrivileged = userHasPermission(currentUser, 'approve_transactions');
      if (!gate.ok) {
        if (!isPrivileged) {
          showToast('error', gate.error);
          return;
        }
      }

      updatedTx = { ...updatedTx, status: updates.status };

      // On Cancel: confirm a reason (updates note), cancel unpaid accruals and
      // release the property back to inventory.
      if (updates.status === 'Cancelled') {
        const cancellationReason = (existingTx.internalRemarks || existingTx.notes || updates.internalRemarks || updates.notes || '').trim();
        if (cancellationReason.length < 3 || cancellationReason.length > 2000) {
          showToast('error', 'A cancellation reason must be between 3 and 2,000 characters.');
          return;
        }
        const cancelledTx = { ...updatedTx, commissionStatus: 'Cancelled' as const };
        setCommissionEntries((prev) =>
          prev.map((entry) => {
            if (entry.transactionId !== id || entry.status === 'Paid' || entry.paidPKR > 0) return entry;
            return { ...entry, status: 'Cancelled' };
          })
        );
        updatedTx = cancelledTx;
        setProperties((props) =>
          props.map((p) => (p.id === existingTx.propertyId ? { ...p, status: 'Available' } : p))
        );
      } else if (updates.status === 'Completed' || updates.status === 'Under Transfer') {
        setProperties((props) =>
          props.map((p) => (p.id === existingTx.propertyId ? { ...p, status: 'Sold' } : p))
        );
      } else if (existingTx.status === 'Completed' || existingTx.status === 'Under Transfer') {
        // Privileged corrective move away from a sale-state: release listing.
        setProperties((props) =>
          props.map((p) => (p.id === existingTx.propertyId ? { ...p, status: 'Reserved' } : p))
        );
      }
    }

    setTransactions((prev) => prev.map((t) => (t.id === id ? updatedTx : t)));

    // P0-6: lifecycle changes are append-only audited ("Audit-Ready" ERP) -
    // every stage move is recorded with who/when, not just voids & payments.
    logAudit({
      actor: currentUser.name,
      role: currentUser.role,
      action: statusChanged ? 'status_change' : 'update_transaction',
      target: id,
      detail: statusChanged
        ? `${existingTx.status} -> ${updatedTx.status}`
        : `Updated: ${Object.keys(updates).join(', ') || 'no fields'}`,
    });

    // P1-3: re-check the accrual milestone after any meaningful change.
    if (commissionEntries.some((e) => e.transactionId === id)) {
      setCommissionEntries((prev) =>
        prev.map((e) => {
          if (e.transactionId !== id) return e;
          const r = accrueCommission(e, updatedTx, paymentEntries);
          if (r.changed) {
            showToast('success', r.note);
            return r.entry;
          }
          return e;
        })
      );
    }

    showToast('success', updates.status && updates.status !== existingTx.status
      ? `Deal moved to "${updates.status}".`
      : 'Transaction updated.');
  };

  /** P1-2 payment ledger. Admin/Super Admin record money movements; the deal's
   *  payment status and stage are derived, never hand-typed past the balance. */
  const recordPayment = (transactionId: string, amount: number, method: PaymentEntry['method'], note?: string): boolean => {
    if (!requirePermission('manage_payments')) return false;
    const txn = transactions.find((t) => t.id === transactionId);
    if (!txn) {
      showToast('error', 'Transaction not found.');
      return false;
    }
    const gate = validatePayment(txn, paymentEntries, amount, {
      allowOverpay: userHasPermission(currentUser, 'approve_transactions'),
    });
    if (!gate.ok) {
      showToast('error', gate.error);
      return false;
    }

    const today = new Date().toISOString().split('T')[0];
    const entry: PaymentEntry = {
      id: genId('pay'),
      transactionId,
      amountPKR: Number(amount),
      method,
      paidAt: today,
      recordedBy: currentUser.name,
      type: 'payment',
      note: (note || '').trim() || undefined,
    };
    const updatedPayments = [...paymentEntries, entry];
    setPaymentEntries(updatedPayments);

    const money = dealMoneyOf(txn, updatedPayments);
    const nextStatus = impliedStatusByPayment(txn, updatedPayments);
    let updatedTx: Transaction = {
      ...txn,
      paymentStatus: money.isFullyPaid ? 'Fully Paid' : money.collected > 0 ? 'Partially Paid' : txn.paymentStatus,
      remainingBalancePKR: money.balance,
      balancePKR: money.balance,
    };
    // Auto-advance the stage only forward, never past a terminal/sale state.
    const stageOf = (s: Transaction['status']) => TRANSACTION_STAGES.indexOf(s);
    if (
      !['Completed', 'Cancelled', 'Under Transfer'].includes(txn.status) &&
      stageOf(nextStatus) > stageOf(txn.status)
    ) {
      updatedTx = { ...updatedTx, status: nextStatus };
    }

    setTransactions((prev) => prev.map((t) => (t.id === transactionId ? updatedTx : t)));

    // Milestone check: crossing Fully Paid accrues the dealer commission.
    if (commissionEntries.some((e) => e.transactionId === transactionId)) {
      setCommissionEntries((prev) =>
        prev.map((e) => {
          if (e.transactionId !== transactionId) return e;
          const r = accrueCommission(e, updatedTx, updatedPayments);
          if (r.changed) {
            showToast('success', r.note);
            return r.entry;
          }
          return e;
        })
      );
    }

    showToast(
      'success',
      `Payment of PKR ${amount.toLocaleString()} recorded${updatedTx.status !== txn.status ? ` — deal moved to "${updatedTx.status}".` : '.'}`
    );
    logAudit({ actor: currentUser.name, role: currentUser.role, action: 'record_payment', target: transactionId, detail: 'PKR ' + amount.toLocaleString() + ' via ' + method });
    return true;
  };

  /** Record an audited refund; cancelled deals can be refunded, voided deals stay frozen. */
  const recordRefund = (transactionId: string, amount: number, method: PaymentEntry['method'], note: string): boolean => {
    if (!requirePermission('manage_payments')) return false;
    const txn = transactions.find((t) => t.id === transactionId);
    if (!txn) {
      showToast('error', 'Transaction not found.');
      return false;
    }
    if (!note.trim()) {
      showToast('error', 'A reason/reference note is required for every refund.');
      return false;
    }

    const gate = validateRefund(txn, paymentEntries, amount);
    if (!gate.ok) {
      showToast('error', gate.error);
      return false;
    }

    const today = new Date().toISOString().split('T')[0];
    const entry: PaymentEntry = {
      id: genId('refund'),
      transactionId,
      amountPKR: Number(amount),
      method,
      paidAt: today,
      recordedBy: currentUser.name,
      type: 'refund',
      note: note.trim(),
    };
    const updatedPayments = [...paymentEntries, entry];
    setPaymentEntries(updatedPayments);

    const money = dealMoneyOf(txn, updatedPayments);
    const updatedTx: Transaction = {
      ...txn,
      status: txn.status === 'Fully Paid' && !money.isFullyPaid ? 'Under Transfer' : txn.status,
      paymentStatus: money.isFullyPaid ? 'Fully Paid' : money.collected > 0 ? 'Partially Paid' : 'Unpaid',
      remainingBalancePKR: money.balance,
      balancePKR: money.balance,
    };
    setTransactions((prev) => prev.map((item) => (item.id === transactionId ? updatedTx : item)));

    logAudit({
      actor: currentUser.name,
      role: currentUser.role,
      action: 'record_refund',
      target: transactionId,
      detail: 'PKR ' + amount.toLocaleString() + ' refunded via ' + method + ' — ' + note.trim(),
    });
    showToast('success', `Refund of PKR ${amount.toLocaleString()} recorded.`);
    return true;
  };

  /** P1-1: voiding replaces hard delete - the record stays with an audit trail;
   *  totals exclude it, frozen payments surface in the void message. */
  const voidTransaction = (id: string, reason: string) => {
    if (!requirePermission('void_transactions')) return;
    const txn = transactions.find((t) => t.id === id);
    if (!txn) {
      showToast('error', 'Transaction not found.');
      return;
    }
    if (txn.voided) {
      showToast('info', 'This transaction is already voided.');
      return;
    }
    const cleanReason = (reason || '').trim();
    if (cleanReason.length < 3 || cleanReason.length > 2000) {
      showToast('error', 'Enter a void reason between 3 and 2,000 characters for the audit trail.');
      return;
    }

    const gate = validateVoid(txn, paymentEntries, commissionEntries);
    if (!gate.ok) {
      showToast('error', gate.error);
      return;
    }

    const today = new Date().toISOString().split('T')[0];
    const voidedTx: Transaction = {
      ...txn,
      status: 'Cancelled',
      voided: true,
      voidReason: cleanReason,
      voidedBy: currentUser.name,
      voidedAt: today,
    };

    setCommissionEntries((prev) =>
      prev.map((entry) => {
        if (entry.transactionId !== id || entry.status === 'Paid' || entry.paidPKR > 0) return entry;
        return { ...entry, status: 'Cancelled' };
      })
    );
    setTransactions((prev) => prev.map((t) => (t.id === id ? voidedTx : t)));
    setProperties((props) =>
      props.map((p) => (p.id === txn.propertyId ? { ...p, status: 'Available' } : p))
    );

    showToast('info', 'Deal voided (record kept for audit).');
    logAudit({ actor: currentUser.name, role: currentUser.role, action: 'void_transaction', target: id, detail: reason });
  };

  // Compatibility shim: old hard-deletes are replaced by the audited void flow.
  const deleteTransaction = (id: string) => voidTransaction(id, 'Removed via delete control (legacy).');

  // Property Files Management
  const addPropertyFile = (fileData: Omit<PropertyFileItem, 'id' | 'uploadedAt'>) => {
    if (!requirePermission('upload_documents')) return;
    const newFile: PropertyFileItem = {
      ...fileData,
      id: `doc-${Date.now()}`,
      uploadedAt: new Date().toISOString().split('T')[0],
    };
    setPropertyFiles((prev) => [newFile, ...prev]);
    showToast('success', `File "${newFile.title}" uploaded to verification desk.`);
  };

  const verifyPropertyFile = (id: string, status: DocumentVerificationStatus, notes?: string) => {
    if (!requirePermission('verify_documents')) return;
    // Verifying requires a genuine reviewer note (hard-coded "Legal Officer"
    // auto-note was earlier; P2-2 finalizes private document categories).
    const trimmed = (notes ?? '').trim();
    if (status === 'Verified' && trimmed === '') {
      showToast('error', 'A reviewer note is required when verifying a document.');
      return;
    }
    setPropertyFiles((prev) =>
      prev.map((f) =>
        f.id === id
          ? {
              ...f,
              verificationStatus: status,
              verifiedBy: currentUser.name,
              verifiedAt: new Date().toISOString().split('T')[0],
              notes: trimmed || f.notes,
            }
          : f
      )
    );
    showToast('info', `Document status updated to "${status}".`);
    logAudit({ actor: currentUser.name, role: currentUser.role, action: 'verify_document', target: id, detail: `Status → ${status}` });
  };

  const deletePropertyFile = (id: string) => {
    if (!requirePermission(['verify_documents', 'upload_documents'])) return;
    const file = propertyFiles.find((f) => f.id === id);
    if (!file || file.deletedAt) return;
    const today = new Date().toISOString().split('T')[0];
    setPropertyFiles((prev) =>
      prev.map((f) => (f.id === id ? { ...f, deletedAt: today, deletedBy: currentUser.name } : f))
    );
    showToast('info', 'Document moved to trash (soft-deleted, restorable).');
    logAudit({
      actor: currentUser.name,
      role: currentUser.role,
      action: 'soft_delete_document',
      target: id,
      detail: file.title
    });
  };

  const restorePropertyFile = (id: string) => {
    if (!requirePermission(['verify_documents', 'upload_documents'])) return;
    setPropertyFiles((prev) =>
      prev.map((f) => {
        if (f.id !== id) return f;
        const restored = { ...f };
        delete restored.deletedAt;
        delete restored.deletedBy;
        return restored;
      })
    );
    showToast('success', 'Document restored to the verification desk.');
    logAudit({
      actor: currentUser.name,
      role: currentUser.role,
      action: 'restore_document',
      target: id
    });
  };

  // Plot Locator
  const updatePlotMarker = (id: string, updates: Partial<PlotMarker>) => {
    if (!requirePermission('edit_properties')) return;
    setPlotMarkers((prev) => prev.map((pl) => (pl.id === id ? { ...pl, ...updates } : pl)));
    showToast('success', 'Plot marker details updated.');
  };

  // Media Management with Real Reference Detection
  const addMediaItem = (itemData: Omit<MediaItem, 'id' | 'uploadedAt' | 'usageCount'>) => {
    if (!requirePermission(['manage_media', 'upload_media'])) return;
    const newItem: MediaItem = {
      ...itemData,
      id: `med-${Date.now()}`,
      uploadedAt: new Date().toISOString().split('T')[0],
      usageCount: 0,
      usedInTitles: []
    };
    setMediaItems((prev) => [newItem, ...prev]);
    showToast('success', `File "${newItem.title}" uploaded to ${newItem.folder}.`);
  };

  const deleteMediaItem = (id: string): { success: boolean; message?: string } => {
    if (!requirePermission('manage_media')) return { success: false, message: 'Permission denied.' };
    const item = mediaItems.find((m) => m.id === id);
    const decision = decideMediaDeletion(item, properties, projects, propertyFiles.filter((f) => !f.deletedAt));
    if (!decision.ok) {
      showToast('error', decision.message);
      return { success: false, message: decision.message };
    }

    const today = new Date().toISOString().split('T')[0];
    setMediaItems((prev) => prev.map((m) => (m.id === id ? { ...m, deletedAt: today, deletedBy: currentUser.name } : m)));
    showToast('info', `File "${item?.title ?? 'asset'}" moved to trash.`);
    logAudit({ actor: currentUser.name, role: currentUser.role, action: 'soft_delete_media', target: id });
    return { success: true };
  };

  const restoreMediaItem = (id: string) => {
    if (!requirePermission('manage_media')) return;
    setMediaItems((prev) => prev.map((m) => (m.id === id ? { ...m, deletedAt: undefined, deletedBy: undefined } : m)));
    showToast('success', 'Media asset restored from trash.');
    logAudit({ actor: currentUser.name, role: currentUser.role, action: 'restore_media', target: id });
  };

  /**
   * Mark a media asset (agency file/photo/video/PDF) as SOLD with buyer
   * attribution. Computes profit via the deterministic util, records WHO sold
   * it (name + role), masks the buyer national ID, writes an audit entry, and
   * persists through the workspace sync to MySQL.
   */
  const markMediaSold = (id: string, sale: { soldPricePKR: number; basePricePKR?: number; buyerName: string; buyerPhone: string; buyerNationalId?: string }): { success: boolean; message?: string; profit?: number } => {
    if (!requirePermission(['manage_media'])) return { success: false, message: 'Permission denied.' };
    const item = mediaItems.find((m) => m.id === id);
    if (!item) {
      showToast('error', 'Media asset not found.');
      return { success: false, message: 'Media asset not found.' };
    }
    if (item.soldAt) {
      showToast('error', `"${item.title}" is already sold - the sale record is read-only.`);
      return { success: false, message: 'Already sold.' };
    }
    const buyerName = (sale.buyerName || '').trim();
    const buyerPhone = (sale.buyerPhone || '').trim();
    if (buyerName.length < 2 || buyerPhone.length < 7) {
      const message = 'Buyer name (2+ characters) and a valid phone number are required for sale attribution.';
      showToast('error', message);
      return { success: false, message };
    }
    const economics = calculateMediaSaleProfit(sale.soldPricePKR, sale.basePricePKR);
    if (!economics.ok) {
      showToast('error', economics.error ?? 'Invalid sale amounts.');
      return { success: false, message: economics.error };
    }
    const today = new Date().toISOString().split('T')[0];
    setMediaItems((prev) =>
      prev.map((m) =>
        m.id === id
          ? {
              ...m,
              soldAt: today,
              soldPricePKR: economics.sellingPrice,
              basePricePKR: economics.costPrice,
              profitPKR: economics.profit,
              buyerName,
              buyerPhone,
              buyerNationalIdMasked: sale.buyerNationalId ? maskCNIC(sale.buyerNationalId) : undefined,
              soldBy: currentUser.name,
              soldByRole: currentUser.role,
            }
          : m
      )
    );
    logAudit({
      actor: currentUser.name,
      role: currentUser.role,
      action: 'media_sold',
      target: id,
      detail: `"${item.title}" sold for PKR ${economics.sellingPrice.toLocaleString()} (profit PKR ${economics.profit.toLocaleString()}) to ${buyerName}`,
    });
    showToast('success', `"${item.title}" marked sold - profit PKR ${economics.profit.toLocaleString()} recorded.`);
    return { success: true, profit: economics.profit };
  };

  // Agency Videos Operations (Public Video Walkthroughs & User Engagement)
  const likeAgencyVideo = (videoId: string) => {
    const likedKey = `${STORAGE_KEY_PREFIX}liked_video_${videoId}`;
    const alreadyLiked = localStorage.getItem(likedKey) === 'true';

    setAgencyVideos((prev) =>
      prev.map((v) => {
        if (v.id === videoId) {
          const newLikes = alreadyLiked ? Math.max(0, v.likes - 1) : v.likes + 1;
          return { ...v, likes: newLikes };
        }
        return v;
      })
    );

    if (alreadyLiked) {
      // Un-like: drop the local mirror only; server likes are append-only
      // counters keyed by visitor state, never decremented retroactively.
      localStorage.removeItem(likedKey);
      showToast('info', 'Like removed from video tour.');
      return;
    }

    localStorage.setItem(likedKey, 'true');
    if (backendConnected) {
      api.likeVideo(videoId).then((result) => {
        if (!result?.ok) {
          // Rollback optimistic like on API failure.
          setAgencyVideos((prev) =>
            prev.map((v) =>
              v.id === videoId ? { ...v, likes: Math.max(0, v.likes - 1) } : v
            )
          );
          localStorage.removeItem(likedKey);
          showToast('error', 'Could not like the video. Please try again.');
        }
      }).catch(() => {
        setAgencyVideos((prev) =>
          prev.map((v) =>
            v.id === videoId ? { ...v, likes: Math.max(0, v.likes - 1) } : v
          )
        );
        localStorage.removeItem(likedKey);
        showToast('error', 'Could not like the video. Please try again.');
      });
    } else {
      showToast('success', 'Liked! Thank you for supporting our partner agency video tour.');
    }
  };

  const addAgencyVideoComment = (videoId: string, userName: string, commentText: string) => {
    if (!userName.trim() || !commentText.trim()) {
      showToast('error', 'Please enter your name and comment.');
      return;
    }

    const newComment: VideoComment = {
      id: `comm-${Date.now()}`,
      userName: userName.trim(),
      comment: commentText.trim(),
      createdAt: 'Just now',
      isDealer: false
    };

    setAgencyVideos((prev) =>
      prev.map((v) => {
        if (v.id === videoId) {
          return { ...v, comments: [newComment, ...v.comments] };
        }
        return v;
      })
    );

    if (backendConnected) {
      api.postVideoComment(videoId, userName.trim(), commentText.trim()).then((result) => {
        if (!result) {
          // Rollback optimistic comment on API failure.
          setAgencyVideos((prev) =>
            prev.map((v) =>
              v.id === videoId
                ? { ...v, comments: v.comments.filter((c) => c.id !== newComment.id) }
                : v
            )
          );
          showToast('error', 'Could not post the comment. Please try again.');
        }
      }).catch(() => {
        setAgencyVideos((prev) =>
          prev.map((v) =>
            v.id === videoId
              ? { ...v, comments: v.comments.filter((c) => c.id !== newComment.id) }
              : v
          )
        );
        showToast('error', 'Could not post the comment. Please try again.');
      });
    } else {
      showToast('info', 'Comment saved locally — will sync when backend is available.');
    }
  };

  const addAgencyVideo = (videoData: Omit<AgencyVideo, 'id' | 'likes' | 'comments' | 'views'>): Promise<{ ok: boolean }> => {
    const requirePublishRights = (): boolean => {
      // Mirrors the Laravel backend gate (AuthorizesApi: upload_media|manage_media).
      return requirePermission(['manage_media', 'upload_media']);
    };

    const newVideo: AgencyVideo = {
      ...videoData,
      id: `vid-${Date.now()}`,
      likes: 0,
      comments: [],
      views: 0,
    };

    if (!requirePublishRights()) {
      showToast('error', 'You do not have permission to publish agency video tours. Please sign in with a manager or higher agency account.');
      return Promise.resolve({ ok: false });
    }

    if (backendConnected) {
      return api
        .createAgencyVideo({ ...newVideo } as unknown as Record<string, unknown>)
        .then((created) => {
          setAgencyVideos((prev) => (prev.some((v) => v.id === created.id) ? prev : [created, ...prev]));
          showToast('success', 'New agency video walkthrough added successfully!');
          return { ok: true };
        })
        .catch(() => {
          showToast('error', 'Could not publish the video tour to the media archive. Please try again later.');
          return { ok: false };
        });
    }

    // Offline / API unreachable: never claim success without persistence.
    showToast('error', 'Publishing is currently unavailable. Please keep the agency API running before adding video tours.');
    return Promise.resolve({ ok: false });
  };

  // Inquiries Management
  const addInquiry = async (
    inquiryData: Omit<Inquiry, 'id' | 'submittedAt' | 'status'> & {
      /** Bot protection (G-11): solved challenge fields forwarded verbatim. */
      captchaToken?: string;
      captchaAnswer?: string;
      companyWebsite?: string;
    }
  ): Promise<boolean> => {
    const now = new Date();
    const formattedDate = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')} ${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;

    const { captchaToken: _ct, captchaAnswer: _ca, companyWebsite: _cw, ...leadFields } = inquiryData;
    const newInquiry: Inquiry = {
      ...leadFields,
      id: `inq-${Date.now()}`,
      status: 'Pending',
      submittedAt: formattedDate,
      adminNotes: 'New inquiry received via public portal. Awaiting agent follow-up.'
    };

    if (!backendConnected) {
      showToast('error', 'Our inquiry desk is temporarily unreachable. Please try again or call the advisory desk.');
      return false;
    }

    const result = await api.submitInquiry({
      fullName: inquiryData.fullName,
      email: inquiryData.email,
      phone: inquiryData.phone,
      subject: inquiryData.subject,
      message: inquiryData.message,
      preferredPropertyOrProject: inquiryData.preferredPropertyOrProject,
      budgetRange: inquiryData.budgetRange,
      propertyId: inquiryData.propertyId,
      captchaToken: inquiryData.captchaToken,
      captchaAnswer: inquiryData.captchaAnswer,
      companyWebsite: inquiryData.companyWebsite
    });

    if (!result || result.ok === false) {
      const status = result?.status;
      const code = result?.data?.code;
      const message = code === 'captcha_failed'
        ? 'The security answer was incorrect or expired. A new question has been loaded — please solve it and resend.'
        : status === 429
        ? 'Too many inquiries were sent from this connection. Please wait a minute and try again.'
        : status === 422
          ? 'Please check the name, phone number and message, then send the inquiry again.'
          : 'We could not send your inquiry. Please try again or call the advisory desk.';
      showToast('error', message);
      return false;
    }

    setInquiries((prev) => [newInquiry, ...prev]);
    showToast('success', 'Thank you! Your inquiry has been dispatched to our Taiser Town advisory desk.');
    return true;
  };

  const updateInquiryStatus = (id: string, status: InquiryStatus) => {
    if (!requirePermission(['crm.update_lead', 'crm.delete_lead'])) return;
    setInquiries((prev) =>
      prev.map((i) => (i.id === id ? { ...i, status } : i))
    );
    if (backendConnected) {
      api.updateInquiryStatus(id, status).then((result) => {
        if (!result) {
          // Rollback optimistic status on API failure.
          setInquiries((prev) =>
            prev.map((i) => (i.id === id ? { ...i, status: 'Pending' } : i))
          );
          showToast('error', 'Could not change inquiry status. Please try again.');
        }
      }).catch(() => {
        setInquiries((prev) =>
          prev.map((i) => (i.id === id ? { ...i, status: 'Pending' } : i))
        );
        showToast('error', 'Could not change inquiry status. Please try again.');
      });
    } else {
      showToast('info', 'Status change saved locally — will sync when backend is available.');
    }
  };

  const updateInquiryNotes = (id: string, notes: string) => {
    if (!requirePermission('crm.update_lead')) return;
    setInquiries((prev) =>
      prev.map((i) => (i.id === id ? { ...i, adminNotes: notes } : i))
    );
    if (backendConnected) {
      api.updateInquiryNotes(id, notes).then((result) => {
        if (!result) {
          // Rollback local notes on API failure.
          setInquiries((prev) =>
            prev.map((i) => (i.id === id ? { ...i, adminNotes: '' } : i))
          );
          showToast('error', 'Could not save inquiry notes. Please try again.');
        }
      }).catch(() => {
        showToast('error', 'Could not save inquiry notes. Please try again.');
      });
    } else {
      showToast('info', 'Notes saved locally — will sync when backend is available.');
    }
  };

  const deleteInquiry = (id: string) => {
    if (!requirePermission('crm.delete_lead')) return;
    if (backendConnected) {
      api.deleteInquiry(id).then((result) => {
        if (!result) {
          showToast('error', 'Could not delete the inquiry. Please try again.');
          return;
        }
        setInquiries((prev) => prev.filter((i) => i.id !== id));
        showToast('info', 'Inquiry deleted.');
      }).catch(() => {
        showToast('error', 'Could not delete the inquiry. Please try again.');
      });
    } else {
      showToast('info', 'Inquiry marked for deletion — will sync when backend is available.');
    }
  };

  // CMS Updates
  const updateCMSConfig = (updates: Partial<CMSConfig>) => {
    if (!requirePermission('edit_cms')) return;
    setCmsConfig((prev) => ({ ...prev, ...updates }));
    showToast('info', 'CMS settings updated locally — changes persist only in this session until a backend CMS endpoint is implemented.');
  };

  const resetToDefaultData = async () => {
    const token = getAuthToken();
    if (token) {
      const data = await api.getWorkspace();
      if (!data) {
        showToast('error', 'MySQL is not reachable, so records were not reloaded.');
        return;
      }
      applyServerData(data, true);
      showToast('success', 'Records reloaded from MySQL.');
      return;
    }

    const [catalog, publicProperties] = await Promise.all([api.getCatalog(), api.getPublicProperties()]);
    if (!catalog && publicProperties === null) {
      showToast('error', 'MySQL is not reachable, so records were not reloaded.');
      return;
    }
    applyPublicData(catalog, publicProperties);
    showToast('success', 'Records reloaded from MySQL.');
  };

  return (
    <AppContext.Provider
      value={{
        currentView,
        setCurrentView,
        selectedProjectId,
        setSelectedProjectId,
        selectedPropertyId,
        setSelectedPropertyId,
        adminTab,
        setAdminTab,
        isAdminAuthenticated,
        isSessionLoading,
        refreshSession,
        currentUser,
        authUsers,
        activeRole: currentUser.role,
        verifyPresenterPin,
        switchAuthUser,
        loginAdmin,
        loginWithCredentials,
        registerDealerAccount,
        loginAsDemo,
        logoutAdmin,
        currentTheme,
        setClientThemeId,
        isPresentationMode,
        togglePresentationMode,
        deviceViewport,
        setDeviceViewport,
        projects,
        addProject,
        updateProject,
        deleteProject,
        restoreProject,
        properties,
        addProperty,
        updateProperty,
        deleteProperty,
        restoreProperty,
        sellers,
        addSeller,
        updateSeller,
        deleteSeller,
        restoreSeller,
        buyers,
        addBuyer,
        updateBuyer,
        deleteBuyer,
        restoreBuyer,
        dealers,
        addDealer,
        updateDealer,
        deleteDealer,
        restoreDealer,
        payDealerCommission,
        transactions,
        paymentEntries,
        commissionEntries,
        addTransaction,
        updateTransaction,
        deleteTransaction,
        recordPayment,
        recordRefund,
        voidTransaction,
        propertyFiles,
        addPropertyFile,
        verifyPropertyFile,
        deletePropertyFile,
        restorePropertyFile,
        plotMarkers,
        updatePlotMarker,
        mediaItems,
        addMediaItem,
        deleteMediaItem,
        restoreMediaItem,
        markMediaSold,
        agencyVideos,
        likeAgencyVideo,
        addAgencyVideoComment,
        addAgencyVideo,
        inquiries,
        addInquiry,
        updateInquiryStatus,
        updateInquiryNotes,
        deleteInquiry,
        cmsConfig,
        updateCMSConfig,
        backendConnected,
        toasts,
        showToast,
        removeToast,
        resetToDefaultData,
        pendingConfirm,
        confirmAction,
        resolveConfirm,
      }}
    >
      {children}
    </AppContext.Provider>
  );
};

export const useApp = () => {
  const context = useContext(AppContext);
  if (!context) {
    throw new Error('useApp must be used within an AppProvider');
  }
  return context;
};






