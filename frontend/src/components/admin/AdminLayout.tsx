import React, { useState, useEffect, Suspense, lazy } from 'react';
import { useApp } from '../../context/AppContext';
import { hasAnyPermission } from '../../utils/logic';
import { normalizeRole } from '../../domain/permissions';
import { IS_DEMO } from '../../config/appMode';
import {
  Building2,
  Home,
  Image,
  Inbox,
  Settings,
  LogOut,
  ExternalLink,
  ShieldCheck,
  LayoutDashboard,
  Sparkles,
  Layers,
  ChevronRight,
  MapPin,
  FileText,
  Users,
  UserCheck,
  Briefcase,
  FileCheck,
  Shield,
  Menu,
  X
} from 'lucide-react';

const AdminOverview = lazy(() => import('./AdminOverview').then((m) => ({ default: m.AdminOverview })));
const AdminProjects = lazy(() => import('./AdminProjects').then((m) => ({ default: m.AdminProjects })));
const AdminProperties = lazy(() => import('./AdminProperties').then((m) => ({ default: m.AdminProperties })));
const AdminMediaLibrary = lazy(() => import('./AdminMediaLibrary').then((m) => ({ default: m.AdminMediaLibrary })));
const AdminInquiries = lazy(() => import('./AdminInquiries').then((m) => ({ default: m.AdminInquiries })));
const AdminCMS = lazy(() => import('./AdminCMS').then((m) => ({ default: m.AdminCMS })));
const AdminSellers = lazy(() => import('./AdminSellers').then((m) => ({ default: m.AdminSellers })));
const AdminBuyers = lazy(() => import('./AdminBuyers').then((m) => ({ default: m.AdminBuyers })));
const AdminDealers = lazy(() => import('./AdminDealers').then((m) => ({ default: m.AdminDealers })));
const AdminTransactions = lazy(() => import('./AdminTransactions').then((m) => ({ default: m.AdminTransactions })));
const AdminFileManagement = lazy(() => import('./AdminFileManagement').then((m) => ({ default: m.AdminFileManagement })));
const AdminPlotLocator = lazy(() => import('./AdminPlotLocator').then((m) => ({ default: m.AdminPlotLocator })));
const AdminSecurityRoles = lazy(() => import('./AdminSecurityRoles').then((m) => ({ default: m.AdminSecurityRoles })));
const AdminAudit = lazy(() => import('./AdminAudit').then((m) => ({ default: m.AdminAudit })));

export const AdminLayout: React.FC = () => {
  const {
    adminTab,
    setAdminTab,
    setCurrentView,
    logoutAdmin,
    currentTheme,
    inquiries,
    properties,
    projects,
    sellers,
    buyers,
    dealers,
    transactions,
    propertyFiles,
    activeRole,
    currentUser,
    isPresentationMode,
    backendConnected
  } = useApp();

  const [mobileSidebarOpen, setMobileSidebarOpen] = useState(false);

  // Dealer accounts only ever see deals booked under their own dealer profile,
  // so their badge must count the scoped set — never the full ledger size.
  const isDealerViewer = normalizeRole(currentUser.role) === 'dealer';
  const scopedTransactions = isDealerViewer
    ? transactions.filter((t) => t.dealerId && t.dealerId === currentUser.dealerId)
    : transactions;

  const pendingInquiriesCount = inquiries.filter((i) => i.status === 'Pending').length;
  const pendingFilesCount = propertyFiles.filter((f) => f.verificationStatus === 'Pending Verification').length;

  const sidebarLinks: {
    id: string;
    label: string;
    icon: any;
    badge?: number;
    alertBadge?: number;
    permission?: string;
    /** Alternate grant: tab is visible when EITHER permission OR altPermission is held. */
    altPermission?: string;
  }[] = [
    { id: 'overview', label: 'Dashboard Overview', icon: LayoutDashboard, permission: 'view_reports' },
    { id: 'properties', label: 'Property Listings', icon: Home, badge: properties.length, permission: 'view_properties' },
    { id: 'plot_locator', label: 'Plot Map & Locator', icon: MapPin, permission: 'view_properties' },
    // Dealers hold view_my_deals (scoped-equivalent of view_transactions): they see the tab,
    // but AdminTransactions only renders deals booked under their dealer profile.
    { id: 'transactions', label: 'Deals & Transactions', icon: FileText, badge: scopedTransactions.length, permission: 'view_transactions', altPermission: 'view_my_deals' },
    { id: 'sellers', label: 'Sellers & Owners', icon: Users, badge: sellers.length, permission: 'manage_sellers' },
    { id: 'buyers', label: 'Buyers CRM', icon: UserCheck, badge: buyers.length, permission: 'manage_buyers' },
    { id: 'dealers', label: 'Dealers & Commission', icon: Briefcase, badge: dealers.length, permission: 'manage_dealers' },
    // File desk: verifiers see the full desk; uploaders (operator) still need access to the pending-queue upload path.
    { id: 'file_management', label: 'File Verification', icon: FileCheck, alertBadge: pendingFilesCount > 0 ? pendingFilesCount : undefined, permission: 'upload_documents', altPermission: 'verify_documents' },
    { id: 'projects', label: 'Projects Management', icon: Building2, badge: projects.length, permission: 'manage_projects' },
    { id: 'media', label: 'Media Library', icon: Image, permission: 'manage_media' },
    { id: 'inquiries', label: 'Inquiries CRM', icon: Inbox, alertBadge: pendingInquiriesCount > 0 ? pendingInquiriesCount : undefined, permission: 'view_inquiries' },
    { id: 'cms', label: 'Website CMS & Builder', icon: Settings, permission: 'edit_cms' },
    { id: 'security', label: 'Security & Roles', icon: Shield, permission: 'manage_users' },
    { id: 'audit', label: 'Audit & Recycle Bin', icon: Shield, permission: 'manage_users' },
  ];

  // Sidebar visibility is enforced here AND in context actions (requirePermission) —
  // this filter is cosmetic; the domain gate is the real enforcement.
  const accessibleSidebarLinks = sidebarLinks.filter((l) => {
    if (!l.permission) return true;
    const wanted = l.altPermission ? [l.permission, l.altPermission] : [l.permission];
    return hasAnyPermission(activeRole, wanted);
  });

  useEffect(() => {
    const isCurrentTabAccessible = accessibleSidebarLinks.some((l) => l.id === adminTab);
    if (!isCurrentTabAccessible && accessibleSidebarLinks.length > 0) {
      setAdminTab(accessibleSidebarLinks[0].id);
    }
  }, [activeRole, adminTab, accessibleSidebarLinks, setAdminTab]);

  const handleSelectTab = (tabId: string) => {
    setAdminTab(tabId);
    setMobileSidebarOpen(false);
  };

  return (
    <div className="min-h-screen bg-[#070B14] flex flex-col md:flex-row text-slate-100">
      {/* Mobile Top App Bar */}
      <div className="md:hidden bg-[#101C2C] border-b border-[#C5A46D]/20 px-4 py-3 flex items-center justify-between sticky top-0 z-40 shadow-lg">
        <div className="flex items-center gap-2.5">
          <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-[#C5A46D] to-[#8C6D37] flex items-center justify-center border border-[#F9E7BA]/40 shadow shrink-0">
            <Building2 className="w-4 h-4 text-[#101C2C]" />
          </div>
          <div>
            <span className="font-serif-luxury font-bold text-xs text-white block">
              {currentTheme.agencyName}
            </span>
            <span className="text-[9px] text-[#C5A46D] font-semibold uppercase tracking-wider block">
              Real Estate ERP
            </span>
          </div>
        </div>

        <div className="flex items-center gap-2">
          <button
            onClick={() => setMobileSidebarOpen(!mobileSidebarOpen)}
            className="p-2 rounded-lg bg-slate-800 text-slate-300 hover:text-white border border-slate-700 cursor-pointer"
            aria-label="Toggle Navigation Menu"
          >
            {mobileSidebarOpen ? <X className="w-5 h-5" /> : <Menu className="w-5 h-5" />}
          </button>
        </div>
      </div>

      {/* Sidebar (Desktop Permanent + Mobile Drawer Overlay) */}
      <aside
        className={`fixed md:sticky top-0 left-0 z-50 md:z-30 h-screen w-72 md:w-64 bg-[#101C2C] border-r border-[#C5A46D]/20 flex flex-col justify-between shrink-0 transition-transform duration-300 ease-in-out ${
          mobileSidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'
        }`}
      >
        <div className="overflow-y-auto max-h-[calc(100vh-140px)]">
          {/* Brand header */}
          <div className="p-5 border-b border-slate-800 flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-[#C5A46D] to-[#8C6D37] flex items-center justify-center shadow-lg border border-[#F9E7BA]/40 shrink-0">
                <Building2 className="w-5 h-5 text-[#101C2C]" />
              </div>
              <div className="min-w-0">
                <span className="font-serif-luxury font-bold text-sm text-white block truncate">
                  {currentTheme.agencyName}
                </span>
                <span className="text-[10px] text-[#C5A46D] font-semibold uppercase tracking-wider block">
                  Real Estate ERP
                </span>
              </div>
            </div>

            <button
              onClick={() => setMobileSidebarOpen(false)}
              className="md:hidden text-slate-400 hover:text-white p-1"
            >
              <X className="w-5 h-5" />
            </button>
          </div>

          {/* Active Role indicator in Sidebar */}
          <div className="px-4 pt-3 pb-1">
            <div className="px-3 py-1.5 rounded-lg bg-[#0B1220] border border-slate-800 flex items-center justify-between text-[11px]">
              <span className="text-slate-400">Role:</span>
              <span className="font-bold text-[#C5A46D] capitalize">{activeRole.replace('_', ' ')}</span>
            </div>
          </div>

          {/* Nav items */}
          <nav className="p-3 space-y-1">
            {accessibleSidebarLinks.map((item) => {
              const Icon = item.icon;
              const isActive = adminTab === item.id;

              return (
                <button
                  key={item.id}
                  onClick={() => handleSelectTab(item.id)}
                  className={`w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold transition-all cursor-pointer ${
                    isActive
                      ? 'bg-[#C5A46D] text-[#101C2C] shadow-lg shadow-[#C5A46D]/20 font-bold'
                      : 'text-slate-300 hover:text-white hover:bg-slate-800/60'
                  }`}
                >
                  <div className="flex items-center gap-2.5 truncate">
                    <Icon className="w-4 h-4 shrink-0" />
                    <span className="truncate">{item.label}</span>
                  </div>

                  {item.alertBadge !== undefined && (
                    <span className="px-1.5 py-0.5 rounded-full text-[9px] font-bold bg-[#C5A46D] text-[#101C2C] shrink-0">
                      {item.alertBadge}
                    </span>
                  )}
                  {item.badge !== undefined && item.alertBadge === undefined && (
                    <span className={`px-1.5 py-0.5 rounded text-[10px] shrink-0 ${
                      isActive ? 'bg-[#101C2C]/20 text-[#101C2C]' : 'bg-slate-800 text-slate-400'
                    }`}>
                      {item.badge}
                    </span>
                  )}
                </button>
              );
            })}
          </nav>
        </div>

        {/* Sidebar Footer Controls */}
        <div className="p-3 border-t border-slate-800 space-y-1.5 shrink-0 bg-[#0E1724]">
          {/* View Public Site */}
          <button
            onClick={() => setCurrentView('home')}
            className="w-full flex items-center justify-center gap-2 px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-medium transition-colors cursor-pointer"
          >
            <ExternalLink className="w-3.5 h-3.5 text-[#C5A46D]" />
            <span>View Public Website</span>
          </button>

          {/* Logout */}
          <button
            onClick={logoutAdmin}
            className="w-full flex items-center justify-center gap-2 px-3 py-1.5 rounded-xl text-xs font-medium text-slate-400 hover:text-white hover:bg-slate-800 transition-colors cursor-pointer"
          >
            <LogOut className="w-3.5 h-3.5" />
            <span>Sign Out</span>
          </button>
        </div>
      </aside>

      {/* Backdrop for mobile drawer */}
      {mobileSidebarOpen && (
        <div
          onClick={() => setMobileSidebarOpen(false)}
          className="fixed inset-0 z-40 bg-black/70 backdrop-blur-sm md:hidden animate-fadeIn"
        />
      )}

      {/* Main Workspace Area */}
      <main className="flex-1 flex flex-col min-w-0 overflow-y-auto">
        {/* Top Header */}
        <header className="sticky top-0 z-30 bg-[#101C2C]/90 backdrop-blur-md border-b border-slate-800 px-6 py-4 flex items-center justify-between">
          <div className="flex items-center gap-2 text-xs text-slate-400">
            <span>Admin</span>
            <ChevronRight className="w-3.5 h-3.5" />
            <span className="text-white font-semibold capitalize">
              {sidebarLinks.find((s) => s.id === adminTab)?.label || 'Overview'}
            </span>
          </div>

          <div className="flex items-center gap-3">
            {backendConnected && (
              <span className="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-950/70 text-emerald-400 border border-emerald-500/30 shadow-sm" title="Connected to Laravel 12 API + MySQL Database">
                <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse" />
                <span>MySQL Live</span>
              </span>
            )}

            {(isPresentationMode || IS_DEMO) && (
              <span className="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-[#C5A46D]/15 text-[#F9E7BA] border border-[#C5A46D]/40 uppercase tracking-wider flex items-center gap-1 shadow-sm">
                <Sparkles className="w-3 h-3 text-[#C5A46D]" />
                DEMO MODE
              </span>
            )}

            <div className="hidden sm:flex items-center gap-2 text-xs text-slate-300">
              <span className="w-2 h-2 rounded-full bg-[#C5A46D]" />
              <span>Active Role: <strong className="text-[#C5A46D] capitalize">{activeRole.replace('_', ' ')}</strong></span>
            </div>
            <button
              onClick={() => setCurrentView('home')}
              className="text-xs text-[#C5A46D] hover:underline flex items-center gap-1 cursor-pointer"
            >
              <span>Exit to Live Site</span>
              <ExternalLink className="w-3 h-3" />
            </button>
          </div>
        </header>

        {/* Tab Content */}
        <div className="p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto">
          <Suspense fallback={<div className="min-h-[40vh] flex items-center justify-center text-slate-400">Loading module...</div>}>
            {adminTab === 'overview' && <AdminOverview />}
            {adminTab === 'projects' && <AdminProjects />}
            {adminTab === 'properties' && <AdminProperties />}
            {adminTab === 'plot_locator' && <AdminPlotLocator />}
            {adminTab === 'transactions' && <AdminTransactions />}
            {adminTab === 'sellers' && <AdminSellers />}
            {adminTab === 'buyers' && <AdminBuyers />}
            {adminTab === 'dealers' && <AdminDealers />}
            {adminTab === 'file_management' && <AdminFileManagement />}
            {adminTab === 'media' && <AdminMediaLibrary />}
            {adminTab === 'inquiries' && <AdminInquiries />}
            {adminTab === 'cms' && <AdminCMS />}
            {adminTab === 'security' && <AdminSecurityRoles />}
            {adminTab === 'audit' && <AdminAudit />}
          </Suspense>
        </div>
      </main>
    </div>
  );
};
