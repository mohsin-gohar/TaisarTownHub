import React, { useState, useEffect } from 'react';
import { useApp } from '../../context/AppContext';
import { LayoutDashboard, Menu, X, ChevronRight, Building2, LogOut, UserCircle2, Home } from 'lucide-react';

export const Navbar: React.FC = () => {
  const {
    setSelectedProjectId,
    setSelectedPropertyId,
    isAdminAuthenticated,
    cmsConfig,
    currentUser,
    logoutAdmin,
  } = useApp();

  const [drawerOpen, setDrawerOpen] = useState(false);
  const [scrolled, setScrolled] = useState(false);

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 24);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
    return () => window.removeEventListener('scroll', onScroll);
  }, []);

  useEffect(() => {
    if (!drawerOpen) return;
    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => { document.body.style.overflow = prev; };
  }, [drawerOpen]);

  useEffect(() => {
    if (!drawerOpen) return;
    const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') setDrawerOpen(false); };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [drawerOpen]);

  const navTo = (path: string) => {
    if (typeof window === 'undefined') return;
    window.history.pushState({}, '', path);
    window.dispatchEvent(new PopStateEvent('popstate'));
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const handleNavClick = (path: string) => {
    setSelectedProjectId(null);
    setSelectedPropertyId(null);
    navTo(path);
    setDrawerOpen(false);
  };

  const navItems = [
    { label: 'Home', path: '/' },
    { label: 'Agencies', path: '/agencies' },
    { label: 'Properties', path: '/properties' },
    { label: 'Services', path: '/services' },
      { label: 'About', path: '/about' },
      { label: 'Contact', path: '/contact' },
  ];

  const currentPath = typeof window !== 'undefined' ? window.location.pathname : '/';
  const currentSearch = typeof window !== 'undefined' ? window.location.search : '';
  const isActive = (path: string) => {
    const [p, q] = path.split('?');
    if (p === '/') return currentPath === '/' || currentPath === '/home';
    if (p === '/properties' && q) return currentPath === '/properties' && currentSearch.includes((q.split('=')[1] || ''));
    if (p === '/properties' && !q) return currentPath === '/properties' && !currentSearch.includes('purpose=sale') && !currentSearch.includes('purpose=rent');
    return currentPath === p || currentPath.startsWith(p + '/');
  };

  const hasDashboardAccess = isAdminAuthenticated && !!currentUser?.workspaceAccess?.allowed;

  return (
    <>
      <nav className={`fixed w-full top-0 z-50 transition-all duration-300 border-b ${
        scrolled ? 'glass-panel-strong border-[#C5A46D]/25 shadow-xl shadow-black/20' : 'glass-panel border-[#C5A46D]/20'
      }`}>
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between h-20 items-center gap-4">
            <button type="button" onClick={() => handleNavClick('/')} className="flex-shrink-0 flex items-center gap-3 cursor-pointer group" aria-label="Go to home">
              <div className="w-11 h-11 rounded-full border-2 border-[#C5A46D]/60 bg-[#101C2C] flex items-center justify-center shadow-md shadow-[#C5A46D]/20 group-hover:border-[#F9E7BA] transition-colors shrink-0">
                <img src="/logo.jpg" alt="" className="w-full h-full rounded-full object-contain" />
              </div>
              <div className="text-left leading-tight hidden sm:block">
                <h1 className="font-serif-luxury font-bold text-base lg:text-lg text-white tracking-wide whitespace-nowrap">{cmsConfig.agencyName || 'TaisarTownHub'}</h1>
                <p className="text-[11px] text-[#C5A46D]">Agency-first property marketplace</p>
              </div>
            </button>

            <div className="hidden xl:flex items-center gap-1">
              {navItems.map((item) => (
                <button
                  key={item.label + item.path}
                  type="button"
                  onClick={() => handleNavClick(item.path)}
                  className={`nav-underline px-3 py-2 rounded-md text-sm font-medium transition-colors cursor-pointer ${
                    isActive(item.path) ? 'text-[#C5A46D] bg-[#C5A46D]/10 is-active' : 'text-slate-300 hover:text-white hover:bg-white/5'
                  }`}
                >
                  {item.label}
                </button>
              ))}
            </div>

            <div className="flex items-center gap-2 sm:gap-3">
              {hasDashboardAccess ? (
                <div className="hidden md:flex items-center gap-2">
                  <button type="button" onClick={() => handleNavClick('/admin')} className={`inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-sm font-semibold border transition-colors cursor-pointer ${
                    isActive('/admin') ? 'bg-[#C5A46D] text-[#101C2C] border-[#C5A46D]' : 'bg-transparent text-[#C5A46D] border-[#C5A46D]/50 hover:bg-[#C5A46D]/10'
                  }`}>
                    <LayoutDashboard className="w-4 h-4" />
                    <span>Dashboard</span>
                  </button>
                  <div className="hidden lg:flex items-center gap-2 px-3 py-1.5 rounded-lg bg-white/5 border border-slate-700/70 text-xs text-slate-300">
                    <UserCircle2 className="w-4 h-4 text-[#C5A46D]" />
                    <span className="max-w-[120px] truncate">{currentUser?.name || currentUser?.email || 'Account'}</span>
                  </div>
                  <button type="button" onClick={() => { logoutAdmin(); handleNavClick('/'); }} className="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold text-slate-300 hover:text-white hover:bg-white/5" title="Sign out">
                    <LogOut className="w-4 h-4" />
                  </button>
                </div>
              ) : (
                <div className="hidden md:flex items-center gap-2">
                  <button type="button" onClick={() => handleNavClick('/login')} className="text-sm font-semibold text-slate-300 hover:text-white transition-colors cursor-pointer px-3 py-2">Log in</button>
                  <button type="button" onClick={() => handleNavClick('/register')} className="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-lg bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C] text-sm font-bold transition-colors cursor-pointer">
                    <Building2 className="w-4 h-4" /> Register agency
                  </button>
                </div>
              )}

              <button type="button" onClick={() => setDrawerOpen(true)} className="xl:hidden inline-flex items-center justify-center p-2 rounded-md text-slate-300 hover:text-white hover:bg-white/10 focus:outline-none cursor-pointer" aria-label="Open navigation menu" aria-expanded={drawerOpen} aria-controls="mobile-nav-drawer">
                <Menu className="w-6 h-6" />
              </button>
            </div>
          </div>
        </div>
      </nav>

      {drawerOpen && (
        <div className="fixed inset-0 z-[60] xl:hidden">
          <button type="button" aria-label="Close navigation" className="absolute inset-0 bg-black/60 backdrop-blur-sm animate-fadeIn" onClick={() => setDrawerOpen(false)} />
          <div id="mobile-nav-drawer" role="dialog" aria-modal="true" aria-label="Navigation menu" className="absolute right-0 top-0 h-full w-[86%] max-w-sm bg-[#101C2C] border-l border-[#C5A46D]/30 shadow-2xl flex flex-col animate-slideInRight">
            <div className="flex items-center justify-between px-5 py-4 border-b border-slate-800">
              <div className="flex items-center gap-2.5">
                <div className="w-9 h-9 rounded-full border border-[#C5A46D]/50 bg-[#101C2C] flex items-center justify-center">
                  <img src="/logo.jpg" alt="" className="w-full h-full rounded-full object-contain" />
                </div>
                <span className="font-serif-luxury font-bold text-white text-sm">{cmsConfig.agencyName || 'TaisarTownHub'}</span>
              </div>
              <button type="button" onClick={() => setDrawerOpen(false)} className="p-2 rounded-md text-slate-400 hover:text-white hover:bg-white/10 cursor-pointer" aria-label="Close menu"><X className="w-5 h-5" /></button>
            </div>

            <div className="flex-1 overflow-y-auto py-4 px-3 space-y-1">
              {navItems.map((item) => {
                const active = isActive(item.path);
                return (
                  <button key={item.label + item.path} type="button" onClick={() => handleNavClick(item.path)} className={`w-full flex items-center justify-between px-4 py-3 rounded-xl text-sm font-medium transition-colors cursor-pointer ${active ? 'text-[#C5A46D] bg-[#C5A46D]/10 border border-[#C5A46D]/30' : 'text-slate-300 hover:text-white hover:bg-white/5 border border-transparent'}`}>
                    <span>{item.label}</span>
                    {active && <ChevronRight className="w-4 h-4" />}
                  </button>
                );
              })}
            </div>

            <div className="p-4 border-t border-slate-800 space-y-2">
              {hasDashboardAccess ? (
                <>
                  <button type="button" onClick={() => handleNavClick('/admin')} className="w-full flex items-center justify-center gap-2 py-3 px-4 rounded-xl bg-[#C5A46D] text-[#101C2C] font-bold text-sm cursor-pointer hover:bg-[#D4AF37] transition-colors"><LayoutDashboard className="w-4 h-4" /><span>Open dashboard</span></button>
                  <button type="button" onClick={() => { logoutAdmin(); handleNavClick('/'); }} className="w-full flex items-center justify-center gap-2 py-3 px-4 rounded-xl border border-slate-700 text-slate-200 font-semibold text-sm cursor-pointer hover:bg-white/5"><LogOut className="w-4 h-4" /> Sign out</button>
                </>
              ) : (
                <>
                  <button type="button" onClick={() => handleNavClick('/login')} className="w-full flex items-center justify-center py-3 px-4 rounded-xl border border-[#C5A46D]/40 text-[#C5A46D] font-semibold text-sm cursor-pointer hover:bg-[#C5A46D]/10 transition-colors">Log in</button>
                  <button type="button" onClick={() => handleNavClick('/register')} className="w-full flex items-center justify-center gap-2 py-3 px-4 rounded-xl bg-[#C5A46D] text-[#101C2C] font-bold text-sm cursor-pointer hover:bg-[#D4AF37] transition-colors"><Building2 className="w-4 h-4" /> Register agency</button>
                </>
              )}
            </div>
          </div>
        </div>
      )}

      <div className="h-20" />
    </>
  );
};