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

// Code-split sub-pages & modals
const ProjectDetailPage = lazy(() => import('./components/public/ProjectDetailPage').then((m) => ({ default: m.ProjectDetailPage })));
const PropertyDetailPage = lazy(() => import('./components/public/PropertyDetailPage').then((m) => ({ default: m.PropertyDetailPage })));
const ProjectsListingPage = lazy(() => import('./components/public/ProjectsListingPage').then((m) => ({ default: m.ProjectsListingPage })));
const PropertiesListingPage = lazy(() => import('./components/public/PropertiesListingPage').then((m) => ({ default: m.PropertiesListingPage })));
const AgenciesDirectoryPage = lazy(() => import('./components/public/AgenciesDirectoryPage').then((m) => ({ default: m.AgenciesDirectoryPage })));
const AgencyProfilePage = lazy(() => import('./components/public/AgencyProfilePage').then((m) => ({ default: m.AgencyProfilePage })));
const AuthPage = lazy(() => import('./components/public/AuthPage').then((m) => ({ default: m.AuthPage })));
const AgencyAccessPage = lazy(() => import('./components/public/AgencyAccessPage').then((m) => ({ default: m.AgencyAccessPage })));
const NotFoundPage = lazy(() => import('./components/public/NotFoundPage').then((m) => ({ default: m.NotFoundPage })));

// Admin views
const AdminLayout = lazy(() => import('./components/admin/AdminLayout').then((m) => ({ default: m.AdminLayout })));
const AdminLoginModal = lazy(() => import('./components/admin/AdminLoginModal').then((m) => ({ default: m.AdminLoginModal })));
import { PropertyType } from './types';
import {
  SITE_NAME,
  breadcrumbJsonLd,
  canonicalUrl,
  robotsContent,
  routeSeoForView,
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
  '/projects',
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

const MainAppContent: React.FC = () => {
  const {
    currentView,
    isAdminAuthenticated,
    isSessionLoading,
    currentUser,
    properties,
    projects,
    selectedPropertyId,
    selectedProjectId,
  } = useApp();

  // Unknown public URL → render the 404 screen (not the homepage).
  const isNotFoundRoute = !isKnownPath(window.location.pathname);
  // Derived view so the 404 screen never collides with a legitimate default
  // view value: navigating "Back to Home" from the 404 must flip the rendered
  // view even though the underlying state value was already 'home'.
  const resolvedView = isNotFoundRoute ? 'not-found' : currentView;
  const [pathTick, setPathTick] = useState(0);

  useEffect(() => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }, [resolvedView]);

  // Force a re-render whenever the URL changes through the History API so
  // route-derived UI (e.g. the 404 screen) can never go stale after pushState.
  useEffect(() => {
    const historyAny = window.history as unknown as {
      pushState: (...args: any[]) => any;
      replaceState: (...args: any[]) => any;
    };
    const originalPush = historyAny.pushState;
    const originalReplace = historyAny.replaceState;
    const makeWrapped = (original: (...args: any[]) => any) =>
      (...args: any[]) => {
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

  // SEO: keeps the crawlable <head> in sync with the active route — title,
  // description, canonical, Open Graph/Twitter cards, robots directive and
  // breadcrumb structured data. Detail pages get a unique title and
  // description derived from the selected record instead of a generic one.
  useEffect(() => {
    if (resolvedView.startsWith('admin')) return; // admin ERP sets its own title

    if (isNotFoundRoute) {
      document.title = 'Page Not Found (404) — TaisarTownHub';
      upsertMeta('name', 'description', 'The requested page could not be found. Browse live Taiser Town properties, projects and advisory services from TaisarTownHub.');
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
      const property = properties.find((p) => p.id === selectedPropertyId);
      if (property) {
        query = `?property=${encodeURIComponent(property.id)}`;
        title = `${property.title} — ${property.location} | ${SITE_NAME}`;
        description = `${property.type}${
          property.sizeSqYards ? ` · ${property.sizeSqYards} Sq. Yd` : ''
        } · ${property.formattedPrice} in ${property.location}. Verified file details, gallery and installment options.`;
      }
    } else if (resolvedView === 'project-detail') {
      const project = projects.find((p) => p.id === selectedProjectId);
      if (project) {
        query = `?project=${encodeURIComponent(project.id)}`;
        title = `${project.title} — ${project.location} | ${SITE_NAME}`;
        description = `${project.tagline} ${project.location}. Plot sizes, facilities, pricing and booking status.`;
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
  }, [resolvedView, isNotFoundRoute, pathTick, properties, projects, selectedPropertyId, selectedProjectId]);

  const [activeFilterCriteria, setActiveFilterCriteria] = useState<{
    type?: PropertyType | 'All';
    phase?: 'Phase 1' | 'Phase 2' | 'Phase 3' | 'All';
    sector?: string | 'All';
    budget?: string;
    size?: string;
  }>({});

  const handleHeroSearch = (criteria: {
    type?: PropertyType | 'All';
    phase?: 'Phase 1' | 'Phase 2' | 'Phase 3' | 'All';
    sector?: string | 'All';
    budget?: string;
    size?: string;
  }) => {
    setActiveFilterCriteria(criteria);
  };

  // If in admin view and authenticated, render full admin dashboard
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
            {isNotFoundRoute ? (
              <Suspense fallback={<div className="min-h-[50vh] flex items-center justify-center text-slate-400">Loading content...</div>}>
                <NotFoundPage />
              </Suspense>
            ) : (
            /* CSS-driven page transition (enter animation per view) */
            <div key={`${resolvedView}-${window.location.pathname}${window.location.search}`} className="animate-pageEnter">
            <Suspense fallback={<div className="min-h-[50vh] flex items-center justify-center text-slate-400">Loading content...</div>}>
              {resolvedView === 'home' && (
                <>
                  <HeroSection onSearch={handleHeroSearch} />
                  <section className="py-16 sm:py-20 px-4 sm:px-6 lg:px-8 bg-[#0B1220] border-t border-[#C5A46D]/10">
                    <div className="max-w-7xl mx-auto space-y-8">
                      <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                        <div className="space-y-2 max-w-2xl">
                          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-[#C5A46D]/15 text-[#C5A46D] border border-[#C5A46D]/30">Agency-first marketplace</span>
                          <h2 className="font-serif-luxury text-3xl sm:text-4xl font-bold text-white">Discover verified agencies and their listings</h2>
                          <p className="text-slate-400 text-sm sm:text-base">Browse trusted real estate agencies, explore approved public properties, and connect with the right team for buying or renting.</p>
                        </div>
                        <button onClick={() => { window.history.pushState({}, '', '/agencies'); window.dispatchEvent(new PopStateEvent('popstate')); }} className="self-start sm:self-auto px-5 py-2.5 rounded-xl bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C] font-bold text-sm">Explore all agencies</button>
                      </div>
                      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <button onClick={() => { window.history.pushState({}, '', '/properties?purpose=sale'); window.dispatchEvent(new PopStateEvent('popstate')); }} className="rounded-2xl border border-slate-800 bg-[#101C2C] p-5 text-left hover:border-[#C5A46D]/50 transition">
                          <div className="text-[#C5A46D] font-semibold text-sm mb-1">Buy</div>
                          <div className="text-white font-serif-luxury text-xl font-bold">Properties for sale</div>
                          <div className="text-slate-400 text-sm mt-1">Browse approved listings from verified agencies.</div>
                        </button>
                        <button onClick={() => { window.history.pushState({}, '', '/properties?purpose=rent'); window.dispatchEvent(new PopStateEvent('popstate')); }} className="rounded-2xl border border-slate-800 bg-[#101C2C] p-5 text-left hover:border-[#C5A46D]/50 transition">
                          <div className="text-[#C5A46D] font-semibold text-sm mb-1">Rent</div>
                          <div className="text-white font-serif-luxury text-xl font-bold">Properties for rent</div>
                          <div className="text-slate-400 text-sm mt-1">Find rental options managed by agencies.</div>
                        </button>
                        <button onClick={() => { window.history.pushState({}, '', '/agencies'); window.dispatchEvent(new PopStateEvent('popstate')); }} className="rounded-2xl border border-slate-800 bg-[#101C2C] p-5 text-left hover:border-[#C5A46D]/50 transition">
                          <div className="text-[#C5A46D] font-semibold text-sm mb-1">Agencies</div>
                          <div className="text-white font-serif-luxury text-xl font-bold">Agency directory</div>
                          <div className="text-slate-400 text-sm mt-1">View profiles, stock, and contact details.</div>
                        </button>
                      </div>
                    </div>
                  </section>
                  <ServicesSection />
                  <Scheme45Guide />
                  <MediaGallerySection />
                  <AboutSection />
                  <TestimonialsSection />
                  <ContactSection />
                </>
              )}

              {resolvedView === 'projects' && <ProjectsListingPage />}
              {resolvedView === 'project-detail' && <ProjectDetailPage />}
              {resolvedView === 'properties' && (
                <PropertiesListingPage initialFilter={activeFilterCriteria} />
              )}
              {resolvedView === 'property-detail' && <PropertyDetailPage />}
              {resolvedView === 'agencies' && <AgenciesDirectoryPage />}
              {resolvedView === 'agency-profile' && (
                <AgencyProfilePage slug={decodeURIComponent(window.location.pathname.replace(/^\/agencies\//, '').replace(/\/+$/, ''))} />
              )}
              {resolvedView === 'guide' && <Scheme45Guide />}
              {resolvedView === 'gallery' && <MediaGallerySection />}
              {resolvedView === 'services' && <ServicesSection />}
              {resolvedView === 'about' && <AboutSection />}
              {resolvedView === 'contact' && <ContactSection />}
              {resolvedView === 'login' && <AuthPage initialTab="signin" />}
              {resolvedView === 'register' && <AuthPage initialTab="register" />}
            </Suspense>
            </div>
            )}
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

// G-14: cold-load pages for backend token-based mail links. They are lazy
// imports so the main bundle never pays for them, and they are rendered
// INSTEAD of the SPA shell (these URLs are outside the app's navigation
// state, so AppProvider never mounts for them).
const ResetPasswordPage = lazy(() =>
  import('./pages/AuthFlowPages').then((m) => ({ default: m.ResetPasswordPage })),
);
const VerifyEmailPage = lazy(() =>
  import('./pages/AuthFlowPages').then((m) => ({ default: m.VerifyEmailPage })),
);

const AUTH_FLOW_PATHS = new Set(['/reset-password', '/verify-email']);

/**
 * Renders the matching auth-flow page for /reset-password and /verify-email,
 * bypassing the entire SPA shell (AppProvider, Navbar, …). Every other path
 * mounts the normal application.
 */
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
