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
import { FeaturedProjects } from './components/public/FeaturedProjects';
import { FeaturedProperties } from './components/public/FeaturedProperties';
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
const AgenciesSection = lazy(() => import('./components/public/AgenciesSection').then((m) => ({ default: m.AgenciesSection })));
const AuthPage = lazy(() => import('./components/public/AuthPage').then((m) => ({ default: m.AuthPage })));

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

const MainAppContent: React.FC = () => {
  const {
    currentView,
    isAdminAuthenticated,
    properties,
    projects,
    selectedPropertyId,
    selectedProjectId,
  } = useApp();

  useEffect(() => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }, [currentView]);

  // SEO: keeps the crawlable <head> in sync with the active route — title,
  // description, canonical, Open Graph/Twitter cards, robots directive and
  // breadcrumb structured data. Detail pages get a unique title and
  // description derived from the selected record instead of a generic one.
  useEffect(() => {
    if (currentView.startsWith('admin')) return; // admin ERP sets its own title

    const route = routeSeoForView(currentView);
    let title = route.title;
    let description = route.description;
    let query = '';

    if (currentView === 'property-detail') {
      const property = properties.find((p) => p.id === selectedPropertyId);
      if (property) {
        query = `?property=${encodeURIComponent(property.id)}`;
        title = `${property.title} — ${property.location} | ${SITE_NAME}`;
        description = `${property.type}${
          property.sizeSqYards ? ` · ${property.sizeSqYards} Sq. Yd` : ''
        } · ${property.formattedPrice} in ${property.location}. Verified file details, gallery and installment options.`;
      }
    } else if (currentView === 'project-detail') {
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
  }, [currentView, properties, projects, selectedPropertyId, selectedProjectId]);

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
  if (currentView === 'admin') {
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
            {/* CSS-driven page transition (enter animation per view) */}
            <div key={currentView} className="animate-pageEnter">
            <Suspense fallback={<div className="min-h-[50vh] flex items-center justify-center text-slate-400">Loading content...</div>}>
              {currentView === 'home' && (
                <>
                  <HeroSection onSearch={handleHeroSearch} />
                  <FeaturedProjects />
                  <FeaturedProperties />
                  <AgenciesSection />
                  <Scheme45Guide />
                  <ServicesSection />
                  <MediaGallerySection />
                  <AboutSection />
                  <TestimonialsSection />
                  <ContactSection />
                </>
              )}

              {currentView === 'projects' && <ProjectsListingPage />}
              {currentView === 'project-detail' && <ProjectDetailPage />}
              {currentView === 'properties' && (
                <PropertiesListingPage initialFilter={activeFilterCriteria} />
              )}
              {currentView === 'property-detail' && <PropertyDetailPage />}
              {currentView === 'agencies' && (
                <div className="py-6">
                  <AgenciesSection />
                </div>
              )}
              {currentView === 'guide' && <Scheme45Guide />}
              {currentView === 'gallery' && <MediaGallerySection />}
              {currentView === 'services' && <ServicesSection />}
              {currentView === 'about' && <AboutSection />}
              {currentView === 'contact' && <ContactSection />}
              {currentView === 'login' && <AuthPage initialTab="signin" />}
              {currentView === 'register' && <AuthPage initialTab="register" />}
            </Suspense>
            </div>
          </main>

          <Footer />
        </div>
      </DeviceFrame>

      {/* Floating Helpers */}
      <ToastContainer />

      {/* Global accessible confirmation dialog (replaces window.confirm) */}
      <ConfirmDialogHost />

      {/* Login modal popup if requested */}
      {currentView === 'admin-login' && (
        <Suspense fallback={null}>
          <AdminLoginModal />
        </Suspense>
      )}
    </div>
  );
};

export default function App() {
  return (
    <ErrorBoundary>
      <AppProvider>
        <MainAppContent />
      </AppProvider>
    </ErrorBoundary>
  );
}
