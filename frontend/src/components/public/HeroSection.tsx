import React, { useRef, useState, useEffect } from 'react';
import { useApp } from '../../context/AppContext';
import {
  Search,
  Building2,
  ArrowRight,
  Compass,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  MapPin,
  ShieldCheck,
  Handshake,
  Home,
  Ruler,
  Wallet
} from 'lucide-react';
import { PropertyType } from '../../types';
import { usePrefersReducedMotion } from '../common/usePrefersReducedMotion';

interface HeroSectionProps {
  onSearch: (criteria: {
    type?: PropertyType | 'All';
    phase?: 'Phase 1' | 'Phase 2' | 'Phase 3' | 'All';
    sector?: string | 'All';
    budget?: string;
    size?: string;
  }) => void;
}

/** Staggered word-by-word cinematic reveal for the headline (CSS-driven). */
const StaggeredWords: React.FC<{ text: string; gold: boolean; delay?: number; isStatic?: boolean }> = ({
  text,
  gold,
  delay = 0,
  isStatic,
}) => {
  const words = text.split(' ');
  return (
    <>
      {words.map((word, i) => {
        const wordDelay = `${Math.round((delay + i * 0.055) * 1000)}ms`;
        const content = (
          <span className={gold ? 'gold-gradient-text' : undefined}>{word}</span>
        );
        if (isStatic) {
          return (
            <span key={i}>
              {content}
              {i < words.length - 1 ? ' ' : ''}
            </span>
          );
        }
        // Each word rises from a clipping box — pure CSS with per-word delay
        return (
          <span key={i} className="word-reveal-outer">
            <span className="word-reveal" style={{ ['--word-delay' as string]: wordDelay }}>
              {content}
              {'\u00A0'}
            </span>
          </span>
        );
      })}
    </>
  );
};

interface HeroSlide {
  id: string;
  background: string;
  alt: string;
}

/** Auto-rotating hero slides — uploaded brand shot first, then premium property scenes. */
const HERO_SLIDES: HeroSlide[] = [
  { id: 'uploaded', background: '/image.png', alt: 'TaisarTownHub — Taiser Town Scheme 45 signature vista' },
  { id: 'modern-villa', background: '/hero-slide-2.jpg', alt: 'Modern luxury villa with pool at Taiser Town' },
  { id: 'residential', background: '/hero-slide-3.jpg', alt: 'Premium residential home exterior' },
  { id: 'classic-home', background: '/hero-slide-4.jpg', alt: 'Classic family home in Scheme 45' },
  { id: 'modern-estate', background: '/hero-slide-5.jpg', alt: 'Contemporary estate at Taiser Town' },
];

/** Slides advance every 6–8 seconds; reduced motion disables the autoplay. */
const HERO_SLIDE_INTERVAL_MS = 7000;

export const HeroSection: React.FC<HeroSectionProps> = ({ onSearch }) => {
  const { cmsConfig, currentTheme, setCurrentView } = useApp();

  const [selectedType, setSelectedType] = useState<PropertyType | 'All'>('All');
  const [selectedPhase, setSelectedPhase] = useState<'Phase 1' | 'Phase 2' | 'Phase 3' | 'All'>('All');
  const [selectedSector, setSelectedSector] = useState<string>('All');
  const [selectedBudget, setSelectedBudget] = useState<string>('All');
  const [selectedSize, setSelectedSize] = useState<string>('All');

  const [activeSlideIndex, setActiveSlideIndex] = useState(0);

  // Parallax: background drifts slower than scroll; content fades gently.
  const heroRef = useRef<HTMLDivElement>(null);
  const bgRef = useRef<HTMLDivElement>(null);
  const contentRef = useRef<HTMLDivElement>(null);
  const reduceMotion = usePrefersReducedMotion();

  useEffect(() => {
    if (reduceMotion) return;
    let raf = 0;
    const onScroll = () => {
      if (raf) return;
      raf = requestAnimationFrame(() => {
        const y = Math.max(0, Math.min(window.scrollY, window.innerHeight));
        if (bgRef.current) {
          bgRef.current.style.transform = `translate3d(0, ${(y * 0.16).toFixed(1)}px, 0)`;
        }
        if (contentRef.current) {
          const fade = Math.max(0.25, 1 - (y / (window.innerHeight * 0.75)) * 0.75);
          contentRef.current.style.opacity = fade.toFixed(3);
        }
        raf = 0;
      });
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    return () => {
      window.removeEventListener('scroll', onScroll);
      if (raf) cancelAnimationFrame(raf);
    };
  }, [reduceMotion]);

  // Hero slideshow: cross-fade between slides on an interval (6–8s).
  useEffect(() => {
    if (reduceMotion) return;
    const timer = window.setInterval(() => {
      setActiveSlideIndex((prev) => (prev + 1) % HERO_SLIDES.length);
    }, HERO_SLIDE_INTERVAL_MS);
    return () => window.clearInterval(timer);
  }, [reduceMotion]);

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    onSearch({
      type: selectedType,
      phase: selectedPhase,
      sector: selectedSector,
      budget: selectedBudget,
      size: selectedSize,
    });
    setCurrentView('properties');
  };

  // Headline format: "Line one. Gold line." — the last segment renders in gold
  // and trailing periods are stripped so the reveal matches the reference design.
  const headlineParts = cmsConfig.heroHeadline
    .split('. ')
    .map((part) => part.replace(/\.$/, ''))
    .filter(Boolean);
  const goldHeadlineIndex = headlineParts.length - 1;
  const isStatic = !!reduceMotion;

  /** Trust strip under the search bar (matches the approved reference hero). */
  const TRUST_INDICATORS = [
    { icon: ShieldCheck, title: 'Trusted Platform', subtitle: 'Verified Listings' },
    { icon: Home, title: 'Premium Location', subtitle: 'Taisar Town' },
    { icon: Handshake, title: 'Secure Deals', subtitle: '100% Safe' },
  ];

  const scrollToNext = () => {
    window.scrollTo({ top: window.innerHeight * 0.92, behavior: reduceMotion ? 'auto' : 'smooth' });
  };

  return (
    <div ref={heroRef} className="premium-hero relative min-h-0 sm:min-h-[92vh] flex flex-col justify-center overflow-hidden">
      {/* Background Slideshow — cross-fade between premium property visuals every 6-8s */}
      <div className="absolute inset-0 z-0">
        {HERO_SLIDES.map((slide, index) => {
          const isActive = index === activeSlideIndex;
          return (
            <div
              key={slide.id}
              className="absolute inset-0"
              style={{ opacity: isActive ? 1 : 0, transition: 'opacity 1500ms ease-in-out', zIndex: isActive ? 1 : 0 }}
              aria-hidden={!isActive}
            >
              <div
                ref={isActive ? bgRef : undefined}
                className="absolute top-0 inset-x-0 aspect-[5/6] sm:aspect-auto sm:h-full will-change-transform"
              >
                <img
                  src={slide.background}
                  alt={slide.alt}
                  fetchPriority={isActive ? 'high' : 'auto'}
                  decoding="async"
                  loading={isActive ? 'eager' : 'lazy'}
                  className="w-full h-full object-contain sm:object-cover object-top sm:object-center"
                />
              </div>
            </div>
          );
        })}
        <div className="absolute inset-0 bg-gradient-to-r from-[#050F19]/95 via-[#07141E]/68 to-[#07141E]/10" />
        <div className="absolute inset-0 bg-gradient-to-t from-[#07141E] via-transparent to-[#07141E]/30" />
        <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-transparent via-[#07141E]/30 to-[#07141E]/75" />
      </div>

      <div
        ref={contentRef}
        className="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-28 flex flex-col items-center text-center lg:items-start lg:text-left"
      >
        {/* Top Floating Pill */}
        <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-[#0B1220]/80 border border-[#E7C878]/40 backdrop-blur-md mb-6 shadow-xl animate-fadeIn">
          <MapPin className="w-3.5 h-3.5 text-[#E7C878]" />
          <span className="text-xs uppercase tracking-widest text-[#F6E3B4] font-semibold">
            {currentTheme.heroBadge}
          </span>
        </div>

        {/* Cinematic Headline — word-by-word stagger */}
        <h1 className="font-serif-luxury text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-bold text-white tracking-tight leading-[1.15] max-w-5xl">
          {headlineParts.map((part, index) => (
            <span key={index} className="block">
              <StaggeredWords text={part} gold={index === goldHeadlineIndex} delay={0.15 + index * 0.45} isStatic={isStatic} />
            </span>
          ))}
        </h1>

        {/* Subtitle */}
        <p
          className="mt-6 text-base sm:text-lg md:text-xl text-slate-300 max-w-3xl leading-relaxed font-light animate-fadeInUp"
          style={isStatic ? undefined : { animationDelay: '1.05s' }}
        >
          {cmsConfig.heroSubtitle}
        </p>

        {/* Premium Property Search Interface */}
        <div
          className="mt-8 w-full max-w-5xl lg:max-w-4xl animate-fadeInUp"
          style={isStatic ? undefined : { animationDelay: '1.45s' }}
        >
          <div className="glass-panel p-4 sm:p-6 rounded-2xl shadow-2xl border border-[#E7C878]/30 text-left">
            <form onSubmit={handleSearchSubmit} className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3.5">
              {/* Property Type */}
              <div className="relative">
                <Home aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#B98A2F]" />
                <select
                  aria-label="Property type"
                  value={selectedType}
                  onChange={(e) => setSelectedType(e.target.value as PropertyType | 'All')}
                  className="w-full bg-[#07141E]/90 text-white text-xs sm:text-sm rounded-lg pl-9 pr-3 py-3 border border-slate-700 focus:border-[#E7C878] focus:outline-none appearance-none cursor-pointer"
                >
                    <option value="All">All Property Types</option>
                    <option value="Residential Plot">Residential Plot</option>
                    <option value="Commercial Plot">Commercial Plot</option>
                    <option value="House / Villa">House / Villa</option>
                    <option value="Commercial Shop">Commercial Shop</option>
                    <option value="Apartment">Apartment</option>
                    <option value="Farmhouse">Farmhouse</option>
                  </select>
              </div>

              {/* Taiser Town Phase */}
              <div className="relative">
                <Building2 aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#B98A2F]" />
                <select
                  aria-label="Taiser Town phase"
                  value={selectedPhase}
                  onChange={(e) => setSelectedPhase(e.target.value as 'Phase 1' | 'Phase 2' | 'Phase 3' | 'All')}
                  className="w-full bg-[#07141E]/90 text-[#F6E3B4] font-medium text-xs sm:text-sm rounded-lg pl-9 pr-3 py-3 border border-[#E7C878]/50 focus:border-[#E7C878] focus:outline-none appearance-none cursor-pointer"
                >
                  <option value="All">All Phases (1, 2, 3)</option>
                  <option value="Phase 1">Phase 1 (Possession Ready)</option>
                  <option value="Phase 2">Phase 2 (Developing Boulevards)</option>
                  <option value="Phase 3">Phase 3 (Affordable & Farmhouses)</option>
                </select>
              </div>

              {/* Sector */}
              <div className="relative">
                <MapPin aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#B98A2F]" />
                <select
                  aria-label="Sector or location"
                  value={selectedSector}
                  onChange={(e) => setSelectedSector(e.target.value)}
                  className="w-full bg-[#07141E]/90 text-white text-xs sm:text-sm rounded-lg pl-9 pr-3 py-3 border border-slate-700 focus:border-[#E7C878] focus:outline-none appearance-none cursor-pointer"
                >
                  <option value="All">All Sectors</option>
                  <option value="Sector 70-D">Sector 70-D (Phase 1)</option>
                  <option value="Sector 71">Sector 71 (Phase 1)</option>
                  <option value="Sector 50">Sector 50 (Phase 1)</option>
                  <option value="Sector 30-A">Sector 30-A (Phase 1)</option>
                  <option value="Sector 40">Sector 40 (Phase 1)</option>
                  <option value="Sector 63">Sector 63 (Phase 2 - 100ft)</option>
                  <option value="Sector 77">Sector 77 (Phase 2)</option>
                  <option value="Sector 84">Sector 84 (Phase 3 Farmhouses)</option>
                  <option value="Sector 85">Sector 85 (Phase 3)</option>
                </select>
              </div>

              {/* Budget */}
              <div className="relative">
                <Wallet aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#B98A2F]" />
                <select
                  aria-label="Budget in PKR"
                  value={selectedBudget}
                  onChange={(e) => setSelectedBudget(e.target.value)}
                  className="w-full bg-[#07141E]/90 text-white text-xs sm:text-sm rounded-lg pl-9 pr-3 py-3 border border-slate-700 focus:border-[#E7C878] focus:outline-none appearance-none cursor-pointer"
                >
                  <option value="All">Any Price Range</option>
                  <option value="under-25">Under 25 Lacs</option>
                  <option value="25-50">25 to 50 Lacs</option>
                  <option value="50-100">50 Lacs to 1 Crore</option>
                  <option value="1cr-plus">Above 1 Crore</option>
                </select>
              </div>

              {/* Size */}
              <div className="relative">
                <Ruler aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#B98A2F]" />
                <select
                  aria-label="Plot or unit size"
                  value={selectedSize}
                  onChange={(e) => setSelectedSize(e.target.value)}
                  className="w-full bg-[#07141E]/90 text-white text-xs sm:text-sm rounded-lg pl-9 pr-3 py-3 border border-slate-700 focus:border-[#E7C878] focus:outline-none appearance-none cursor-pointer"
                >
                  <option value="All">All Sizes</option>
                  <option value="80">80 Sq. Yards</option>
                  <option value="120">120 Sq. Yards</option>
                  <option value="240">240 Sq. Yards</option>
                  <option value="400">400 Sq. Yards</option>
                  <option value="commercial">Commercial Units</option>
                </select>
              </div>

              {/* Submit Button */}
              <div className="flex items-end">
                <button
                  type="submit"
                  className="w-full py-2.5 px-4 rounded-lg bg-[#E7C878] hover:bg-[#F3C464] active:scale-95 text-[#0B1220] font-bold text-sm tracking-wide transition-all flex items-center justify-center gap-2 cursor-pointer shadow-md shadow-[#E7C878]/20 h-[42px]"
                >
                  <Search className="w-4 h-4" />
                  <span>Search</span>
                </button>
              </div>
            </form>
          </div>
        </div>

        {/* Quick CTA Buttons — kept compact under the search bar */}
        <div
          className="mt-8 flex flex-wrap items-center justify-center lg:justify-start gap-3 animate-fadeInUp"
          style={isStatic ? undefined : { animationDelay: '1.6s' }}
        >
          <button
            onClick={() => setCurrentView('properties')}
            className="px-6 py-3 rounded-xl bg-[#E7C878] hover:bg-[#F3C464] active:scale-95 text-[#0B1220] font-bold text-sm tracking-wide transition-all duration-200 shadow-lg shadow-[#E7C878]/25 hover:shadow-[#E7C878]/40 flex items-center gap-2 cursor-pointer"
          >
            <span>Explore Properties</span>
            <ArrowRight className="w-4 h-4" />
          </button>
          <button
            onClick={() => setCurrentView('agencies')}
            className="px-6 py-3 rounded-xl bg-white/10 hover:bg-white/15 active:scale-95 text-white font-semibold text-sm tracking-wide transition-all duration-200 backdrop-blur-md border border-white/20 flex items-center gap-2 cursor-pointer"
          >
            <span>Discover Agencies</span>
            <Building2 className="w-4 h-4 text-[#E7C878]" />
          </button>
          <button
            onClick={() => setCurrentView('guide')}
            className="px-5 py-3 rounded-xl bg-[#0B1220]/80 hover:bg-[#0B1220] active:scale-95 text-slate-300 hover:text-white font-medium text-sm transition-all border border-[#E7C878]/30 flex items-center gap-2 cursor-pointer"
          >
            <Compass className="w-4 h-4 text-[#E7C878]" />
            <span>Scheme 45 Master Plan</span>
          </button>
        </div>

        {/* Trust Indicators — reference hero footer strip */}
        <div className="mt-10 flex flex-wrap items-center gap-x-10 gap-y-5 w-full max-w-5xl lg:max-w-4xl pt-7 border-t border-white/10">
          {TRUST_INDICATORS.map(({ icon: Icon, title, subtitle }, index) => (
            <div
              key={title}
              className={`flex items-center gap-3 ${isStatic ? '' : 'animate-fadeInUp'}`}
              style={isStatic ? undefined : { animationDelay: `${1.75 + index * 0.12}s` }}
            >
              <span className="w-11 h-11 rounded-full bg-[#E7C878]/10 border border-[#E7C878]/40 flex items-center justify-center shrink-0">
                <Icon className="w-5 h-5 text-[#E7C878]" />
              </span>
              <span className="text-left">
                <span className="block text-white font-semibold text-sm">{title}</span>
                <span className="block text-slate-400 text-xs">{subtitle}</span>
              </span>
            </div>
          ))}
        </div>
      </div>

      {/* Hero Slide Switcher — circular arrows + dots (reference style) */}
      <div className="absolute bottom-6 right-6 z-20 hidden sm:flex items-center gap-3">
        <button
          type="button"
          onClick={() => setActiveSlideIndex((prev) => (prev - 1 + HERO_SLIDES.length) % HERO_SLIDES.length)}
          aria-label="Previous slide"
          className="w-9 h-9 rounded-full bg-[#07141E]/70 backdrop-blur-md border border-[#E7C878]/40 text-[#E7C878] hover:bg-[#E7C878] hover:text-[#0B1220] active:scale-95 transition-all cursor-pointer flex items-center justify-center shadow-lg"
        >
          <ChevronLeft className="w-4 h-4" />
        </button>
        <div className="flex items-center gap-2 bg-[#07141E]/70 backdrop-blur-md px-3 py-1.5 rounded-full border border-[#E7C878]/30 shadow-lg">
          {HERO_SLIDES.map((slide, idx) => (
            <button
              key={slide.id}
              onClick={() => setActiveSlideIndex(idx)}
              aria-label={`Switch to slide ${idx + 1}`}
              className="w-6 h-6 rounded-full cursor-pointer flex items-center justify-center"
            >
              <span
                aria-hidden="true"
                className={`transition-all duration-300 rounded-full ${
                  idx === activeSlideIndex
                    ? 'w-5 h-2 bg-[#E7C878] shadow-sm shadow-[#E7C878]/50'
                    : 'w-2 h-2 bg-slate-500 group-hover:bg-slate-300'
                }`}
              />
            </button>
          ))}
        </div>
        <button
          type="button"
          onClick={() => setActiveSlideIndex((prev) => (prev + 1) % HERO_SLIDES.length)}
          aria-label="Next slide"
          className="w-9 h-9 rounded-full bg-[#07141E]/70 backdrop-blur-md border border-[#E7C878]/40 text-[#E7C878] hover:bg-[#E7C878] hover:text-[#0B1220] active:scale-95 transition-all cursor-pointer flex items-center justify-center shadow-lg"
        >
          <ChevronRight className="w-4 h-4" />
        </button>
      </div>

      {/* Scroll-down indicator */}
      <button
        onClick={scrollToNext}
        aria-label="Scroll down to explore"
        className="absolute bottom-5 left-1/2 -translate-x-1/2 z-10 flex flex-col items-center gap-1 text-slate-300 hover:text-[#E7C878] transition-colors cursor-pointer group"
      >
        <span className="text-[10px] uppercase tracking-[0.25em] font-semibold">Scroll</span>
        <span className="w-7 h-11 rounded-full border-2 border-slate-400/50 group-hover:border-[#E7C878]/70 flex items-start justify-center p-1.5">
          <ChevronDown className="w-4 h-4 animate-scrollHint text-[#E7C878]" />
        </span>
      </button>

    </div>
  );
};
