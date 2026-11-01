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
  DocumentVerificationStatus
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
import { APP_MODE, DEMO_PIN, IS_DEMO } from '../config/appMode';
import { calculateMediaUsageCount, checkDuplicateProperty, hasPermission, userHasPermission } from '../utils/logic';

/** Read a collection from localStorage, returning fallback on failure. */
function readStored<T>(key: string, fallback: T[]): T[] {
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
  currentUser: AuthUser;
  authUsers: AuthUser[];
  activeRole: UserRole;
  switchAuthUser: (role: UserRole, pin?: string) => Promise<boolean>;
  loginAsDemo: (role: UserRole, pin?: string) => Promise<boolean>;
  verifyPresenterPin: (pin: string) => boolean;
  loginAdmin: (password: string) => Promise<boolean>;
  loginWithCredentials: (email: string, password: string) => Promise<{ success: boolean; error?: string }>;
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
  
  properties: Property[];
  addProperty: (property: Omit<Property, 'id' | 'createdDate'>) => { success: boolean; error?: string };
  updateProperty: (id: string, updates: Partial<Property>) => { success: boolean; error?: string };
  deleteProperty: (id: string) => void;
  
  // Real Estate ERP Collections
  sellers: Seller[];
  addSeller: (seller: Omit<Seller, 'id' | 'createdAt'>) => void;
  updateSeller: (id: string, updates: Partial<Seller>) => void;
  deleteSeller: (id: string) => void;

  buyers: Buyer[];
  addBuyer: (buyer: Omit<Buyer, 'id' | 'createdAt'>) => void;
  updateBuyer: (id: string, updates: Partial<Buyer>) => void;
  deleteBuyer: (id: string) => void;

  dealers: Dealer[];
  addDealer: (dealer: Omit<Dealer, 'id' | 'createdAt' | 'totalEarnedPKR' | 'pendingCommissionPKR'>) => void;
  updateDealer: (id: string, updates: Partial<Dealer>) => void;
  deleteDealer: (id: string) => void;
  payDealerCommission: (dealerId: string, amount: number, transactionId?: string) => void;

  transactions: Transaction[];
  addTransaction: (transaction: Omit<Transaction, 'id'>) => void;
  updateTransaction: (id: string, updates: Partial<Transaction>) => void;
  deleteTransaction: (id: string) => void;

  propertyFiles: PropertyFileItem[];
  addPropertyFile: (file: Omit<PropertyFileItem, 'id' | 'uploadedAt'>) => void;
  verifyPropertyFile: (id: string, status: DocumentVerificationStatus, notes?: string) => void;
  deletePropertyFile: (id: string) => void;

  plotMarkers: PlotMarker[];
  updatePlotMarker: (id: string, updates: Partial<PlotMarker>) => void;

  // Media Library
  mediaItems: MediaItem[];
  addMediaItem: (item: Omit<MediaItem, 'id' | 'uploadedAt' | 'usageCount'>) => void;
  deleteMediaItem: (id: string) => { success: boolean; message?: string };
  
  // Inquiries & CMS
  inquiries: Inquiry[];
  addInquiry: (inquiry: Omit<Inquiry, 'id' | 'submittedAt' | 'status'>) => void;
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

export const AppProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  // Navigation State
  const [currentView, setCurrentView] = useState<string>('home');
  const [selectedProjectId, setSelectedProjectId] = useState<string | null>(null);
  const [selectedPropertyId, setSelectedPropertyId] = useState<string | null>(null);
  const [adminTab, setAdminTab] = useState<string>('overview');

  // Presentation & Theme State
  const [currentThemeId, setCurrentThemeId] = useState<ClientThemeId>(() => {
    const saved = localStorage.getItem(`${STORAGE_KEY_PREFIX}theme_id`);
    return (saved as ClientThemeId) || 'taisartown-navy';
  });
  // Presentation mode NEVER comes from localStorage — it is an in-memory,
  // demo-only surface so visitors cannot flip it on themselves.
  const [isPresentationMode, setIsPresentationMode] = useState<boolean>(false);
  const [deviceViewport, setDeviceViewport] = useState<DeviceViewport>('desktop');

  // Auth & RBAC State
  // NOTE: Authentication state is NEVER restored from localStorage.
  // A full page reload always requires a fresh (PIN-gated demo, or credential) login.
  const [isAdminAuthenticated, setIsAdminAuthenticated] = useState<boolean>(false);

  // Placeholder until an admin session begins; real profile arrives with login.
  const [currentUser, setCurrentUser] = useState<AuthUser>(() => ({
    id: 'anonymous',
    name: 'Anonymous Visitor',
    email: 'visitor@taisartownhub.com',
    role: 'admin' as UserRole, // display role irrelevant until an admin session begins
    permissions: [],
  }));

  // ---- ERP seed hydration (admin-only, dynamically imported chunk) ----
  // The ERP chunk (sellers/buyers/dealers/transactions/files/inquiries/auth users/
  // demo credentials) is fetched ONLY when an admin session starts. Public builds
  // never trigger it, so its bytes never reach public visitors.
  const erpReadyRef = useRef(false);
  const [erpReady, setErpReady] = useState(false);
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
  const [projects, setProjects] = useState<Project[]>(() => {
    const saved = localStorage.getItem(`${STORAGE_KEY_PREFIX}projects`);
    if (saved) {
      try { return JSON.parse(saved); } catch (e) { /* ignore */ }
    }
    return initialProjects;
  });

  const [properties, setProperties] = useState<Property[]>(() => {
    const saved = localStorage.getItem(`${STORAGE_KEY_PREFIX}properties`);
    if (saved) {
      try { return JSON.parse(saved); } catch (e) { /* ignore */ }
    }
    return initialProperties;
  });

  const [sellers, setSellers] = useState<Seller[]>(() => readStored<Seller>('sellers', []));

  const [buyers, setBuyers] = useState<Buyer[]>(() => readStored<Buyer>('buyers', []));

  const [dealers, setDealers] = useState<Dealer[]>(() => readStored<Dealer>('dealers', []));

  const [transactions, setTransactions] = useState<Transaction[]>(() => readStored<Transaction>('transactions', []));

  const [propertyFiles, setPropertyFiles] = useState<PropertyFileItem[]>(() => readStored<PropertyFileItem>('files', []));

  const [plotMarkers, setPlotMarkers] = useState<PlotMarker[]>(() => {
    const saved = localStorage.getItem(`${STORAGE_KEY_PREFIX}plots`);
    if (saved) {
      try { return JSON.parse(saved); } catch (e) { /* ignore */ }
    }
    return initialPlotMarkers;
  });

  const [mediaItems, setMediaItems] = useState<MediaItem[]>(() => {
    const saved = localStorage.getItem(`${STORAGE_KEY_PREFIX}media`);
    if (saved) {
      try { return JSON.parse(saved); } catch (e) { /* ignore */ }
    }
    return initialMedia;
  });

  const [inquiries, setInquiries] = useState<Inquiry[]>(() => readStored<Inquiry>('inquiries', []));

  const [cmsConfig, setCmsConfig] = useState<CMSConfig>(() => {
    const saved = localStorage.getItem(`${STORAGE_KEY_PREFIX}cms`);
    if (saved) {
      try { return JSON.parse(saved); } catch (e) { /* ignore */ }
    }
    return initialCMSConfig;
  });

  // Toasts
  const [toasts, setToasts] = useState<ToastMessage[]>([]);

  // Local storage synchronization
  // NOTE: no presentation-mode / demo-user keys — auth & presentation state is
  // in-memory only, so a page reload always requires a fresh login.
  useEffect(() => {
    localStorage.setItem(`${STORAGE_KEY_PREFIX}theme_id`, currentThemeId);
  }, [currentThemeId]);

  useEffect(() => {
    localStorage.setItem(`${STORAGE_KEY_PREFIX}projects`, JSON.stringify(projects));
  }, [projects]);

  useEffect(() => {
    localStorage.setItem(`${STORAGE_KEY_PREFIX}properties`, JSON.stringify(properties));
  }, [properties]);

  useEffect(() => {
    // ERP collections only persist once their seed has been loaded, otherwise an
    // initial empty render would allow wiping previously stored admin data.
    if (!erpReady) return;
    localStorage.setItem(`${STORAGE_KEY_PREFIX}sellers`, JSON.stringify(sellers));
  }, [sellers, erpReady]);

  useEffect(() => {
    if (!erpReady) return;
    localStorage.setItem(`${STORAGE_KEY_PREFIX}buyers`, JSON.stringify(buyers));
  }, [buyers, erpReady]);

  useEffect(() => {
    if (!erpReady) return;
    localStorage.setItem(`${STORAGE_KEY_PREFIX}dealers`, JSON.stringify(dealers));
  }, [dealers, erpReady]);

  useEffect(() => {
    if (!erpReady) return;
    localStorage.setItem(`${STORAGE_KEY_PREFIX}files`, JSON.stringify(propertyFiles));
  }, [propertyFiles, erpReady]);

  useEffect(() => {
    if (!erpReady) return;
    localStorage.setItem(`${STORAGE_KEY_PREFIX}transactions`, JSON.stringify(transactions));
  }, [transactions, erpReady]);

  useEffect(() => {
    localStorage.setItem(`${STORAGE_KEY_PREFIX}media`, JSON.stringify(mediaItems));
  }, [mediaItems]);

  useEffect(() => {
    localStorage.setItem(`${STORAGE_KEY_PREFIX}inquiries`, JSON.stringify(inquiries));
  }, [inquiries]);

  useEffect(() => {
    localStorage.setItem(`${STORAGE_KEY_PREFIX}cms`, JSON.stringify(cmsConfig));
  }, [cmsConfig]);

  // Toast Helpers
  const showToast = (type: 'success' | 'error' | 'info', message: string) => {
    const id = Date.now().toString() + Math.random().toString(36).substring(2, 5);
    setToasts((prev) => [...prev, { id, type, message }]);
    setTimeout(() => {
      removeToast(id);
    }, 4500);
  };

  const removeToast = (id: string) => {
    setToasts((prev) => prev.filter((t) => t.id !== id));
  };

  // Auth & RBAC Handlers
  const normalizeRole = (r: UserRole | string) => {
    const base = String(r).toLowerCase().replace(/[\s-]+/g, '_');
    const aliases: Record<string, string> = {
      dealer: 'property_dealer',
      property_dealer: 'property_dealer',
      operator: 'data_entry_operator',
      data_entry_operator: 'data_entry_operator',
      super_admin: 'super_admin',
      admin: 'admin',
      manager: 'manager',
    };
    return aliases[base] || base;
  };

  // Presenter PIN gate (demo builds only). Honest limitation: a client-side PIN
  // is a speed bump against casual snooping, not real access control. Demo
  // deployments must also use Vercel password protection and fictional data only.
  const verifyPresenterPin = (pin: string): boolean => IS_DEMO && String(pin) === DEMO_PIN;

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
    showToast('success', `Demo login: ${user.name} (${user.role}).`);
    return true;
  };

  // Credential login validates against DEMO_ACCOUNTS (fictional) that live only
  // inside the dynamically-imported ERP chunk. In the 'public' build there is no
  // login code at all — the ERP chunk never loads.
  const loginWithCredentials = async (email: string, pass: string): Promise<{ success: boolean; error?: string }> => {
    if (APP_MODE === 'public' || APP_MODE === 'production') {
      return { success: false, error: 'Admin accounts are not configured in this build.' };
    }
    const loaded = await ensureErpLoaded();
    if (!loaded) return { success: false, error: 'Records module unavailable.' };
    if (!pass || pass.trim() === '') {
      showToast('error', 'Password is required.');
      return { success: false, error: 'Password is required.' };
    }
    const erp = await import('../data/erpSeed');
    const cleanEmail = email.trim().toLowerCase();
    const account = erp.DEMO_ACCOUNTS.find((a) => a.email.toLowerCase() === cleanEmail);
    if (!account || pass !== account.password) {
      showToast('error', 'Invalid email or password. Use demo account credentials.');
      return { success: false, error: 'Invalid email or password.' };
    }
    const foundUser =
      erp.initialAuthUsers.find((u) => u.email.toLowerCase() === cleanEmail) ||
      erp.initialAuthUsers[0];
    setCurrentUser(foundUser);
    setIsAdminAuthenticated(true);
    showToast('success', `Welcome back, ${foundUser.name}! Signed in as ${foundUser.role}. (DEMO MODE)`);
    return { success: true };
  };

  const loginAdmin = async (password: string): Promise<boolean> => {
    return (await loginWithCredentials('admin@taisartownhub.com', password)).success;
  };

  const logoutAdmin = () => {
    setIsAdminAuthenticated(false);
    showToast('info', 'Logged out of admin dashboard.');
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
    const newProject: Project = {
      ...projectData,
      id: `proj-${Date.now()}`,
      createdDate: new Date().toISOString().split('T')[0],
    };
    setProjects((prev) => [newProject, ...prev]);
    showToast('success', `Project "${newProject.title}" published successfully!`);
  };

  const updateProject = (id: string, updates: Partial<Project>) => {
    setProjects((prev) =>
      prev.map((p) => (p.id === id ? { ...p, ...updates } : p))
    );
    showToast('success', 'Project details updated.');
  };

  const deleteProject = (id: string) => {
    setProjects((prev) => prev.filter((p) => p.id !== id));
    showToast('info', 'Project removed from system.');
  };

  // Properties CRUD with Duplicate Detection
  const addProperty = (propData: Omit<Property, 'id' | 'createdDate'>): { success: boolean; error?: string } => {
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
    showToast('success', `Property "${newProp.title}" listed successfully!`);
    return { success: true };
  };

  const updateProperty = (id: string, updates: Partial<Property>): { success: boolean; error?: string } => {
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
    setProperties((prev) => prev.filter((p) => p.id !== id));
    showToast('info', 'Property listing removed.');
  };

  // Sellers Management
  const addSeller = (sellerData: Omit<Seller, 'id' | 'createdAt'>) => {
    const newSeller: Seller = {
      ...sellerData,
      id: `sel-${Date.now()}`,
      createdAt: new Date().toISOString().split('T')[0],
    };
    setSellers((prev) => [newSeller, ...prev]);
    showToast('success', `Seller "${newSeller.fullName}" registered.`);
  };

  const updateSeller = (id: string, updates: Partial<Seller>) => {
    setSellers((prev) => prev.map((s) => (s.id === id ? { ...s, ...updates } : s)));
    showToast('success', 'Seller profile updated.');
  };

  const deleteSeller = (id: string) => {
    const referenced = transactions.some((t) => t.sellerId === id);
    if (referenced) {
      showToast('error', 'Cannot delete: seller is referenced by existing transactions. Archive instead.');
      return;
    }
    setSellers((prev) => prev.filter((s) => s.id !== id));
    showToast('info', 'Seller removed.');
  };

  // Buyers Management
  const addBuyer = (buyerData: Omit<Buyer, 'id' | 'createdAt'>) => {
    const newBuyer: Buyer = {
      ...buyerData,
      id: `buy-${Date.now()}`,
      createdAt: new Date().toISOString().split('T')[0],
    };
    setBuyers((prev) => [newBuyer, ...prev]);
    showToast('success', `Buyer "${newBuyer.fullName}" registered.`);
  };

  const updateBuyer = (id: string, updates: Partial<Buyer>) => {
    setBuyers((prev) => prev.map((b) => (b.id === id ? { ...b, ...updates } : b)));
    showToast('success', 'Buyer profile updated.');
  };

  const deleteBuyer = (id: string) => {
    const referenced = transactions.some((t) => t.buyerId === id);
    if (referenced) {
      showToast('error', 'Cannot delete: buyer is referenced by existing transactions. Archive instead.');
      return;
    }
    setBuyers((prev) => prev.filter((b) => b.id !== id));
    showToast('info', 'Buyer removed.');
  };

  // Dealers Management
  const addDealer = (dealerData: Omit<Dealer, 'id' | 'createdAt' | 'totalEarnedPKR' | 'pendingCommissionPKR'>) => {
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
    setDealers((prev) => prev.map((d) => (d.id === id ? { ...d, ...updates } : d)));
    showToast('success', 'Dealer details updated.');
  };

  const deleteDealer = (id: string) => {
    const referenced = transactions.some((t) => t.dealerId === id);
    if (referenced) {
      showToast('error', 'Cannot delete: dealer is referenced by existing transactions.');
      return;
    }
    setDealers((prev) => prev.filter((d) => d.id !== id));
    showToast('info', 'Dealer removed.');
  };

  const payDealerCommission = (dealerId: string, amount: number, transactionId?: string) => {
    // Validation
    if (!Number.isFinite(amount) || amount <= 0) {
      showToast('error', 'Payout amount must be a positive number.');
      return;
    }
    const dealer = dealers.find((d) => d.id === dealerId);
    if (!dealer) {
      showToast('error', 'Dealer not found.');
      return;
    }
    const pending = dealer.pendingCommissionPKR ?? 0;
    if (amount > pending + 1e-9) {
      showToast('error', `Cannot overpay: pending commission is PKR ${pending.toLocaleString()}, requested PKR ${amount.toLocaleString()}.`);
      return;
    }

    // Authorization: only Super Admin / Admin can pay commissions (checked by caller in UI; here we trust context user)
    if (!hasPermission(currentUser.role, 'pay_commissions') && !hasPermission(currentUser.role, 'manage_payments')) {
      showToast('error', 'You are not authorized to pay commissions.');
      return;
    }

    // Duplicate payout guard: if transaction already fully Paid, block.
    if (transactionId) {
      const tx = transactions.find((t) => t.id === transactionId);
      if (tx && tx.commissionStatus === 'Paid') {
        showToast('error', 'Commission for this transaction has already been paid.');
        return;
      }
    }

    setDealers((prev) =>
      prev.map((d) => {
        if (d.id === dealerId) {
          const currentPending = d.pendingCommissionPKR ?? 0;
          const currentPaid = d.totalCommissionPaidPKR ?? 0;
          const newPending = Math.max(0, currentPending - amount);
          const newPaid = currentPaid + amount;
          return { ...d, pendingCommissionPKR: newPending, totalCommissionPaidPKR: newPaid };
        }
        return d;
      })
    );

    if (transactionId) {
      setTransactions((prev) =>
        prev.map((t) => {
          if (t.id !== transactionId) return t;
          const comm = t.commissionAmountPKR ?? t.commissionPKR ?? 0;
          const alreadyPaid = t.commissionPaidPKR ?? 0;
          const newPaid = alreadyPaid + amount;
          const fullyPaid = comm > 0 ? newPaid >= comm - 1e-9 : true;
          return {
            ...t,
            commissionStatus: fullyPaid ? 'Paid' : 'Partially Paid',
            commissionPaidPKR: newPaid,
          };
        })
      );
    }

    showToast('success', `Commission payout of PKR ${amount.toLocaleString()} processed successfully.`);
  };

  // Transactions Management
  const addTransaction = (txData: Omit<Transaction, 'id'>) => {
    // Prevent duplicate active sale transactions per propertyId unless authorized
    const hasActiveTransaction = transactions.some(
      (t) =>
        t.propertyId === txData.propertyId &&
        t.status !== 'Cancelled' &&
        t.status !== 'Draft'
    );
    if (hasActiveTransaction && !hasPermission(currentUser.role, 'approve_transactions')) {
      showToast('error', `An active transaction already exists for property ID "${txData.propertyId}". Complete or cancel it first.`);
      return;
    }

    const newTx: Transaction = {
      ...txData,
      id: `tx-${Date.now().toString().slice(-4)}`,
    };
    setTransactions((prev) => [newTx, ...prev]);

    // If commission pending and dealer assigned, update dealer pending commission
    const commAmount = newTx.commissionAmountPKR ?? newTx.commissionPKR ?? 0;
    if (newTx.dealerId && commAmount > 0 && newTx.commissionStatus === 'Pending') {
      setDealers((prev) =>
        prev.map((d) =>
          d.id === newTx.dealerId
            ? { ...d, pendingCommissionPKR: (d.pendingCommissionPKR ?? 0) + commAmount }
            : d
        )
      );
    }

    // If transaction completed, update property status to 'Sold' or 'Transferred'
    if (newTx.status === 'Completed') {
      setProperties((prev) =>
        prev.map((p) => (p.id === newTx.propertyId ? { ...p, status: 'Sold' } : p))
      );
    } else if (newTx.status === 'Agreement Signed') {
      setProperties((prev) =>
        prev.map((p) => (p.id === newTx.propertyId ? { ...p, status: 'Reserved' } : p))
      );
    }

    showToast('success', `Transaction #${newTx.id} recorded.`);
  };

  const updateTransaction = (id: string, updates: Partial<Transaction>) => {
    const existingTx = transactions.find((t) => t.id === id);
    if (!existingTx) {
      showToast('error', 'Transaction not found.');
      return;
    }

    // Prevent duplicate active sale transactions per propertyId unless authorized.
    const targetPropertyId = updates.propertyId || existingTx.propertyId;
    if (targetPropertyId && targetPropertyId !== existingTx.propertyId) {
      const hasActive = transactions.some(
        (t) =>
          t.id !== id &&
          t.propertyId === targetPropertyId &&
          t.status !== 'Cancelled' &&
          t.status !== 'Draft'
      );
      if (hasActive && !hasPermission(currentUser.role, 'approve_transactions')) {
        showToast('error', `An active transaction already exists for property ID "${targetPropertyId}".`);
        return;
      }
    }

    // 1. Lifecycle transition validation:
    // Draft -> Negotiation -> Agreement Signed -> Payment Pending -> Partially Paid -> Fully Paid -> Completed
    // Cancelled from any
    // Under Transfer from Completed
    if (updates.status && updates.status !== existingTx.status) {
      const from = existingTx.status;
      const to = updates.status;

      const VALID_LIFECYCLE_TRANSITIONS: Record<string, string[]> = {
        'Draft': ['Negotiation', 'Agreement Signed', 'Cancelled'],
        'Negotiation': ['Agreement Signed', 'Draft', 'Cancelled'],
        'Token Paid': ['Agreement Signed', 'Negotiation', 'Cancelled'],
        'Agreement Signed': ['Payment Pending', 'Partially Paid', 'Fully Paid', 'Cancelled'],
        'Payment Pending': ['Partially Paid', 'Fully Paid', 'Completed', 'Cancelled'],
        'Advance Received': ['Partially Paid', 'Fully Paid', 'Completed', 'Cancelled'],
        'Partially Paid': ['Fully Paid', 'Completed', 'Cancelled'],
        'Fully Paid': ['Completed', 'Under Transfer', 'Cancelled'],
        'Completed': ['Under Transfer', 'Cancelled'],
        'Under Transfer': ['Completed', 'Cancelled'],
        'Cancelled': [],
      };

      const allowedNextStates = VALID_LIFECYCLE_TRANSITIONS[from] || [];
      const isAllowed = to === 'Cancelled' || allowedNextStates.includes(to);
      const isPrivileged = hasPermission(currentUser.role, 'approve_transactions');

      if (!isAllowed && !isPrivileged) {
        showToast('error', `Invalid transition from "${from}" to "${to}". Allowed: ${allowedNextStates.join(', ') || 'None (Terminal)'}.`);
        return;
      }

      // On transition to Cancelled:
      // Subtract that transaction's commission from assigned dealer's pendingCommissionPKR (if pending)
      // and set commissionStatus to 'Unpaid'
      if (to === 'Cancelled') {
        const commAmount = existingTx.commissionAmountPKR ?? existingTx.commissionPKR ?? 0;
        if (existingTx.dealerId && commAmount > 0 && existingTx.commissionStatus === 'Pending') {
          setDealers((prev) =>
            prev.map((d) =>
              d.id === existingTx.dealerId
                ? { ...d, pendingCommissionPKR: Math.max(0, (d.pendingCommissionPKR ?? 0) - commAmount) }
                : d
            )
          );
        }
        updates.commissionStatus = 'Cancelled';

        // Revert property status back to Available
        setProperties((props) =>
          props.map((p) => (p.id === existingTx.propertyId ? { ...p, status: 'Available' } : p))
        );
      } else if (to === 'Completed' || to === 'Under Transfer') {
        setProperties((props) =>
          props.map((p) => (p.id === existingTx.propertyId ? { ...p, status: 'Sold' } : p))
        );
      }
    }

    setTransactions((prev) =>
      prev.map((t) => (t.id === id ? { ...t, ...updates } : t))
    );
    showToast('success', 'Transaction updated.');
  };

  const deleteTransaction = (id: string) => {
    setTransactions((prev) => prev.filter((t) => t.id !== id));
    showToast('info', 'Transaction record deleted.');
  };

  // Property Files Management
  const addPropertyFile = (fileData: Omit<PropertyFileItem, 'id' | 'uploadedAt'>) => {
    const newFile: PropertyFileItem = {
      ...fileData,
      id: `doc-${Date.now()}`,
      uploadedAt: new Date().toISOString().split('T')[0],
    };
    setPropertyFiles((prev) => [newFile, ...prev]);
    showToast('success', `File "${newFile.title}" uploaded to verification desk.`);
  };

  const verifyPropertyFile = (id: string, status: DocumentVerificationStatus, notes?: string) => {
    setPropertyFiles((prev) =>
      prev.map((f) =>
        f.id === id
          ? {
              ...f,
              verificationStatus: status,
              verifiedBy: currentUser.name,
              verifiedAt: new Date().toISOString().split('T')[0],
              notes: notes || f.notes,
            }
          : f
      )
    );
    showToast('info', `Document status updated to "${status}".`);
  };

  const deletePropertyFile = (id: string) => {
    setPropertyFiles((prev) => prev.filter((f) => f.id !== id));
    showToast('info', 'Document removed.');
  };

  // Plot Locator
  const updatePlotMarker = (id: string, updates: Partial<PlotMarker>) => {
    setPlotMarkers((prev) => prev.map((pl) => (pl.id === id ? { ...pl, ...updates } : pl)));
    showToast('success', 'Plot marker details updated.');
  };

  // Media Management with Real Reference Detection
  const addMediaItem = (itemData: Omit<MediaItem, 'id' | 'uploadedAt' | 'usageCount'>) => {
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
    const item = mediaItems.find((m) => m.id === id);
    if (!item) return { success: false, message: 'Item not found.' };

    // Dynamic real reference check across active properties, projects, and property files
    const usage = calculateMediaUsageCount(item.url, properties, projects, propertyFiles);
    if (usage.count > 0) {
      const msg = `Cannot delete: File is currently referenced by ${usage.count} active listing(s): ${usage.referencingTitles.slice(0, 3).join(', ')}${usage.count > 3 ? '...' : ''}. Please unlink or replace the media reference first.`;
      showToast('error', msg);
      return { success: false, message: msg };
    }

    setMediaItems((prev) => prev.filter((m) => m.id !== id));
    showToast('info', `File "${item.title}" deleted.`);
    return { success: true };
  };

  // Inquiries Management
  const addInquiry = (inquiryData: Omit<Inquiry, 'id' | 'submittedAt' | 'status'>) => {
    const now = new Date();
    const formattedDate = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')} ${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
    
    const newInquiry: Inquiry = {
      ...inquiryData,
      id: `inq-${Date.now()}`,
      status: 'Pending',
      submittedAt: formattedDate,
      adminNotes: 'New inquiry received via public portal. Awaiting agent follow-up.'
    };
    setInquiries((prev) => [newInquiry, ...prev]);
    showToast('success', 'Thank you! Your inquiry has been dispatched to our Taiser Town advisory desk.');
  };

  const updateInquiryStatus = (id: string, status: InquiryStatus) => {
    setInquiries((prev) =>
      prev.map((i) => (i.id === id ? { ...i, status } : i))
    );
    showToast('info', `Inquiry status changed to "${status}".`);
  };

  const updateInquiryNotes = (id: string, notes: string) => {
    setInquiries((prev) =>
      prev.map((i) => (i.id === id ? { ...i, adminNotes: notes } : i))
    );
    showToast('success', 'Internal agent notes updated.');
  };

  const deleteInquiry = (id: string) => {
    setInquiries((prev) => prev.filter((i) => i.id !== id));
    showToast('info', 'Inquiry deleted.');
  };

  // CMS Updates
  const updateCMSConfig = (updates: Partial<CMSConfig>) => {
    setCmsConfig((prev) => ({ ...prev, ...updates }));
    showToast('success', 'Website CMS settings updated and live!');
  };

  // Reset to default demo data (demo builds only)
  const resetToDefaultData = async () => {
    if (APP_MODE !== 'demo') {
      showToast('error', 'Data reset is a demo-build action.');
      return;
    }
    setCurrentThemeId('taisartown-navy');
    setIsPresentationMode(true);
    const ok = await ensureErpLoaded();
    if (!ok) return;
    const erp = await import('../data/erpSeed');
    // Fresh presentation state: seeds overwrite local copies completely.
    setProjects(initialProjects);
    setProperties(erp.initialProperties);
    setSellers(erp.initialSellers);
    setBuyers(erp.initialBuyers);
    setDealers(erp.initialDealers);
    setTransactions(erp.initialTransactions);
    setPropertyFiles(erp.initialPropertyFiles);
    setInquiries(erp.initialInquiries);
    setPlotMarkers(initialPlotMarkers);
    setMediaItems(initialMedia);
    setCmsConfig(initialCMSConfig);
    setCurrentUser(erp.initialAuthUsers[0]);
    setIsAdminAuthenticated(true);
    setErpReady(true);
    erpReadyRef.current = true;
    setAuthUsers(erp.initialAuthUsers);
    showToast('success', 'All ERP records, plots, transactions, and CMS settings restored to default state.');
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
        currentUser,
        authUsers,
        activeRole: currentUser.role,
        verifyPresenterPin,
        switchAuthUser,
        loginAdmin,
        loginWithCredentials,
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
        properties,
        addProperty,
        updateProperty,
        deleteProperty,
        sellers,
        addSeller,
        updateSeller,
        deleteSeller,
        buyers,
        addBuyer,
        updateBuyer,
        deleteBuyer,
        dealers,
        addDealer,
        updateDealer,
        deleteDealer,
        payDealerCommission,
        transactions,
        addTransaction,
        updateTransaction,
        deleteTransaction,
        propertyFiles,
        addPropertyFile,
        verifyPropertyFile,
        deletePropertyFile,
        plotMarkers,
        updatePlotMarker,
        mediaItems,
        addMediaItem,
        deleteMediaItem,
        inquiries,
        addInquiry,
        updateInquiryStatus,
        updateInquiryNotes,
        deleteInquiry,
        cmsConfig,
        updateCMSConfig,
        toasts,
        showToast,
        removeToast,
        resetToDefaultData,
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
