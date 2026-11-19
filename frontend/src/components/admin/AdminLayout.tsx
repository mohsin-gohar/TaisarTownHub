import React, { useState, useEffect, Suspense, lazy } from 'react';
import { useApp } from '../../context/AppContext';
import { api, getApiAgencyId, setApiAgencyId } from '../../services/api';
import { hasAnyPermission } from '../../utils/logic';
import { normalizeRole } from '../../domain/permissions';
import { AdminModuleSkeleton } from './AdminModuleSkeleton';
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
  X,
  LifeBuoy
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
const AdminAgencyDashboard = lazy(() => import('./AdminAgencyDashboard').then((m) => ({ default: m.AdminAgencyDashboard })));
const AdminAgencyApprovals = lazy(() => import('./AdminAgencyApprovals').then((m) => ({ default: m.AdminAgencyApprovals })));
const AdminStaffManager = lazy(() => import('./AdminStaffManager').then((m) => ({ default: m.AdminStaffManager })));
const AdminDocumentVault = lazy(() => import('./AdminDocumentVault').then((m) => ({ default: m.AdminDocumentVault })));
const AdminModerationDesk = lazy(() => import('./AdminModerationDesk').then((m) => ({ default: m.AdminModerationDesk })));
const AdminCrmPipeline = lazy(() => import('./AdminCrmPipeline').then((m) => ({ default: m.AdminCrmPipeline })));
const AdminNotifications = lazy(() => import('./AdminNotifications').then((m) => ({ default: m.AdminNotifications })));
const AdminSupportDesk = lazy(() => import('./AdminSupportDesk').then((m) => ({ default: m.AdminSupportDesk })));
const AdminSubscription = lazy(() => import('./AdminSubscription').then((m) => ({ default: m.AdminSubscription })));
const AdminLocations = lazy(() => import('./AdminLocations').then((m) => ({ default: m.AdminLocations })));
const AdminMediaStudio = lazy(() => import('./AdminMediaStudio').then((m) => ({ default: m.AdminMediaStudio })));

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
    currentUser
  } = useApp();

  const [mobileSidebarOpen, setMobileSidebarOpen] = useState(false);
  const [enterprisePermissions, setEnterprisePermissions] = useState<string[]>([]);
  const [agencyMemberships, setAgencyMemberships] = useState<any[]>([]);
  const [platformAgencies, setPlatformAgencies] = useState<any[]>([]);
  const [agencyContextId, setAgencyContextId] = useState(() => getApiAgencyId() ?? '');
  // Enterprise capability keys arrive asynchronously from /auth/me, so the
  // tab-accessibility guard must wait for that round-trip. Without this flag a
  // hard reload of an enterprise deep link (e.g. /admin/staff) resolves the
  // guard against the empty default context and silently bounces the operator
  // to Dashboard Overview.
  const [enterpriseContextReady, setEnterpriseContextReady] = useState(false);

  // The legacy auth payload only carries matrix permissions; enterprise
  // capability keys and agency membership context come from /auth/me. This
  // fetch is cosmetic for the sidebar — every screen still has server-side
  // enforcement on each request.
  useEffect(() => {
    let active = true;
    void api.getMe().then(async (me) => {
      if (!active || !me) return;
      const permissions: string[] = Array.isArray(me.enterprise_permissions) ? me.enterprise_permissions : [];
      const memberships: any[] = Array.isArray(me.agencies) ? me.agencies : [];
      setEnterprisePermissions(permissions);
      setAgencyMemberships(memberships);

      // Platform operators with agency-management rights may enter any
      // agency's workspace. The server only honours X-Agency-Id for
      // cross-agency admins, so members can never use this to escape scope.
      if (memberships.length === 0 && permissions.includes('admin.manage_agencies')) {
        const agenciesBody = await api.getAdminAgencies();
        if (active) {
          setPlatformAgencies(Array.isArray(agenciesBody?.agencies) ? agenciesBody.agencies : []);
        }
      }
    }).finally(() => {
      // Resolve the guard even when /auth/me fails, so the base role matrix
      // still applies and no operator is left stranded on an empty module.
      if (active) setEnterpriseContextReady(true);
    });
    return () => { active = false; };
  }, []);

  const hasEnterprise = (permission: string) => enterprisePermissions.includes(permission);
  const canManageAgencies = hasEnterprise('admin.manage_agencies');
  const hasAgencyContext = agencyMemberships.length > 0 || Boolean(agencyContextId);
  const canApproveListings = hasEnterprise('properties.approve');
  const canManageStaff = hasEnterprise('agency.manage_staff');
  const canUploadDocuments = hasEnterprise('documents.upload');
  const canDeleteDocuments = hasEnterprise('documents.delete');
  const canAssignLeads = hasEnterprise('crm.assign_lead');
  const canEditLeads = hasEnterprise('crm.update_lead') || hasEnterprise('crm.manage_lead');
  const canCreateLeads = hasEnterprise('crm.create_lead');
  const canUploadMedia = hasEnterprise('upload_media') || hasEnterprise('manage_media');
  const canManageSupport = hasEnterprise('admin.support_tickets');
  const agencyOptions = agencyMemberships.length > 0
    ? agencyMemberships.map((membership) => ({
        id: String(membership.agency_id ?? membership.id ?? ''),
        name: membership.agency?.name || membership.agency_name || membership.name || 'My agency',
      })).filter((option) => option.id)
    : platformAgencies.map((agency) => ({ id: String(agency.id), name: agency.name }));

  const handleAgencyContext = (id: string) => {
    setAgencyContextId(id);
    setApiAgencyId(id || null);
    if (id) setAdminTab('agency_dashboard');
  };

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
    ...(canManageAgencies
      ? [{ id: 'agency_approvals', label: 'Agency approvals', icon: ShieldCheck }]
      : []),
    ...(hasAgencyContext && hasEnterprise('agency.view')
      ? [
          { id: 'agency_dashboard', label: 'Agency Dashboard', icon: Building2 },
          ...(hasEnterprise('agency.manage_staff') || hasEnterprise('staff.view')
            ? [{ id: 'staff', label: 'Staff & Roles', icon: Users }]
            : []),
          { id: 'documents', label: 'Document Vault', icon: FileText },
          ...(hasEnterprise('crm.view')
            ? [{ id: 'crm', label: 'CRM Pipeline', icon: Inbox }]
            : []),
          { id: 'media_studio', label: 'Media Studio', icon: Image },
          { id: 'subscription', label: 'Subscription & Usage', icon: Settings },
        ]
      : []),
    ...(canApproveListings
      ? [
          { id: 'moderation', label: 'Moderation Desk', icon: FileCheck },
          { id: 'locations', label: 'Locations & Categories', icon: MapPin },
        ]
      : []),
    { id: 'notifications', label: 'Notifications', icon: Inbox },
    { id: 'support', label: 'Support Center', icon: LifeBuoy },
  ];

  // Sidebar visibility is enforced here AND in context actions (requirePermission) —
  // this filter is cosmetic; the domain gate is the real enforcement.
  const accessibleSidebarLinks = sidebarLinks.filter((l) => {
    if (!l.permission) return true;
    const wanted = l.altPermission ? [l.permission, l.altPermission] : [l.permission];
    return hasAnyPermission(activeRole, wanted);
  });

  useEffect(() => {
    if (!enterpriseContextReady) return;
    const isCurrentTabAccessible = accessibleSidebarLinks.some((l) => l.id === adminTab);
    if (!isCurrentTabAccessible && accessibleSidebarLinks.length > 0) {
      setAdminTab(accessibleSidebarLinks[0].id);
    }
  }, [activeRole, adminTab, accessibleSidebarLinks, enterpriseContextReady, setAdminTab]);

  useEffect(() => {
    const previousTitle = document.title;
    document.title = 'Admin ERP — TaisarTownHub';
    return () => {
      document.title = previousTitle;
    };
  }, []);

  const handleSelectTab = (tabId: string) => {
    setAdminTab(tabId);
    setMobileSidebarOpen(false);
  };

  return (
    <div className="min-h-screen bg-[#070B14] flex flex-col md:flex-row text-slate-100">
      {/* Mobile Top App Bar */}
      <div className="md:hidden bg-[#0B1220] border-b border-[#E7C878]/20 px-4 py-3 flex items-center justify-between sticky top-0 z-40 shadow-lg">
        <div className="flex items-center gap-2.5">
          <div className="w-8 h-8 rounded-lg bg-white/5 flex items-center justify-center border border-[#F6E3B4]/40 shadow shrink-0 overflow-hidden">
            <img src="/logo.jpg" alt="Logo" className="w-full h-full object-contain" />
          </div>
          <div>
            <span className="font-serif-luxury font-bold text-xs text-white block">
              {currentTheme.agencyName}
            </span>
            <span className="text-[9px] text-[#E7C878] font-semibold uppercase tracking-wider block">
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
        className={`fixed md:sticky top-0 left-0 z-50 md:z-30 h-screen w-72 md:w-64 bg-[#0B1220] border-r border-[#E7C878]/20 flex flex-col justify-between shrink-0 transition-transform duration-300 ease-in-out ${
          mobileSidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'
        }`}
      >
        <div className="flex-1 min-h-0 overflow-y-auto overscroll-contain sidebar-scroll">
          {/* Brand header */}
          <div className="p-5 border-b border-slate-800 flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-lg bg-white/5 flex items-center justify-center shadow-lg border border-[#F6E3B4]/40 shrink-0 overflow-hidden">
                <img src="/logo.jpg" alt="Logo" className="w-full h-full object-contain" />
              </div>
              <div className="min-w-0">
                <span className="font-serif-luxury font-bold text-sm text-white block truncate">
                  {currentTheme.agencyName}
                </span>
                <span className="text-[10px] text-[#E7C878] font-semibold uppercase tracking-wider block">
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
            <div className="px-3 py-1.5 rounded-lg bg-[#07141E] border border-slate-800 flex items-center justify-between text-[11px]">
              <span className="text-slate-400">Role:</span>
              <span className="font-bold text-[#E7C878] capitalize">{activeRole.replace('_', ' ')}</span>
            </div>
          </div>

          {/* Agency context: members switch between their memberships; platform
              operators with admin.manage_agencies may enter any agency. */}
          {agencyOptions.length > 0 && (
            <div className="px-4 pt-2 pb-1">
              <label className="block text-[9px] uppercase tracking-wider text-slate-500 font-semibold mb-1">
                Agency context
              </label>
              <select
                value={agencyContextId}
                onChange={(e) => handleAgencyContext(e.target.value)}
                className="w-full px-3 py-1.5 rounded-lg bg-[#07141E] border border-slate-800 text-[11px] text-white focus:outline-none focus:border-[#E7C878]"
              >
                {agencyMemberships.length === 0 && <option value="">Platform view</option>}
                {agencyOptions.map((option) => (
                  <option key={option.id} value={option.id}>{option.name}</option>
                ))}
              </select>
            </div>
          )}

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
                      ? 'bg-[#E7C878] text-[#0B1220] shadow-lg shadow-[#E7C878]/20 font-bold'
                      : 'text-slate-300 hover:text-white hover:bg-slate-800/60'
                  }`}
                >
                  <div className="flex items-center gap-2.5 truncate">
                    <Icon className="w-4 h-4 shrink-0" />
                    <span className="truncate">{item.label}</span>
                  </div>

                  {item.alertBadge !== undefined && (
                    <span className="px-1.5 py-0.5 rounded-full text-[9px] font-bold bg-[#E7C878] text-[#0B1220] shrink-0">
                      {item.alertBadge}
                    </span>
                  )}
                  {item.badge !== undefined && item.alertBadge === undefined && (
                    <span className={`px-1.5 py-0.5 rounded text-[10px] shrink-0 ${
                      isActive ? 'bg-[#0B1220]/20 text-[#0B1220]' : 'bg-slate-800 text-slate-400'
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
            <ExternalLink className="w-3.5 h-3.5 text-[#E7C878]" />
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
        <header className="sticky top-0 z-30 bg-[#0B1220]/90 backdrop-blur-md border-b border-slate-800 px-4 sm:px-6 py-4 flex items-center justify-between gap-3">
          <div className="flex items-center gap-2 text-xs text-slate-400 min-w-0">
            <span className="hidden sm:inline">Admin</span>
            <ChevronRight className="w-3.5 h-3.5 hidden sm:inline" />
            <span className="text-white font-semibold capitalize truncate">
              {sidebarLinks.find((s) => s.id === adminTab)?.label || 'Overview'}
            </span>
          </div>

          <div className="flex items-center gap-3 shrink-0">
            <div className="hidden sm:flex items-center gap-2 text-xs text-slate-300">
              <span className="w-2 h-2 rounded-full bg-[#E7C878]" />
              <span>Active Role: <strong className="text-[#E7C878] capitalize">{activeRole.replace('_', ' ')}</strong></span>
            </div>
            <button
              onClick={() => setCurrentView('home')}
              aria-label="View public website"
              className="text-xs text-[#E7C878] hover:underline flex items-center gap-1 cursor-pointer"
            >
              <span className="hidden sm:inline">View Public Site</span>
              <ExternalLink className="w-3 h-3" />
            </button>
          </div>
        </header>

        {/* Tab Content */}
        <div className="p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto">
          <Suspense key={agencyContextId || 'platform'} fallback={<AdminModuleSkeleton />}>
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
            {adminTab === 'agency_approvals' && canManageAgencies && <AdminAgencyApprovals />}
            {adminTab === 'agency_dashboard' && <AdminAgencyDashboard onNavigate={handleSelectTab} />}
            {adminTab === 'staff' && <AdminStaffManager canManage={canManageStaff} />}
            {adminTab === 'documents' && (
              <AdminDocumentVault canUpload={canUploadDocuments} canDelete={canDeleteDocuments} />
            )}
            {adminTab === 'moderation' && <AdminModerationDesk canModerate={canApproveListings} />}
            {adminTab === 'crm' && (
              <AdminCrmPipeline canAssign={canAssignLeads} canEdit={canEditLeads} canCreate={canCreateLeads} />
            )}
            {adminTab === 'media_studio' && <AdminMediaStudio canUpload={canUploadMedia} />}
            {adminTab === 'subscription' && <AdminSubscription />}
            {adminTab === 'locations' && <AdminLocations canManage={canApproveListings} />}
            {adminTab === 'notifications' && <AdminNotifications />}
            {adminTab === 'support' && <AdminSupportDesk canManage={canManageSupport} />}
          </Suspense>
        </div>
      </main>
    </div>
  );
};
