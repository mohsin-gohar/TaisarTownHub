import React, { useState, useEffect } from 'react';
import { useApp } from '../../context/AppContext';
import {
  Building2,
  LayoutDashboard,
  Menu,
  X,
  MessageCircle,
  Phone,
  ChevronRight
} from 'lucide-react';
import { generateWhatsAppLink } from '../../utils/logic';

export const Navbar: React.FC = () => {
  const {
    currentView,
    setCurrentView,
    setSelectedProjectId,
    setSelectedPropertyId,
    currentTheme,
    isAdminAuthenticated,
    cmsConfig,
  } = useApp();

  const [drawerOpen, setDrawerOpen] = useState(false);
  const [scrolled, setScrolled] = useState(false);

  // Scroll-aware navbar: denser glass + shadow after the user scrolls.
  // NOTE: height stays constant (no layout jump) — only surface changes.
  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 24);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
    return () => window.removeEventListener('scroll', onScroll);
  }, []);

  // Body scroll lock while the drawer is open
  useEffect(() => {
    if (!drawerOpen) return;
    const prevOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setDrawerOpen(false);
    };
    window.addEventListener('keydown', onKey);
    return () => {
      document.body.style.overflow = prevOverflow;
      window.removeEventListener('keydown', onKey);
    };
  }, [drawerOpen]);

  // Close the drawer if the viewport grows into desktop territory
  useEffect(() => {
    const mq = window.matchMedia('(min-width: 1280px)');
    const onChange = (e: MediaQueryListEvent) => {
      if (e.matches) setDrawerOpen(false);
    };
    mq.addEventListener('change', onChange);
    return () => mq.removeEventListener('change', onChange);
  }, []);

  const navigateTo = (view: string) => {
    setSelectedProjectId(null);
    setSelectedPropertyId(null);
    setCurrentView(view);
    setDrawerOpen(false);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const navLinks = [
    { id: 'home', label: 'Home' },
    { id: 'projects', label: 'Projects' },
    { id: 'properties', label: 'Properties' },
    { id: 'agencies', label: 'Agencies' },
    { id: 'guide', label: 'Scheme 45 Guide' },
    { id: 'gallery', label: 'Media Gallery' },
    { id: 'services', label: 'Services' },
    { id: 'about', label: 'About Us' },
    { id: 'contact', label: 'Contact' },
  ];

  const whatsAppLink = generateWhatsAppLink(
    cmsConfig.contactWhatsApp,
    'Salam! I am inquiring about properties in Taiser Town Scheme 45 via TaisarTownHub.'
  );

  const goAdmin = () => navigateTo(isAdminAuthenticated ? 'admin' : 'admin-login');
  const isAdminActive = currentView.startsWith('admin');

  return (
    <>
      {/* Main Glass Navbar — constant height, scroll-aware surface */}
      <header
        className={`sticky top-0 z-40 backdrop-blur-md border-b transition-all duration-300 ${
          scrolled
            ? 'bg-[#101C2C]/95 border-[#C5A46D]/25 shadow-lg shadow-black/25'
            : 'bg-[#101C2C]/75 border-[#C5A46D]/10'
        }`}
      >
        <div className="max-w-7xl mx-auto px-3 sm:px-5 lg:px-8">
          {/* Height: 64px mobile / 80px desktop — constant per breakpoint */}
          <div className="flex items-center justify-between h-16 lg:h-20 gap-2">
            {/* Logo */}
            <button
              onClick={() => navigateTo('home')}
              className="flex items-center gap-2 sm:gap-3 cursor-pointer group min-w-0"
              aria-label={`${currentTheme.agencyName} — go to homepage`}
            >
              <div className="w-10 h-10 lg:w-11 lg:h-11 shrink-0 rounded-lg bg-gradient-to-br from-[#C5A46D] to-[#8C6D37] flex items-center justify-center shadow-lg shadow-[#C5A46D]/20 border border-[#F9E7BA]/40 group-hover:scale-105 transition-transform">
                <Building2 className="w-5 h-5 lg:w-6 lg:h-6 text-[#101C2C]" />
              </div>
              <div className="min-w-0 text-left">
                <span className="font-serif-luxury text-sm sm:text-lg lg:text-2xl font-bold tracking-tight text-white group-hover:text-[#C5A46D] transition-colors truncate block max-w-[140px] sm:max-w-none leading-tight">
                  {currentTheme.agencyName}
                </span>
                <span className="block text-[9px] lg:text-[10px] uppercase tracking-widest text-[#C5A46D] font-semibold leading-tight">
                  Scheme 45, Karachi
                </span>
              </div>
            </button>

            {/* Desktop Navigation (only when 9 links fit comfortably) */}
            <nav
              className="hidden xl:flex items-center space-x-0.5"
              aria-label="Primary navigation"
            >
              {navLinks.map((link) => {
                const isActive = currentView === link.id || (link.id === 'home' && isAdminActive === false && currentView === 'home');
                return (
                  <button
                    key={link.id}
                    onClick={() => navigateTo(link.id)}
                    aria-current={isActive ? 'page' : undefined}
                    className={`relative px-2.5 py-2 text-[13px] font-medium rounded-md transition-all duration-200 cursor-pointer group ${
                      isActive
                        ? 'text-[#C5A46D] font-semibold'
                        : 'text-slate-300 hover:text-white'
                    }`}
                  >
                    {link.label}
                    {/* Active underline animation */}
                    <span
                      aria-hidden="true"
                      className={`absolute inset-x-2.5 -bottom-0.5 h-0.5 rounded-full gold-gradient-bg transition-all duration-300 ${
                        isActive
                          ? 'opacity-100 scale-x-100'
                          : 'opacity-0 scale-x-0 group-hover:opacity-60 group-hover:scale-x-75'
                      }`}
                    />
                  </button>
                );
              })}
            </nav>

            {/* Action Cluster */}
            <div className="flex items-center gap-2 shrink-0">
              {/* WhatsApp Quick Chat — desktop only (also in drawer) */}
              <a
                href={whatsAppLink}
                target="_blank"
                rel="noreferrer"
                className="hidden xl:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium text-emerald-400 bg-emerald-950/40 border border-emerald-500/30 hover:bg-emerald-900/50 transition-colors"
                title="Direct WhatsApp Inquiry"
              >
                <MessageCircle className="w-3.5 h-3.5" />
                <span>WhatsApp</span>
              </a>

              {/* Admin — icon-only below lg, full label from lg up */}
              <button
                onClick={goAdmin}
                aria-label={isAdminAuthenticated ? 'Open admin dashboard' : 'Open admin login'}
                className={`flex items-center justify-center lg:justify-start gap-2 px-2.5 lg:px-3 py-1.5 rounded-lg text-xs font-semibold transition-all border cursor-pointer ${
                  isAdminActive
                    ? 'bg-[#C5A46D] text-[#101C2C] border-[#C5A46D] shadow-md'
                    : 'bg-slate-800 text-slate-200 border-slate-700 hover:border-slate-500 hover:text-white'
                }`}
              >
                <LayoutDashboard className="w-3.5 h-3.5 text-[#C5A46D] shrink-0" />
                <span className="hidden lg:inline">{isAdminAuthenticated ? 'Dashboard' : 'Admin Login'}</span>
              </button>

              {/* Drawer Toggle — all screens below xl */}
              <button
                onClick={() => setDrawerOpen(!drawerOpen)}
                className="xl:hidden p-2 rounded-md text-slate-300 hover:text-white hover:bg-white/10 cursor-pointer"
                aria-label={drawerOpen ? 'Close navigation menu' : 'Open navigation menu'}
                aria-expanded={drawerOpen}
                aria-controls="primary-drawer"
              >
                {drawerOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
              </button>
            </div>
          </div>
        </div>

        {/* Slide-down Drawer — below xl only */}
        {drawerOpen && (
          <>
            {/* Backdrop */}
            <button
              aria-hidden="true"
              tabIndex={-1}
              onClick={() => setDrawerOpen(false)}
              className="xl:hidden fixed inset-0 top-16 lg:top-20 -z-10 bg-black/55 backdrop-blur-sm animate-fadeIn cursor-default"
            />
            <nav
              id="primary-drawer"
              role="menu"
              aria-label="Primary navigation"
              className="xl:hidden fixed top-16 lg:top-20 inset-x-0 z-40 bg-[#0B1220]/97 backdrop-blur-xl border-b border-[#C5A46D]/20 px-4 pt-3 pb-6 max-h-[calc(100dvh-4rem)] overflow-y-auto animate-dropIn shadow-2xl"
            >
              {/* Links */}
              <div className="space-y-1" role="none">
                {navLinks.map((link) => {
                  const isActive = currentView === link.id;
                  return (
                    <button
                      key={link.id}
                      role="menuitem"
                      onClick={() => navigateTo(link.id)}
                      aria-current={isActive ? 'page' : undefined}
                      className={`w-full text-left px-3 py-2.5 rounded-lg text-sm font-medium flex items-center justify-between transition-colors ${
                        isActive
                          ? 'text-[#C5A46D] bg-[#C5A46D]/10 font-semibold border-l-2 border-[#C5A46D]'
                          : 'text-slate-300 hover:bg-white/5 hover:pl-4'
                      }`}
                    >
                      <span>{link.label}</span>
                      <ChevronRight className={`w-4 h-4 ${isActive ? 'text-[#C5A46D]' : 'text-slate-600'}`} />
                    </button>
                  );
                })}
              </div>

              {/* Calls to Action */}
              <div className="pt-4 mt-2 border-t border-slate-800 flex flex-col gap-2">
                <button
                  role="menuitem"
                  onClick={goAdmin}
                  className={`w-full py-2.5 px-4 rounded-lg font-medium text-sm flex items-center justify-center gap-2 transition-all ${
                    isAdminActive
                      ? 'bg-[#C5A46D] text-[#101C2C]'
                      : 'bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C]'
                  }`}
                >
                  <LayoutDashboard className="w-4 h-4" />
                  <span>{isAdminAuthenticated ? 'Admin Dashboard' : 'Admin Portal Login'}</span>
                </button>
                <a
                  role="menuitem"
                  href={whatsAppLink}
                  target="_blank"
                  rel="noreferrer"
                  className="w-full py-2.5 px-4 rounded-lg bg-emerald-700 hover:bg-emerald-600 text-white font-medium text-sm flex items-center justify-center gap-2 transition-colors"
                >
                  <MessageCircle className="w-4 h-4" />
                  <span>WhatsApp Instant Inquiry</span>
                </a>
                <a
                  role="menuitem"
                  href={`tel:${(cmsConfig.contactPhone || '').replace(/\s+/g, '')}`}
                  className="w-full py-2.5 px-4 rounded-lg bg-transparent text-slate-300 hover:text-white hover:bg-white/5 font-medium text-sm flex items-center justify-center gap-2 transition-colors"
                >
                  <Phone className="w-4 h-4 text-[#C5A46D]" />
                  <span>{cmsConfig.contactPhone}</span>
                </a>
              </div>
            </nav>
          </>
        )}
      </header>
    </>
  );
};
