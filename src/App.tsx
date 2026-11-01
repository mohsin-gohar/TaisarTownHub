import React, { useState, useEffect, Suspense, lazy } from 'react';
import { AppProvider, useApp } from './context/AppContext';
import { ErrorBoundary } from './components/common/ErrorBoundary';
import { Navbar } from './components/common/Navbar';
import { Footer } from './components/common/Footer';
import { ToastContainer } from './components/common/ToastContainer';
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

// Admin views
const AdminLayout = lazy(() => import('./components/admin/AdminLayout').then((m) => ({ default: m.AdminLayout })));
const AdminLoginModal = lazy(() => import('./components/admin/AdminLoginModal').then((m) => ({ default: m.AdminLoginModal })));
import { PropertyType } from './types';

/** Per-document SEO: keeps <title>/description in sync with the active view. */
const VIEW_META: Record<string, { title: string; description: string }> = {
  home: { title: 'TaisarTownHub — Taiser Town Scheme 45 Karachi | Verified Plots & Projects', description: 'Buy verified residential and commercial plots, villas and projects in Taiser Town Scheme 45, Karachi with transparent file verification.' },
  projects: { title: 'Real Estate Projects — Taiser Town Scheme 45 | TaisarTownHub', description: 'Explore master-planned housing and commercial projects across all sectors of Taiser Town Scheme 45.' },
  'project-detail': { title: 'Project Details — Taiser Town Scheme 45 | TaisarTownHub', description: 'Complete project details: plot sizes, prices, facilities and booking status.' },
  properties: { title: 'Verified Plots & Properties — Taiser Town Scheme 45 | TaisarTownHub', description: 'Filter verified plots, villas and shops by sector, size and budget in Taiser Town Scheme 45.' },
  'property-detail': { title: 'Property Details — Taiser Town Scheme 45 | TaisarTownHub', description: 'Property gallery, installment planner and direct advisory contact.' },
  agencies: { title: 'Partner Agencies — TaisarTownHub', description: 'Trusted real estate agencies and dealers operating in Scheme 45.' },
  guide: { title: 'Scheme 45 Master Plan & Area Guide | TaisarTownHub', description: 'Interactive sector map, price trends and development overview for MDA Scheme 45.' },
  gallery: { title: 'Media Gallery — TaisarTownHub', description: 'Site photos, videos and development updates from Taiser Town Scheme 45.' },
  services: { title: 'Our Services — TaisarTownHub', description: 'File verification, transfers, escrow coordination and investment advisory services.' },
  about: { title: 'About Us — TaisarTownHub', description: 'Principal brokers team for Taiser Town Scheme 45 with 20+ years of Karachi real estate experience.' },
  contact: { title: 'Contact Us — TaisarTownHub', description: 'Visit our office or send an inquiry for verified plots in Taiser Town Scheme 45.' },
};

const MainAppContent: React.FC = () => {
  const { currentView, isAdminAuthenticated } = useApp();

  useEffect(() => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }, [currentView]);

  // SEO: document title + meta description per view
  useEffect(() => {
    if (currentView.startsWith('admin')) return; // admin ERP keeps its own title
    const meta = VIEW_META[currentView] || VIEW_META.home;
    document.title = meta.title;
    const desc = document.querySelector('meta[name="description"]');
    if (desc) desc.setAttribute('content', meta.description);
  }, [currentView]);

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
        <>
          <AdminLoginModal />
          <div className="min-h-screen bg-[#0B1220] flex items-center justify-center text-slate-400">
            Redirecting to authentication...
          </div>
        </>
      );
    }
    return (
      <>
        <Suspense fallback={<div className="min-h-screen bg-[#0B1220] flex items-center justify-center text-slate-400">Loading dashboard...</div>}>
          <AdminLayout />
        </Suspense>
        <ToastContainer />
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
            </Suspense>
            </div>
          </main>

          <Footer />
        </div>
      </DeviceFrame>

      {/* Floating Helpers */}
      <ToastContainer />

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
