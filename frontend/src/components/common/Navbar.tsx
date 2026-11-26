import React, { useState, useEffect } from 'react';
import { useApp } from '../../context/AppContext';
import { LayoutDashboard, Menu, X, ChevronRight, LogOut, UserCircle2, Search } from 'lucide-react';

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
    { label: 'Properties', path: '/properties' },
    { label: 'Agencies', path: '/agencies' },
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
        scrolled ? 'glass-panel-strong border-[#E7C878]/25 shadow-xl shadow-black/20' : 'glass-panel border-[#E7C878]/20'
      }`}>
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between h-20 items-center gap-4">
            <button type="button" onClick={() => handleNavClick('/')} aria-current={isActive('/') ? 'page' : undefined} className="flex-shrink-0 flex items-center gap-3 cursor-pointer group">
              <div className="w-11 h-11 rounded-full border-2 border-[#E7C878]/60 bg-[#0B1220] flex items-center justify-center shadow-md shadow-[#E7C878]/20 group-hover:border-[#F6E3B4] transition-colors shrink-0">
                <img src="/logo.jpg" alt="" className="w-full h-full rounded-full object-contain" />
              </div>
              <div className="text-left leading-tight hidden sm:block">
                <h1 className="font-brand-cinzel text-base lg:text-lg text-white whitespace-nowrap">
                  <span className="text-white">TAISAR</span>{' '}
                  <span className="gold-gradient-text font-brand-cinzel">TOWN HUB</span>
                </h1>
                <p className="text-[11px] text-[#E7C878]">Find &middot; Sell &middot; Invest</p>
              </div>
              {/* Accessible name on mobile, where the wordmark is display:none. */}
              <span className="sr-only">Taisar Town Hub home</span>
            </button>

            <div className="hidden xl:flex items-center gap-1">
              {navItems.map((item) => (
                <button
                  key={item.label + item.path}
                  type="button"
                  onClick={() => handleNavClick(item.path)}
                  aria-current={isActive(item.path) ? 'page' : undefined}
                  className={`nav-underline px-3 py-2 rounded-md text-sm font-medium transition-colors cursor-pointer ${
                    isActive(item.path) ? 'text-[#E7C878] bg-[#E7C878]/10 is-active' : 'text-slate-300 hover:text-white hover:bg-white/5'
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
                    isActive('/admin') ? 'bg-[#E7C878] text-[#0B1220] border-[#E7C878]' : 'bg-transparent text-[#E7C878] border-[#E7C878]/50 hover:bg-[#E7C878]/10'
                  }`}>
                    <LayoutDashboard className="w-4 h-4" />
                    <span>Dashboard</span>
                  </button>
                  <div className="hidden lg:flex items-center gap-2 px-3 py-1.5 rounded-lg bg-white/5 border border-slate-700/70 text-xs text-slate-300">
                    <UserCircle2 className="w-4 h-4 text-[#E7C878]" />
                    <span className="max-w-[120px] truncate">{currentUser?.name || currentUser?.email || 'Account'}</span>
                  </div>
                  <button type="button" onClick={() => { logoutAdmin(); handleNavClick('/'); }} className="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold text-slate-300 hover:text-white hover:bg-white/5" title="Sign out">
                    <LogOut className="w-4 h-4" />
                  </button>
                </div>
              ) : (
                <div className="hidden md:flex items-center gap-2.5">
                  <button
                    type="button"
                    onClick={() => handleNavClick('/properties')}
                    aria-label="Search properties"
                    aria-current={isActive('/properties') ? 'page' : undefined}
                    title="Search properties"
                    className="inline-flex items-center justify-center w-9 h-9 rounded-full border border-[#E7C878]/40 text-[#E7C878] hover:bg-[#E7C878] hover:text-[#0B1220] transition-colors cursor-pointer"
                  >
                    <Search className="w-4 h-4" />
                  </button>
                  <button type="button" onClick={() => handleNavClick('/login')} aria-current={isActive('/login') ? 'page' : undefined} className="px-4 py-2 rounded-lg border border-[#E7C878]/50 text-[#E7C878] hover:bg-[#E7C878]/10 text-sm font-semibold transition-colors cursor-pointer">Login</button>
                  <button type="button" onClick={() => handleNavClick('/register')} aria-current={isActive('/register') ? 'page' : undefined} className="px-4 py-2 rounded-lg bg-[#E7C878] hover:bg-[#F3C464] text-[#0B1220] text-sm font-bold transition-colors cursor-pointer">Register</button>
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
          <div id="mobile-nav-drawer" role="dialog" aria-modal="true" aria-label="Navigation menu" className="absolute right-0 top-0 h-full w-[86%] max-w-sm bg-[#0B1220] border-l border-[#E7C878]/30 shadow-2xl flex flex-col animate-slideInRight">
            <div className="flex items-center justify-between px-5 py-4 border-b border-slate-800">
              <div className="flex items-center gap-2.5">
                <div className="w-9 h-9 rounded-full border border-[#E7C878]/50 bg-[#0B1220] flex items-center justify-center">
                  <img src="/logo.jpg" alt="" className="w-full h-full rounded-full object-contain" />
                </div>
                <span className="font-brand-cinzel text-white text-sm">
                  <span className="text-white">TAISAR</span>{' '}
                  <span className="gold-gradient-text font-brand-cinzel">TOWN HUB</span>
                </span>
              </div>
              <button type="button" onClick={() => setDrawerOpen(false)} className="p-2 rounded-md text-slate-400 hover:text-white hover:bg-white/10 cursor-pointer" aria-label="Close menu"><X className="w-5 h-5" /></button>
            </div>

            <div className="flex-1 overflow-y-auto py-4 px-3 space-y-1">
              {navItems.map((item) => {
                const active = isActive(item.path);
                return (
                  <button key={item.label + item.path} type="button" onClick={() => handleNavClick(item.path)} className={`w-full flex items-center justify-between px-4 py-3 rounded-xl text-sm font-medium transition-colors cursor-pointer ${active ? 'text-[#E7C878] bg-[#E7C878]/10 border border-[#E7C878]/30' : 'text-slate-300 hover:text-white hover:bg-white/5 border border-transparent'}`}>
                    <span>{item.label}</span>
                    {active && <ChevronRight className="w-4 h-4" />}
                  </button>
                );
              })}
            </div>

            <div className="p-4 border-t border-slate-800 space-y-2">
              {hasDashboardAccess ? (
                <>
                  <button type="button" onClick={() => handleNavClick('/admin')} className="w-full flex items-center justify-center gap-2 py-3 px-4 rounded-xl bg-[#E7C878] text-[#0B1220] font-bold text-sm cursor-pointer hover:bg-[#F3C464] transition-colors"><LayoutDashboard className="w-4 h-4" /><span>Open dashboard</span></button>
                  <button type="button" onClick={() => { logoutAdmin(); handleNavClick('/'); }} className="w-full flex items-center justify-center gap-2 py-3 px-4 rounded-xl border border-slate-700 text-slate-200 font-semibold text-sm cursor-pointer hover:bg-white/5"><LogOut className="w-4 h-4" /> Sign out</button>
                </>
              ) : (
                <>
                  <button type="button" onClick={() => handleNavClick('/login')} className="w-full flex items-center justify-center py-3 px-4 rounded-xl border border-[#E7C878]/40 text-[#E7C878] font-semibold text-sm cursor-pointer hover:bg-[#E7C878]/10 transition-colors">Login</button>
                  <button type="button" onClick={() => handleNavClick('/register')} className="w-full flex items-center justify-center gap-2 py-3 px-4 rounded-xl bg-[#E7C878] text-[#0B1220] font-bold text-sm cursor-pointer hover:bg-[#F3C464] transition-colors">Register</button>
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