import React, { useState, useEffect, Suspense, lazy } from 'react';
import { AppProvider, useApp } from './context/AppContext';
import { ErrorBoundary } from './components/common/ErrorBoundary';
import { Navbar } from './components/common/Navbar';
import { Footer } from './components/common/Footer';
import { ToastContainer } from './components/common/ToastContainer';
import { ConfirmDialogHost } from './components/common/ConfirmDialog';

// WhatsApp floating button hidden for now per client request.

import { DeviceFrame } from './components/presentation/DeviceFrame';
import { PresentationBar } from './components/presentation/PresentationBar';
import { APP_MODE } from './config/appMode';

// Public views
import { HeroSection } from './components/public/HeroSection';
import { AvailableForSaleSection } from './components/public/AvailableForSaleSection';
import { Scheme45Guide } from './components/public/Scheme45Guide';
import { ServicesSection } from './components/public/ServicesSection';
import { MediaGallerySection } from './components/public/MediaGallerySection';
import { VideoWalkthroughSection } from './components/public/VideoWalkthroughSection';
import { MapsPage } from './components/public/MapsPage';
import { AboutSection } from './components/public/AboutSection';

import { ContactSection } from './components/public/ContactSection';
import { AgencyProfilePage } from './components/public/AgencyProfilePage';
import { Reveal } from './components/common/Reveal';
import { Building2, ArrowRight, ShieldCheck, MapPin, Phone, Mail, Lock, Headphones } from 'lucide-react';
import { AgencyAccessPage } from './components/public/AgencyAccessPage';
import { AuthPage } from './components/public/AuthPage';
import { NotFoundPage } from './components/public/NotFoundPage';
import { api } from './services/api';

// Static imports for public pages used inside the main public route.
// NOTE: converting these from React.lazy to static imports fixes a class of
// stuck-on-navigation issues where new tabs and SPA navigations to
// /agencies, /properties, /property-detail stayed on the Suspense fallback
// forever (lazy import never resolving on the first navigation of a tab).
import { PropertyDetailPage } from './components/public/PropertyDetailPage';
import { PropertiesListingPage } from './components/public/PropertiesListingPage';
import { AgenciesDirectoryPage } from './components/public/AgenciesDirectoryPage';

// Admin views (code-split)
const AdminLayout = lazy(() => import('./components/admin/AdminLayout').then((m) => ({ default: m.AdminLayout })));
const AdminLoginModal = lazy(() => import('./components/admin/AdminLoginModal').then((m) => ({ default: m.AdminLoginModal })));

const WHY_CHOOSE_ITEMS = [
  { icon: ShieldCheck, title: 'Verified Listings', description: 'Only genuine & verified properties — every listing passes platform approval before public display.' },
  { icon: MapPin, title: 'Premium Location', description: 'In the heart of Taiser Town Scheme 45, Karachi — sectors, blocks and master-plan details in one place.' },
  { icon: Lock, title: 'Secure Platform', description: 'Agency records stay private with role-based access, tenant isolation and a hardened document vault.' },
  { icon: Headphones, title: 'Dedicated Support', description: 'Direct agency contact for questions, site visits and document guidance whenever you need help.' },
];

interface FeaturedAgency {
  id: string;
  name: string;
  slug?: string;
  logo_url?: string | null;
  description?: string | null;
  sale_listing_count?: number;
  badge?: 'Featured' | 'Sponsored' | string | null;
  position?: number | null;
  is_paid?: boolean;
}

// SEO utilities from the single source of truth
import {
  SEO_ROUTES,
  routeSeoForView,
  canonicalUrl,
  robotsContent,
  breadcrumbJsonLd,
  SITE_NAME,
} from './config/seo';

/** Upserts a <meta> tag so the runtime sync works whether or not
 *  scripts/prerender.mts already baked the tag into the served HTML. */
const upsertMeta = (key: 'name' | 'property', name: string, content: string) => {
  let el = document.head.querySelector<HTMLMetaElement>(`meta[${key}="${name}"]`);
  if (!el) {
    el = document.createElement('meta');
    el.setAttribute(key, name);
    document.head.appendChild(el);
  }
  el.setAttribute('content', content);
};

const upsertCanonical = (href: string) => {
  let el = document.head.querySelector<HTMLLinkElement>('link[rel="canonical"]');
  if (!el) {
    el = document.createElement('link');
    el.setAttribute('rel', 'canonical');
    document.head.appendChild(el);
  }
  el.setAttribute('href', href);
};

/** Replaces the per-route BreadcrumbList block (removes it when not applicable). */
const upsertJsonLd = (id: string, data: Record<string, unknown> | null) => {
  const existing = document.getElementById(id) as HTMLScriptElement | null;
  if (!data) {
    existing?.remove();
    return;
  }
  const el = existing ?? document.createElement('script');
  el.id = id;
  el.type = 'application/ld+json';
  el.textContent = JSON.stringify(data);
  if (!existing) document.head.appendChild(el);
};

/**
 * Public paths that resolve to a real view (mirrors VIEW_MAP + auth utilities
 * in AppContext). Anything else renders the 404 screen instead of silently
 * falling back to the homepage.
 */
const KNOWN_PUBLIC_PATHS = new Set([
  '/',
  '/home',
  '/properties',
  '/agencies',
  '/guide',
  '/scheme45-guide',
  '/gallery',
  '/media-gallery',
  '/maps',
  '/services',
  '/about',
  '/contact',
  '/login',
  '/auth',
  '/signin',
  '/register',
  '/admin',
  '/admin-login',
  '/reset-password',
  '/verify-email',
]);

const isKnownPath = (pathname: string): boolean => {
  const path = pathname.replace(/\/+$/, '') || '/';
  if (KNOWN_PUBLIC_PATHS.has(path)) return true;
  // Dynamic public agency profile routes: /agencies/:slug
  if (path.startsWith('/agencies/')) return true;
  // Admin deep links are resolved to their tab by AppContext.
  return path.startsWith('/admin/');
};

/** Determines whether the given path is a projects-related route that should
 *  redirect to the sale-only properties view (Rent/Projects removed from public). */
const isProjectsLikePath = (pathname: string): boolean => {
  const path = pathname.replace(/\/+$/, '').toLowerCase();
  return path === '/projects' || path === '/project-detail';
};

/** Redirects legacy project routes to the nearest sale-only equivalent. */
const redirectLegacyProjectPaths = (pathname: string): string | null => {
  const path = pathname.replace(/\/+$/, '');
  if (path === '/projects') return '/properties?purpose=sale';
  if (path === '/project-detail') return '/properties?purpose=sale';
  return null;
};

/** Determines the effective view considering legacy project path redirects. */
const getEffectiveView = (resolvedView: string, pathname: string): string => {
  // Legacy project state can survive the URL redirect during the same SPA session.
  if (isProjectsLikePath(pathname) || resolvedView === 'projects' || resolvedView === 'project-detail') return 'properties';
  return resolvedView;
};

/** SEO effect handler - uses the single source of truth from seo.ts */
const applySeo = (
  resolvedView: string,
  isNotFoundRoute: boolean,
  pathTick: number,
  properties: any[],
  selectedPropertyId: string | null
) => {
  // Back-office views must never receive public meta. `admin-login` IS in
  // SEO_ROUTES (noindex) and must get its own title/robots instead of the
  // homepage shell, so only skip views with no explicit SEO record.
  if (resolvedView.startsWith('admin') && !SEO_ROUTES.some((route) => route.view === resolvedView)) return;

  if (isNotFoundRoute) {
    document.title = 'Page Not Found (404) — TaisarTownHub';
    upsertMeta('name', 'description', 'The requested page could not be found. Browse live Taiser Town properties from verified agencies.');
    upsertMeta('name', 'robots', 'noindex,follow');
    upsertMeta('property', 'og:title', 'Page Not Found (404) — TaisarTownHub');
    upsertMeta('property', 'og:description', 'The requested page could not be found.');
    upsertMeta('name', 'twitter:title', 'Page Not Found (404) — TaisarTownHub');
    upsertMeta('name', 'twitter:description', 'The requested page could not be found.');
    document.head.querySelector('link[rel="canonical"]')?.remove();
    upsertJsonLd('route-breadcrumb-jsonld', null);
    return;
  }

  const route = routeSeoForView(resolvedView);
  let title = route.title;
  let description = route.description;
  let query = '';

  if (resolvedView === 'property-detail') {
    const property = properties.find((p: any) => p.id === selectedPropertyId);
    if (property) {
      query = `?property=${encodeURIComponent(property.id)}`;
      title = `${property.title} — ${property.location} | ${SITE_NAME}`;
      description = `${property.type}${property.sizeSqYards ? ` · ${property.sizeSqYards} Sq. Yd` : ''} · ${property.formattedPrice} in ${property.location}. Verified file details, gallery and installment options.`;
    }
  }

  const url = canonicalUrl(route, query);

  document.title = title;
  upsertMeta('name', 'description', description);
  upsertMeta('name', 'robots', robotsContent(route));
  upsertCanonical(url);
  upsertMeta('property', 'og:title', title);
  upsertMeta('property', 'og:description', description);
  upsertMeta('property', 'og:url', url);
  upsertMeta('name', 'twitter:title', title);
  upsertMeta('name', 'twitter:description', description);
  upsertJsonLd(
    'route-breadcrumb-jsonld',
    route.breadcrumb.length ? breadcrumbJsonLd(route) : null,
  );
};

/** Main application content component. */
const MainAppContent: React.FC = () => {
  const {
    currentView,
    isAdminAuthenticated,
    isSessionLoading,
    currentUser,
    cmsConfig,
    properties,
    selectedPropertyId,
    selectedProjectId,
  } = useApp();

  // Resolve view considering legacy project path redirects
  const isNotFoundRoute = !isKnownPath(window.location.pathname) && !isProjectsLikePath(window.location.pathname);
  const resolvedView = isNotFoundRoute ? 'not-found' : getEffectiveView(currentView, window.location.pathname);
  const [pathTick, setPathTick] = useState(0);
  const [activeFilterCriteria, setActiveFilterCriteria] = useState<Parameters<React.ComponentProps<typeof HeroSection>['onSearch']>[0]>({});
  const [featuredAgencies, setFeaturedAgencies] = useState<FeaturedAgency[]>([]);
  const [featuredAgenciesLoading, setFeaturedAgenciesLoading] = useState(true);
  const [featuredAgenciesError, setFeaturedAgenciesError] = useState(false);
  const [hasFeaturedPlacements, setHasFeaturedPlacements] = useState(false);

  useEffect(() => {
    let active = true;
    void api.getFeaturedAgencies({ limit: 6 }).then((body) => {
      if (!active) return;
      // The public endpoint returns Super-Admin-selected live placements and a
      // verified-agency fallback with `featured: false`; fallback cards never
      // carry a Featured badge.
      const agencies = Array.isArray(body?.agencies)
        ? body.agencies
        : Array.isArray(body?.agencies?.data)
          ? body.agencies.data
          : [];
      setFeaturedAgencies(agencies.slice(0, 6));
      setHasFeaturedPlacements(body?.featured === true);
      setFeaturedAgenciesError(!body || body.success === false);
      setFeaturedAgenciesLoading(false);
    });
    return () => { active = false; };
  }, []);

  // Legacy project URLs must end up on the sale-only properties route so the
  // address bar matches the rendered content (avoids duplicate URLs in SEO).
  useEffect(() => {
    const target = redirectLegacyProjectPaths(window.location.pathname);
    if (target !== null) {
      window.history.replaceState({}, '', target);
      window.dispatchEvent(new PopStateEvent('popstate'));
      setPathTick((tick) => tick + 1);
    }
  }, []);

  useEffect(() => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }, [resolvedView]);

  // Force re-render on URL change via History API
  useEffect(() => {
    const historyAny = window.history as unknown as {
      pushState: (...args: any[]) => any;
      replaceState: (...args: any[]) => any;
    };
    const originalPush = historyAny.pushState;
    const originalReplace = historyAny.replaceState;
    const makeWrapped = (original: (...args: any[]) => any) => (...args: any[]) => {
      const result = original.apply(window.history, args);
      setPathTick((tick) => tick + 1);
      return result;
    };
    historyAny.pushState = makeWrapped(originalPush);
    historyAny.replaceState = makeWrapped(originalReplace);
    const handlePopState = () => setPathTick((tick) => tick + 1);
    window.addEventListener('popstate', handlePopState);
    return () => {
      historyAny.pushState = originalPush;
      historyAny.replaceState = originalReplace;
      window.removeEventListener('popstate', handlePopState);
    };
  }, []);

  // SEO: keeps the crawlable <head> in sync with the active route
  useEffect(() => applySeo(resolvedView, isNotFoundRoute, pathTick, properties, selectedPropertyId), [resolvedView, isNotFoundRoute, pathTick, properties, selectedPropertyId]);

  // Admin view
  if (resolvedView === 'admin') {
    if (isSessionLoading) {
      return <div role="status" className="min-h-screen bg-[#070B14] flex items-center justify-center text-slate-300">Verifying workspace access…</div>;
    }
    if (isAdminAuthenticated && currentUser.workspaceAccess?.allowed === false) {
      return (
        <Suspense fallback={<div role="status" className="min-h-screen bg-[#070B14] text-slate-300 p-8">Loading approval status…</div>}>
          <AgencyAccessPage />
          <ToastContainer />
        </Suspense>
      );
    }
    if (!isAdminAuthenticated) {
      return (
        <Suspense fallback={<div className="min-h-screen bg-[#070B14] flex items-center justify-center text-slate-400">Loading Portal...</div>}>
          <AuthPage initialTab="signin" />
          <ToastContainer />
          <ConfirmDialogHost />
        </Suspense>
      );
    }
    return (
      <>
        <Suspense fallback={<div className="min-h-screen bg-[#07141E] flex items-center justify-center text-slate-400">Loading dashboard...</div>}>
          <AdminLayout />
        </Suspense>
        <ToastContainer />
        <ConfirmDialogHost />
        {APP_MODE === 'demo' && <PresentationBar />}
      </>
    );
  }

  // Public Site Router
  return (
    <div className="min-h-screen bg-[#07141E] text-slate-100 flex flex-col justify-between selection:bg-[#E7C878] selection:text-[#0B1220]">
      {/* Presentation control bar — demo build only (in-memory toggle, never localStorage) */}
      {APP_MODE === 'demo' && <PresentationBar />}

      {/* Device frame preview wrapper when tablet or mobile is activated in presentation mode */}
      <DeviceFrame>
        <div className="flex flex-col min-h-screen">
          {/* Accessibility: skip straight to content (keyboard users) */}
          <a
            href="#main-content"
            className="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-[80] focus:bg-[#E7C878] focus:text-[#0B1220] focus:px-4 focus:py-2 focus:rounded-lg focus:font-bold focus:text-xs focus:shadow-xl"
          >
            Skip to main content
          </a>
          <Navbar />

          <main id="main-content" className="flex-1 page-surface">
            {isNotFoundRoute ? (
              <Suspense fallback={<div className="min-h-[50vh] flex items-center justify-center text-slate-400">Loading content...</div>}>
                <NotFoundPage />
              </Suspense>
            ) : resolvedView !== 'home' ? (
              <Suspense fallback={<div className="min-h-[50vh] flex items-center justify-center text-slate-400">Loading content...</div>}>
                {resolvedView === 'properties' && <PropertiesListingPage initialFilter={activeFilterCriteria} />}
                {resolvedView === 'property-detail' && <PropertyDetailPage />}
                {resolvedView === 'agencies' && <AgenciesDirectoryPage />}
                {resolvedView === 'agency-profile' && (
                  <AgencyProfilePage slug={decodeURIComponent(window.location.pathname.replace(/^\/agencies\//, '').replace(/\/+$/, ''))} />
                )}
                {resolvedView === 'guide' && <Scheme45Guide />}
                {resolvedView === 'gallery' && <MediaGallerySection />}
                {resolvedView === 'maps' && <MapsPage />}
                {resolvedView === 'services' && <ServicesSection />}
                {resolvedView === 'about' && <AboutSection />}
                {resolvedView === 'contact' && <ContactSection />}
                {resolvedView === 'login' && <AuthPage initialTab="signin" />}
                {resolvedView === 'register' && <AuthPage initialTab="register" />}
              </Suspense>
            ) : null}

            {resolvedView === 'home' && <div className="luxury-home">
            {/* SECTION 1 — Premium Navbar (included via Navbar component) */}
            {/* Already rendered by Navbar above */}

            {/* SECTION 2 — Hero Section */}
            <HeroSection onSearch={setActiveFilterCriteria} />

            {/* SECTION 2B — Available for Sale (light inventory band) */}
            <AvailableForSaleSection />

            {/* SECTION 3 — Featured Verified Agencies */}
            <section className="py-24 sm:py-32 px-4 sm:px-6 lg:px-8 bg-[#07141E]">
              <Reveal className="max-w-7xl mx-auto">
                <h2 className="font-serif-luxury text-4xl sm:text-5xl lg:text-6xl font-bold text-white mb-6">
                  {hasFeaturedPlacements ? 'Featured Agencies' : 'Verified Agencies'}
                </h2>
                <p className="text-slate-400 text-lg sm:text-base mb-8 max-w-2xl">
                  {hasFeaturedPlacements
                    ? 'Spotlight placements from approved, verified agencies with Taiser Town properties for sale.'
                    : 'Public agencies with Taiser Town properties for sale.'}
                </p>
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                  {featuredAgenciesLoading ? (
                    <p role="status" className="text-slate-400">Loading agencies…</p>
                  ) : featuredAgencies.length > 0 ? featuredAgencies.map((agency) => (
                    <a
                      key={agency.id}
                      href={`/agencies/${encodeURIComponent(agency.slug || agency.id)}`}
                      className="flex items-start gap-4 rounded-lg border border-slate-800 bg-[#0B1220] p-5 hover:border-[#E7C878]/50 transition-colors"
                    >
                      {agency.logo_url ? (
                        <img src={agency.logo_url} alt="" loading="lazy" className="w-12 h-12 rounded-lg object-cover bg-[#07141E]" />
                      ) : (
                        <Building2 aria-hidden="true" className="w-12 h-12 p-3 rounded-lg bg-[#07141E] text-[#E7C878]" />
                      )}
                      <span className="min-w-0">
                        <span className="flex items-center gap-2 flex-wrap">
                          <span className="text-white font-semibold">{agency.name}</span>
                          {agency.badge && (
                            <span className={`inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider ${
                              agency.badge === 'Sponsored'
                                ? 'border-[#E7C878]/60 bg-[#E7C878]/10 text-[#F6E3B4]'
                                : 'border-slate-600 bg-slate-800/60 text-slate-200'
                            }`}>
                              {agency.badge}
                            </span>
                          )}
                        </span>
                        <span className="block mt-1 text-slate-400 text-sm line-clamp-2">{agency.description || 'View agency profile and sale listings.'}</span>
                        {typeof agency.sale_listing_count === 'number' && <span className="block mt-2 text-xs text-[#E7C878]">{agency.sale_listing_count} sale listings</span>}
                      </span>
                    </a>
                  )) : (
                    <div className="sm:col-span-2 lg:col-span-3 py-8 text-center">
                      <p className="text-slate-300 font-semibold">{featuredAgenciesError ? 'Agency directory is unavailable right now.' : 'No public agencies are available yet.'}</p>
                      <button onClick={() => { window.history.pushState({}, '', '/agencies'); window.dispatchEvent(new PopStateEvent('popstate')); }} className="mt-3 text-sm text-[#E7C878] hover:text-[#F6E3B4]">Browse the agency directory</button>
                    </div>
                  )}
                </div>
              </Reveal>
            </section>

            {/* SECTION 4 — Browse All Agencies */}
            <section className="py-24 sm:py-32 px-4 sm:px-6 lg:px-8 bg-[#07141E] border-t border-[#E7C878]/10 border-b border-[#E7C878]/10">
              <Reveal className="max-w-7xl mx-auto">
                <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8">
                  <div>
                    <h2 className="font-serif-luxury text-3xl sm:text-4xl font-bold text-white">
                      Browse All Agencies
                    </h2>
                    <p className="text-slate-400 text-sm sm:text-base">
                      View all verified agencies in Taiser Town Scheme 45.
                    </p>
                  </div>
                  <button
                    onClick={() => { window.history.pushState({}, '', '/agencies'); window.dispatchEvent(new PopStateEvent('popstate')); }}
                    className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#E7C878] hover:bg-[#F3C464] text-[#0B1220] font-bold text-sm transition"
                  >
                    View all agencies <ArrowRight className="w-4 h-4" />
                  </button>
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                  <button
                    onClick={() => { window.history.pushState({}, '', '/agencies'); window.dispatchEvent(new PopStateEvent('popstate')); }}
                    className="rounded-2xl border border-slate-800 bg-[#0B1220] p-5 text-left hover:border-[#E7C878]/50 transition"
                  >
                    <div className="text-[#E7C878] font-semibold text-sm mb-1">Agencies</div>
                    <div className="text-white font-serif-luxury text-xl font-bold">Agency directory</div>
                    <div className="text-slate-400 text-sm mt-1">View profiles, stock, and contact details.</div>
                  </button>
                  <button
                    onClick={() => { window.history.pushState({}, '', '/properties?purpose=sale'); window.dispatchEvent(new PopStateEvent('popstate')); }}
                    className="rounded-2xl border border-slate-800 bg-[#0B1220] p-5 text-left hover:border-[#E7C878]/50 transition"
                  >
                    <div className="text-[#E7C878] font-semibold text-sm mb-1">Buy</div>
                    <div className="text-white font-serif-luxury text-xl font-bold">Properties for sale</div>
                    <div className="text-slate-400 text-sm mt-1">Browse approved listings from verified agencies.</div>
                  </button>
                </div>
              </Reveal>
            </section>

            {/* SECTION 5 — Why Choose TaisarTownHub */}
            <section className="py-24 sm:py-32 px-4 sm:px-6 lg:px-8 bg-[#07141E]">
              <div className="max-w-7xl mx-auto text-center">
                <Reveal>
                  <div className="flex items-center justify-center gap-3 mb-3">
                    <Building2 className="w-8 h-8 text-[#E7C878]" aria-hidden="true" />
                    <h2 className="font-serif-luxury text-3xl sm:text-4xl lg:text-5xl font-bold text-white">
                      Why Choose TaisarTownHub?
                    </h2>
                  </div>
                  <p className="text-slate-400 text-base sm:text-lg mb-12">
                    Your Trusted Real Estate Partner in Taisar Town
                  </p>
                </Reveal>
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
                  {WHY_CHOOSE_ITEMS.map(({ icon: Icon, title, description }, index) => (
                    <Reveal key={title} delay={index * 90} className="h-full">
                      <div
                        className={`h-full px-6 py-8 flex flex-col items-center text-center gap-3 ${
                          index > 0 ? 'lg:border-l lg:border-[#E7C878]/20' : ''
                        }`}
                      >
                        <span className="w-14 h-14 rounded-full bg-[#E7C878]/10 border border-[#E7C878]/30 flex items-center justify-center">
                          <Icon className="w-7 h-7 text-[#E7C878]" aria-hidden="true" />
                        </span>
                        <h3 className="font-serif-luxury text-lg font-bold text-white">{title}</h3>
                        <p className="text-slate-400 text-sm leading-relaxed">{description}</p>
                      </div>
                    </Reveal>
                  ))}
                </div>
              </div>
            </section>

            {/* SECTION 6 — Understanding Taisar Town, Scheme 45 */}
            <section className="py-24 sm:py-32 px-4 sm:px-6 lg:px-8 bg-[#07141E] border-t border-[#E7C878]/10 border-b border-[#E7C878]/10">
              <Reveal className="max-w-7xl mx-auto">
                <h2 className="font-serif-luxury text-4xl sm:text-5xl font-bold text-white mb-8">
                  Understanding Taisar Town, Scheme 45
                </h2>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                  <div>
                    <h3 className="font-serif-luxury text-2xl font-bold text-white mb-4">Phases</h3>
                    <ul className="space-y-2 text-sm text-slate-300">
                      <li className="flex items-center gap-2"><Building2 className="w-3.5 h-3.5 text-[#E7C878]" />Phase 1: Possession-ready sectors (70-D, 71, 72, 50, 30-A, 40)</li>
                      <li className="flex items-center gap-2"><Building2 className="w-3.5 h-3.5 text-[#E7C878]" />Phase 2: Developing avenues (63, 64, 65, 77, 78)</li>
                      <li className="flex items-center gap-2"><Building2 className="w-3.5 h-3.5 text-[#E7C878]" />Phase 3: Upcoming expansions (84, 85, 88)</li>
                    </ul>
                  </div>
                  <div>
                    <h3 className="font-serif-luxury text-2xl font-bold text-white mb-4">Sectors & Blocks</h3>
                    <ul className="space-y-2 text-sm text-slate-300">
                      <li className="flex items-center gap-2"><MapPin className="w-3.5 h-3.5 text-[#E7C878]" />Sector 70-D: Executive enclave, premium plots</li>
                      <li className="flex items-center gap-2"><MapPin className="w-3.5 h-3.5 text-[#E7C878]" />Sector 71: Park view & villas</li>
                      <li className="flex items-center gap-2"><MapPin className="w-3.5 h-3.5 text-[#E7C878]" />Sector 50: Main commercial zone</li>
                      <li className="flex items-center gap-2"><MapPin className="w-3.5 h-3.5 text-[#E7C878]" />Sector 30-A: MDA homes</li>
                    </ul>
                  </div>
                  <div>
                    <h3 className="font-serif-luxury text-2xl font-bold text-white mb-4">Location Context</h3>
                    <p className="text-slate-400 text-sm">Taiser Town Scheme 45, Malir, Karachi. MDA-approved developmental area with improving infrastructure, 40-ft and 150-ft roads, and progressive possession handover policies.</p>
                    <p className="text-slate-400 text-sm mt-2">Connectivity: Major arterial links to Shahrah-e-Faisal, Jinnah International Airport proximity, and upcoming mass transit discussions.</p>
                  </div>
                </div>
              </Reveal>
            </section>

            {/* SECTION 7 — Taisar Town Video Walkthroughs */}
            <section id="video-walkthroughs" className="py-24 sm:py-32 px-4 sm:px-6 lg:px-8 bg-[#07141E]">
              <Reveal className="max-w-7xl mx-auto">
                <h2 className="font-serif-luxury text-4xl sm:text-5xl font-bold text-white mb-8">
                  Taisar Town Video Walkthroughs
                </h2>
                <p className="text-slate-400 text-lg mb-6">
                  Verified video tours from approved Taisar Town agencies.
                </p>
                <VideoWalkthroughSection />
              </Reveal>
            </section>

            {/* SECTION 8 — How TaisarTownHub Works */}
            <section className="py-24 sm:py-32 px-4 sm:px-6 lg:px-8 bg-[#07141E]">
              <Reveal className="max-w-7xl mx-auto">
                <h2 className="font-serif-luxury text-3xl sm:text-4xl font-bold text-white mb-10 text-center">
                  How TaisarTownHub Works
                </h2>
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                  <div>
                    <h3 className="font-serif-luxury text-2xl font-bold text-white mb-4">1</h3>
                    <h4 className="font-serif-luxury text-xl font-bold text-white">Discover Verified Agencies</h4>
                    <p className="text-slate-400 text-sm">
                      Browse our curated directory of verified real-estate agencies operating in Taiser Town Scheme 45.
                    </p>
                  </div>
                  <div>
                    <h3 className="font-serif-luxury text-2xl font-bold text-white mb-4">2</h3>
                    <h4 className="font-serif-luxury text-xl font-bold text-white">Explore Approved Listings</h4>
                    <p className="text-slate-400 text-sm">
                      View each agency's approved Taisar Town sale properties with full details, images and pricing.
                    </p>
                  </div>
                  <div>
                    <h3 className="font-serif-luxury text-2xl font-bold text-white mb-4">3</h3>
                    <h4 className="font-serif-luxury text-xl font-bold text-white">Contact the Agency</h4>
                    <p className="text-slate-400 text-sm">
                      Reach out directly using the agency's contact information or submit an inquiry from a property page.
                    </p>
                  </div>
                </div>
              </Reveal>
            </section>

            {/* SECTION 9 — Agency Registration CTA (reference banner) */}
            <section className="py-16 sm:py-20 px-4 sm:px-6 lg:px-8 bg-[#07141E]">
              <Reveal className="max-w-7xl mx-auto">
                <div className="rounded-3xl overflow-hidden border border-[#E7C878]/25 bg-gradient-to-r from-[#0B1220] via-[#0E1826] to-[#0B1220] flex flex-col md:flex-row items-stretch shadow-2xl shadow-black/40">
                  <div className="relative md:w-64 lg:w-80 shrink-0 min-h-[160px]">
                    <img
                      src="/image.png"
                      alt=""
                      aria-hidden="true"
                      loading="lazy"
                      className="absolute inset-0 w-full h-full object-cover"
                    />
                    <div className="absolute inset-0 bg-gradient-to-r from-[#0B1220]/20 via-[#0B1220]/40 to-[#0B1220]" />
                  </div>
                  <div className="flex-1 p-6 sm:p-8 flex flex-col sm:flex-row items-center justify-between gap-5">
                    <div className="text-center sm:text-left">
                      <h2 className="font-serif-luxury text-2xl sm:text-3xl font-bold text-white">
                        List Your Properties with TaisarTownHub
                      </h2>
                      <p className="text-slate-400 text-sm mt-2 max-w-xl">
                        Showcase your properties and reach genuine buyers in Taisar Town.
                      </p>
                    </div>
                    <div className="flex flex-col items-center gap-2 shrink-0">
                      <button
                        onClick={() => { window.history.pushState({}, '', '/register'); window.dispatchEvent(new PopStateEvent('popstate')); }}
                        className="px-6 py-3 rounded-xl bg-[#E7C878] hover:bg-[#F3C464] active:scale-95 text-[#0B1220] font-bold text-sm transition-all shadow-lg shadow-[#E7C878]/20 flex items-center gap-2 cursor-pointer"
                      >
                        Register Your Agency
                        <ArrowRight className="w-4 h-4" />
                      </button>
                      <button
                        onClick={() => { window.history.pushState({}, '', '/login'); window.dispatchEvent(new PopStateEvent('popstate')); }}
                        className="text-xs text-[#E7C878] hover:text-[#F6E3B4] hover:underline cursor-pointer"
                      >
                        Already have an agency? Login
                      </button>
                    </div>
                  </div>
                </div>
              </Reveal>
            </section>

            {/* SECTION 11 — Contact & Office Information */}
            <section className="py-24 sm:py-32 px-4 sm:px-6 lg:px-8 bg-[#07141E]">
              <Reveal className="max-w-7xl mx-auto">
                <div className="grid grid-cols-1 lg:grid-cols-4 gap-8">
                  <div>
                    <h2 className="font-serif-luxury text-3xl sm:text-4xl font-bold text-white mb-6">
                      Contact TaisarTownHub
                    </h2>
                    <p className="text-slate-400 text-sm">
                      Our advisory desk is available during business hours to assist with property inquiries, site visits and document guidance.
                    </p>
                  </div>
                  <div>
                    <h3 className="font-serif-luxury text-2xl font-bold text-white mb-4">
                      Office Location
                    </h3>
                    <p className="text-slate-400 text-sm">
                      {cmsConfig.officeAddress}
                    </p>
                    <div className="mt-3">
                      <p className="text-slate-300 text-sm">Malir Development Authority (MDA) approved area</p>
                    </div>
                  </div>
                  <div>
                    <h3 className="font-serif-luxury text-2xl font-bold text-white mb-4">
                      Get in Touch
                    </h3>
                    <ul className="space-y-3 text-sm text-slate-300">
                      <li>
                        <a href={`tel:${cmsConfig.contactPhone.replace(/[^+\d]/g, '')}`} className="inline-flex items-center hover:text-[#E7C878]"><Phone className="w-4 h-4 text-[#E7C878] mr-2" />{cmsConfig.contactPhone}</a>
                      </li>
                      <li>
                        <a href={`mailto:${cmsConfig.contactEmail}`} className="inline-flex items-center hover:text-[#E7C878]"><Mail className="w-4 h-4 text-[#E7C878] mr-2" />{cmsConfig.contactEmail}</a>
                      </li>
                    </ul>
                  </div>
                  <div>
                    <h3 className="font-serif-luxury text-2xl font-bold text-white mb-4">
                      Quick Links
                    </h3>
                    <ul className="space-y-3 text-sm text-slate-300">
                      <li><a href="/agencies" className="hover:text-[#E7C878] transition-colors">Agencies</a></li>
                      <li><a href="/properties" className="hover:text-[#E7C878] transition-colors">Properties for Sale</a></li>
                      <li><a href="/services" className="hover:text-[#E7C878] transition-colors">Services</a></li>
                      <li><a href="/about" className="hover:text-[#E7C878] transition-colors">About</a></li>
                    </ul>
                  </div>
                </div>
              </Reveal>
            </section>

            {/* SECTION 12 — Premium Footer */}
            </div>}
          </main>

          <Footer />

        </div>
      </DeviceFrame>

      {/* Floating Helpers */}
      <ToastContainer />

      {/* Global accessible confirmation dialog (replaces window.confirm) */}
      <ConfirmDialogHost />

      {/* Login modal popup if requested */}
      {resolvedView === 'admin-login' && (
        <Suspense fallback={null}>
          <AdminLoginModal />
        </Suspense>
      )}
    </div>
  );
};

/** Static pages for backend token-based mail links (lazy caused stuck-on-first-load). */
import { ResetPasswordPage } from './pages/AuthFlowPages';
import { VerifyEmailPage } from './pages/AuthFlowPages';

const AUTH_FLOW_PATHS = new Set(['/reset-password', '/verify-email']);

/** Renders the matching auth-flow page for /reset-password and /verify-email, bypassing the entire SPA shell. */
const AuthFlowGate: React.FC = () => {
  const path = window.location.pathname.replace(/\/+$/, '') || '/';
  if (!AUTH_FLOW_PATHS.has(path)) {
    return (
      <ErrorBoundary>
        <AppProvider>
          <MainAppContent />
        </AppProvider>
      </ErrorBoundary>
    );
  }

  const Page = path === '/reset-password' ? ResetPasswordPage : VerifyEmailPage;
  return (
    <ErrorBoundary>
      <Suspense
        fallback={
          <div className="min-h-screen bg-[#070B14] flex items-center justify-center text-slate-400">
            Loading…
          </div>
        }
      >
        <Page />
      </Suspense>
    </ErrorBoundary>
  );
};

export default function App() {
  return <AuthFlowGate />;
}
