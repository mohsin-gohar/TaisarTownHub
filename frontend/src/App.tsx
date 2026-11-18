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
import { Scheme45Guide } from './components/public/Scheme45Guide';
import { ServicesSection } from './components/public/ServicesSection';
import { MediaGallerySection } from './components/public/MediaGallerySection';
import { AboutSection } from './components/public/AboutSection';
import { TestimonialsSection } from './components/public/TestimonialsSection';
import { ContactSection } from './components/public/ContactSection';
import { AgencyProfilePage } from './components/public/AgencyProfilePage';
import { Building2, ArrowRight, ShieldCheck, MapPin, Ruler, Users, Phone, Mail, Video, Quote } from 'lucide-react';
import { AgencyAccessPage } from './components/public/AgencyAccessPage';
import { AuthPage } from './components/public/AuthPage';
import { NotFoundPage } from './components/public/NotFoundPage';

// Code-split sub-pages & modals
const PropertyDetailPage = lazy(() => import('./components/public/PropertyDetailPage').then((m) => ({ default: m.PropertyDetailPage })));
const PropertiesListingPage = lazy(() => import('./components/public/PropertiesListingPage').then((m) => ({ default: m.PropertiesListingPage })));
const AgenciesDirectoryPage = lazy(() => import('./components/public/AgenciesDirectoryPage').then((m) => ({ default: m.AgenciesDirectoryPage })));

// Admin views
const AdminLayout = lazy(() => import('./components/admin/AdminLayout').then((m) => ({ default: m.AdminLayout })));
const AdminLoginModal = lazy(() => import('./components/admin/AdminLoginModal').then((m) => ({ default: m.AdminLoginModal })));

// SEO utilities from the single source of truth
import {
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
  // If the URL looks like a legacy project path, treat it as properties
  if (isProjectsLikePath(pathname)) return 'properties';
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
  if (resolvedView.startsWith('admin')) return;

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
    properties,
    selectedPropertyId,
    selectedProjectId,
  } = useApp();

  // Resolve view considering legacy project path redirects
  const resolvedView = getEffectiveView(currentView, window.location.pathname);
  const [pathTick, setPathTick] = useState(0);

  // Legacy project URLs must end up on the sale-only properties route so the
  // address bar matches the rendered content (avoids duplicate URLs in SEO).
  useEffect(() => {
    const target = redirectLegacyProjectPaths(window.location.pathname);
    if (target !== null) {
      window.history.replaceState({}, '', target);
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
  useEffect(() => applySeo(resolvedView, false, pathTick, properties, selectedPropertyId), [resolvedView, pathTick, properties, selectedPropertyId]);

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
        <Suspense fallback={<div className="min-h-screen bg-[#0B1220] flex items-center justify-center text-slate-400">Loading dashboard...</div>}>
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
    <div className="min-h-screen bg-[#0B1220] text-slate-100 flex flex-col justify-between selection:bg-[#C5A46D] selection:text-[#101C2C]">
      {/* Presentation control bar — demo build only (in-memory toggle, never localStorage) */}
      {APP_MODE === 'demo' && <PresentationBar />}

      {/* Device frame preview wrapper when tablet or mobile is activated in presentation mode */}
      <DeviceFrame>
        <div className="flex flex-col min-h-screen">
          {/* Accessibility: skip straight to content (keyboard users) */}
          <a
            href="#main-content"
            className="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-[80] focus:bg-[#C5A46D] focus:text-[#101C2C] focus:px-4 focus:py-2 focus:rounded-lg focus:font-bold focus:text-xs focus:shadow-xl"
          >
            Skip to main content
          </a>
          <Navbar />

          <main id="main-content" className="flex-1 page-surface">
            {false && (
              <Suspense fallback={<div className="min-h-[50vh] flex items-center justify-center text-slate-400">Loading content...</div>}>
                <NotFoundPage />
              </Suspense>
            )}

            {/* SECTION 1 — Premium Navbar (included via Navbar component) */}
            {/* Already rendered by Navbar above */}

            {/* SECTION 2 — Hero Section */}
            <HeroSection onSearch={ (criteria: any) => { /* handled internally */ } } />

            {/* SECTION 3 — Featured Verified Agencies */}
            <section className="py-24 sm:py-32 px-4 sm:px-6 lg:px-8 bg-[#0B1220]">
              <div className="max-w-7xl mx-auto">
                <h2 className="font-serif-luxury text-4xl sm:text-5xl lg:text-6xl font-bold text-white mb-6">
                  Featured Verified Agencies
                </h2>
                <p className="text-slate-400 text-lg sm:text-base mb-8 max-w-2xl">
                  Agencies trusted by TaisarTownHub with approved Taisar Town sale listings.
                </p>
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                  {[/* Agencies rendered from API when navigating to /agencies */]}
                  <div className="rounded-2xl border border-slate-800 bg-[#101C2C] p-8 text-center">
                    <Building2 className="w-12 h-12 text-slate-600 mx-auto mb-4" />
                    <h3 className="text-white font-semibold">Agencies loading...</h3>
                    <p className="text-slate-400">Agency cards will appear from the verified agency directory.</p>
                  </div>
                </div>
              </div>
            </section>

            {/* SECTION 4 — Browse All Agencies */}
            <section className="py-24 sm:py-32 px-4 sm:px-6 lg:px-8 bg-[#0B1220] border-t border-[#C5A46D]/10 border-b border-[#C5A46D]/10">
              <div className="max-w-7xl mx-auto">
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
                    className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C] font-bold text-sm transition"
                  >
                    View all agencies <ArrowRight className="w-4 h-4" />
                  </button>
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                  <button
                    onClick={() => { window.history.pushState({}, '', '/agencies'); window.dispatchEvent(new PopStateEvent('popstate')); }}
                    className="rounded-2xl border border-slate-800 bg-[#101C2C] p-5 text-left hover:border-[#C5A46D]/50 transition"
                  >
                    <div className="text-[#C5A46D] font-semibold text-sm mb-1">Agencies</div>
                    <div className="text-white font-serif-luxury text-xl font-bold">Agency directory</div>
                    <div className="text-slate-400 text-sm mt-1">View profiles, stock, and contact details.</div>
                  </button>
                  <button
                    onClick={() => { window.history.pushState({}, '', '/properties?purpose=sale'); window.dispatchEvent(new PopStateEvent('popstate')); }}
                    className="rounded-2xl border border-slate-800 bg-[#101C2C] p-5 text-left hover:border-[#C5A46D]/50 transition"
                  >
                    <div className="text-[#C5A46D] font-semibold text-sm mb-1">Buy</div>
                    <div className="text-white font-serif-luxury text-xl font-bold">Properties for sale</div>
                    <div className="text-slate-400 text-sm mt-1">Browse approved listings from verified agencies.</div>
                  </button>
                  <button
                    onClick={() => { window.history.pushState({}, '', '/agencies'); window.dispatchEvent(new PopStateEvent('popstate')); }}
                    className="rounded-2xl border border-slate-800 bg-[#101C2C] p-5 text-left hover:border-[#C5A46D]/50 transition"
                  >
                    <div className="text-[#C5A46D] font-semibold text-sm mb-1">Agencies</div>
                    <div className="text-white font-serif-luxury text-xl font-bold">Agency directory</div>
                    <div className="text-slate-400 text-sm mt-1">View profiles, stock, and contact details.</div>
                  </button>
                </div>
              </div>
            </section>

            {/* SECTION 5 — Why Choose TaisarTownHub */}
            <section className="py-24 sm:py-32 px-4 sm:px-6 lg:px-8 bg-[#0B1220]">
              <div className="max-w-7xl mx-auto text-center">
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                  <div className="space-y-3">
                    <div className="flex items-start gap-3">
                      <div className="w-8 h-8 rounded-bg-[#C5A46D]/15 border border-[#C5A46D]/30 flex items-center justify-center flex-shrink-0">
                        <ShieldCheck className="w-4 h-4 text-[#C5A46D]" />
                      </div>
                      <div>
                        <h4 className="font-serif-luxury text-xl font-bold text-white">Verified Agencies</h4>
                        <p className="text-slate-400 text-sm">Every agency on TaisarTownHub undergoes rigorous verification for trust and credibility.</p>
                      </div>
                    </div>
                    <div className="flex items-start gap-3">
                      <div className="w-8 h-8 rounded-bg-[#C5A46D]/15 border border-[#C5A46D]/30 flex items-center justify-center flex-shrink-0">
                        <MapPin className="w-4 h-4 text-[#C5A46D]" />
                      </div>
                      <div>
                        <h4 className="font-serif-luxury text-xl font-bold text-white">Taisar Town Specialization</h4>
                        <p className="text-slate-400 text-sm">Exclusive focus on Taiser Town Scheme 45 — no distractions from other cities or rental markets.</p>
                      </div>
                    </div>
                    <div className="flex items-start gap-3">
                      <div className="w-8 h-8 rounded-bg-[#C5A46D]/15 border border-[#C5A46D]/30 flex items-center justify-center flex-shrink-0">
                        <ShieldCheck className="w-4 h-4 text-[#C5A46D]" />
                      </div>
                      <div>
                        <h4 className="font-serif-luxury text-xl font-bold text-white">Secure Document Management</h4>
                        <p className="text-slate-400 text-sm">Agencies privately organize owner documents, images, videos and legal files outside public view.</p>
                      </div>
                    </div>
                    <div className="flex items-start gap-3">
                      <div className="w-8 h-8 rounded-bg-[#C5A46D]/15 border border-[#C5A46D]/30 flex items-center justify-center flex-shrink-0">
                        <Ruler className="w-4 h-4 text-[#C5A46D]" />
                      </div>
                      <div>
                        <h4 className="font-serif-luxury text-xl font-bold text-white">Transparent Property Information</h4>
                        <p className="text-slate-400 text-sm">Only approved, publicly visible listings with full details — no hidden or pending properties.</p>
                      </div>
                    </div>
                    <div className="flex items-start gap-3">
                      <div className="w-8 h-8 rounded-bg-[#C5A46D]/15 border border-[#C5A46D]/30 flex items-center justify-center flex-shrink-0">
                        <Users className="w-4 h-4 text-[#C5A46D]" />
                      </div>
                      <div>
                        <h4 className="font-serif-luxury text-xl font-bold text-white">Agency Accountability</h4>
                        <p className="text-slate-400 text-sm">Agencies control their own listing visibility and profile — every action audited and traceable.</p>
                      </div>
                    </div>
                    <div className="flex items-start gap-3">
                      <div className="w-8 h-8 rounded-bg-[#C5A46D]/15 border border-[#C5A46D]/30 flex items-center justify-center flex-shrink-0">
                        <Phone className="w-4 h-4 text-[#C5A46D]" />
                      </div>
                      <div>
                        <h4 className="font-serif-luxury text-xl font-bold text-white">Dedicated Support</h4>
                        <p className="text-slate-400 text-sm">Advisory desk support for property inquiries, site visits and document assistance.</p>
                      </div>
                    </div>
                  </div>
                  <div className="rounded-2xl border border-slate-800 bg-[#101C2C] p-8 hidden sm:block" />
                  <div className="rounded-2xl border border-slate-800 bg-[#101C2C] p-8 hidden sm:block" />
                  <div className="rounded-2xl border border-slate-800 bg-[#101C2C] p-8 hidden sm:block" />
                </div>
              </div>
            </section>

            {/* SECTION 6 — Understanding Taisar Town, Scheme 45 */}
            <section className="py-24 sm:py-32 px-4 sm:px-6 lg:px-8 bg-[#0B1220] border-t border-[#C5A46D]/10 border-b border-[#C5A46D]/10">
              <div className="max-w-7xl mx-auto">
                <h2 className="font-serif-luxury text-4xl sm:text-5xl font-bold text-white mb-8">
                  Understanding Taisar Town, Scheme 45
                </h2>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                  <div>
                    <h3 className="font-serif-luxury text-2xl font-bold text-white mb-4">Phases</h3>
                    <ul className="space-y-2 text-sm text-slate-300">
                      <li className="flex items-center gap-2"><Building2 className="w-3.5 h-3.5 text-[#C5A46D]" />Phase 1: Possession-ready sectors (70-D, 71, 72, 50, 30-A, 40)</li>
                      <li className="flex items-center gap-2"><Building2 className="w-3.5 h-3.5 text-[#C5A46D]" />Phase 2: Developing avenues (63, 64, 65, 77, 78)</li>
                      <li className="flex items-center gap-2"><Building2 className="w-3.5 h-3.5 text-[#C5A46D]" />Phase 3: Upcoming expansions (84, 85, 88)</li>
                    </ul>
                  </div>
                  <div>
                    <h3 className="font-serif-luxury text-2xl font-bold text-white mb-4">Sectors & Blocks</h3>
                    <ul className="space-y-2 text-sm text-slate-300">
                      <li className="flex items-center gap-2"><MapPin className="w-3.5 h-3.5 text-[#C5A46D]" />Sector 70-D: Executive enclave, premium plots</li>
                      <li className="flex items-center gap-2"><MapPin className="w-3.5 h-3.5 text-[#C5A46D]" />Sector 71: Park view & villas</li>
                      <li className="flex items-center gap-2"><MapPin className="w-3.5 h-3.5 text-[#C5A46D]" />Sector 50: Main commercial zone</li>
                      <li className="flex items-center gap-2"><MapPin className="w-3.5 h-3.5 text-[#C5A46D]" />Sector 30-A: MDA homes</li>
                    </ul>
                  </div>
                  <div>
                    <h3 className="font-serif-luxury text-2xl font-bold text-white mb-4">Location Context</h3>
                    <p className="text-slate-400 text-sm">Taiser Town Scheme 45, Malir, Karachi. MDA-approved developmental area with improving infrastructure, 40-ft and 150-ft roads, and progressive possession handover policies.</p>
                    <p className="text-slate-400 text-sm mt-2">Connectivity: Major arterial links to Shahrah-e-Faisal, Jinnah International Airport proximity, and upcoming mass transit discussions.</p>
                  </div>
                </div>
              </div>
            </section>

            {/* SECTION 7 — Taisar Town Video Walkthroughs */}
            <section id="video-walkthroughs" className="py-24 sm:py-32 px-4 sm:px-6 lg:px-8 bg-[#0B1220]">
              <div className="max-w-7xl mx-auto">
                <h2 className="font-serif-luxury text-4xl sm:text-5xl font-bold text-white mb-8">
                  Taisar Town Video Walkthroughs
                </h2>
                <p className="text-slate-400 text-lg mb-6">
                  Video content for approved Taisar Town properties.
                </p>
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                  {/* Video cards will render from agency video API when content exists */}
                  <div className="rounded-2xl border border-slate-800 bg-[#101C2C] p-6 text-center">
                    <Video className="w-16 h-16 text-slate-600 mx-auto mb-3" /> {/* Placeholder: Video icon */}
                    <p className="text-slate-500 text-sm">No walkthrough videos available at this time</p>
                  </div>
                  <div className="rounded-2xl border border-slate-800 bg-[#101C2C] p-6 text-center">
                    <Video className="w-16 h-16 text-slate-600 mx-auto mb-3" /> {/* Placeholder: Video icon */}
                    <p className="text-slate-500 text-sm">No walkthrough videos available at this time</p>
                  </div>
                  <div className="rounded-2xl border border-slate-800 bg-[#101C2C] p-6 text-center">
                    <Video className="w-16 h-16 text-slate-600 mx-auto mb-3" /> {/* Placeholder: Video icon */}
                    <p className="text-slate-500 text-sm">No walkthrough videos available at this time</p>
                  </div>
                </div>
              </div>
            </section>

            {/* SECTION 8 — How TaisarTownHub Works */}
            <section className="py-24 sm:py-32 px-4 sm:px-6 lg:px-8 bg-[#0B1220]">
              <div className="max-w-7xl mx-auto">
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
              </div>
            </section>

            {/* SECTION 9 — Agency Registration CTA */}
            <section className="py-24 sm:py-32 px-4 sm:px-6 lg:px-8 bg-gradient-to-br from-[#101C2C] via-[#1A2638] to-[#101C2C]">
              <div className="max-w-4xl mx-auto text-center">
                <h2 className="font-serif-luxury text-4xl sm:text-5xl font-bold text-white mb-6">
                  Grow Your Real Estate Business with TaisarTownHub
                </h2>
                <p className="text-slate-400 text-lg mb-8 max-w-2xl">
                  Partner with Pakistan's premier agency-first marketplace. Launch your profile, publish eligible Taisar Town sale properties, and manage your team through our secure dashboard.
                </p>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mx-auto">
                  <button
                    onClick={() => { window.history.pushState({}, '', '/register'); window.dispatchEvent(new PopStateEvent('popstate')); }}
                    className="w-full sm:w-px py-3 rounded-xl bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C] font-bold text-sm transition-shadow shadow-lg shadow-[#C5A46D]/20"
                  >
                    Register Your Agency
                  </button>
                  <button
                    onClick={() => { window.history.pushState({}, '', '/agencies'); window.dispatchEvent(new PopStateEvent('popstate')); }}
                    className="w-full sm:w-px py-3 rounded-xl border border-[#C5A46D]/30 bg-transparent hover:bg-[#C5A46D]/10 text-[#C5A46D] font-bold text-sm transition-shadow"
                  >
                    Already have an agency? Log in
                  </button>
                </div>
              </div>
            </section>

            {/* SECTION 10 — Testimonials */}
            <section className="py-24 sm:py-32 px-4 sm:px-6 lg:px-8 bg-[#0B1220] border-t border-[#C5A46D]/10 border-b border-[#C5A46D]/10">
              <div className="max-w-7xl mx-auto">
                <h2 className="font-serif-luxury text-4xl sm:text-5xl font-bold text-white mb-8">
                  Client Testimonials
                </h2>
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                  {/* Testimonials rendered from approved user feedback when available */}
                  <div className="rounded-2xl border border-slate-800 bg-[#101C2C] p-6 text-center">
                    <Quote className="w-10 h-10 text-[#C5A46D] mx-auto mb-4" /> {/* Placeholder: Quote icon */}
                    <p className="text-slate-400 text-sm italic">"Professional team with verified properties. Honest and transparent."</p>
                    <p className="text-slate-500 text-xs mt-2">— Verified Client</p>
                  </div>
                  <div className="rounded-2xl border border-slate-800 bg-[#101C2C] p-6 text-center">
                    <Quote className="w-10 h-10 text-[#C5A46D] mx-auto mb-4" /> {/* Placeholder: Quote icon */}
                    <p className="text-slate-400 text-sm italic">"Transparent process, genuine listings, recommended."</p>
                    <p className="text-slate-500 text-xs mt-2">— Investor</p>
                  </div>
                  <div className="rounded-2xl border border-slate-800 bg-[#101C2C] p-6 text-center">
                    <Quote className="w-10 h-10 text-[#C5A46D] mx-auto mb-4" /> {/* Placeholder: Quote icon */}
                    <p className="text-slate-400 text-sm italic">"Good selection of Taisar Town properties."</p>
                    <p className="text-slate-500 text-xs mt-2">— Buyer</p>
                  </div>
                </div>
              </div>
            </section>

            {/* SECTION 11 — Contact & Office Information */}
            <section className="py-24 sm:py-32 px-4 sm:px-6 lg:px-8 bg-[#0B1220]">
              <div className="max-w-7xl mx-auto">
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
                      Taiser Town Scheme 45, Malir, Karachi, Pakistan
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
                        <Phone className="w-4 h-4 text-[#C5A46D] mr-2" /> +92 300 1234567
                      </li>
                      <li>
                        <Mail className="w-4 h-4 text-[#C5A46D] mr-2" /> info@taisartownhub.com
                      </li>
                    </ul>
                  </div>
                  <div>
                    <h3 className="font-serif-luxury text-2xl font-bold text-white mb-4">
                      Quick Links
                    </h3>
                    <ul className="space-y-3 text-sm text-slate-300">
                      <li><a href="/agencies" className="hover:text-[#C5A46D] transition-colors">Agencies</a></li>
                      <li><a href="/properties" className="hover:text-[#C5A46D] transition-colors">Properties for Sale</a></li>
                      <li><a href="/services" className="hover:text-[#C5A46D] transition-colors">Services</a></li>
                      <li><a href="/about" className="hover:text-[#C5A46D] transition-colors">About</a></li>
                    </ul>
                  </div>
                </div>
              </div>
            </section>

            {/* SECTION 12 — Premium Footer */}
            <Footer />

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

/** Cold-load pages for backend token-based mail links. */
const ResetPasswordPage = lazy(() =>
  import('./pages/AuthFlowPages').then((m) => ({ default: m.ResetPasswordPage })),
);
const VerifyEmailPage = lazy(() =>
  import('./pages/AuthFlowPages').then((m) => ({ default: m.VerifyEmailPage })),
);

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