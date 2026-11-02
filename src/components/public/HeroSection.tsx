import React, { useRef, useState, useEffect } from 'react';
import { useApp } from '../../context/AppContext';
import {
  Search,
  Building,
  ArrowRight,
  Compass,
  Star,
  ChevronDown
} from 'lucide-react';
import { PropertyType } from '../../types';
import { StatsCounter } from '../common/StatsCounter';
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

const TRUST_AVATARS = ['AS', 'KH', 'ST', 'MR', 'IJ'];

export const HeroSection: React.FC<HeroSectionProps> = ({ onSearch }) => {
  const { cmsConfig, currentTheme, setCurrentView, properties } = useApp();

  const [selectedType, setSelectedType] = useState<PropertyType | 'All'>('All');
  const [selectedPhase, setSelectedPhase] = useState<'Phase 1' | 'Phase 2' | 'Phase 3' | 'All'>('All');
  const [selectedSector, setSelectedSector] = useState<string>('All');
  const [selectedBudget, setSelectedBudget] = useState<string>('All');
  const [selectedSize, setSelectedSize] = useState<string>('All');

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

  const headlineParts = cmsConfig.heroHeadline.split('. ');
  const isStatic = !!reduceMotion;

  const scrollToNext = () => {
    window.scrollTo({ top: window.innerHeight * 0.92, behavior: reduceMotion ? 'auto' : 'smooth' });
  };

  return (
    <div ref={heroRef} className="relative min-h-[92vh] flex flex-col justify-center overflow-hidden">
      {/* Background Image — LCP priority, slow cinematic zoom + scroll parallax */}
      <div className="absolute inset-0 z-0">
        <div ref={bgRef} className="absolute inset-0 -bottom-[16%] will-change-transform">
          <img
            src={cmsConfig.heroBackground}
            alt="Aerial view of Taiser Town Scheme 45, Karachi — master-planned plots and boulevards"
            fetchPriority="high"
            decoding="async"
            className="w-full h-full object-cover object-center scale-105 transform animate-subtleZoom"
          />
        </div>
        <div className="absolute inset-0 bg-gradient-to-t from-[#0B1220] via-[#0B1220]/80 to-[#101C2C]/70" />
        <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-transparent via-[#0B1220]/40 to-[#0B1220]" />
      </div>

      <div
        ref={contentRef}
        className="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-28 flex flex-col items-center text-center"
      >
        {/* Top Floating Pill */}
        <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-[#101C2C]/80 border border-[#C5A46D]/40 backdrop-blur-md mb-6 shadow-xl animate-fadeIn">
          <span className="w-2 h-2 rounded-full bg-[#C5A46D] animate-ping" />
          <span className="text-xs uppercase tracking-widest text-[#F9E7BA] font-semibold">
            {currentTheme.heroBadge}
          </span>
        </div>

        {/* Cinematic Headline — word-by-word stagger */}
        <h1 className="font-serif-luxury text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-bold text-white tracking-tight leading-[1.15] max-w-5xl">
          {headlineParts.map((part, index) => (
            <span key={index} className="block">
              <StaggeredWords text={part} gold={index === 1} delay={0.15 + index * 0.45} isStatic={isStatic} />
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

        {/* Trust Badge Row */}
        <div
          className="mt-7 flex items-center gap-3 sm:gap-4 animate-fadeInUp"
          style={isStatic ? undefined : { animationDelay: '1.25s' }}
        >
          <div className="flex -space-x-2.5">
            {TRUST_AVATARS.map((initials, i) => (
              <div
                key={initials}
                className="w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-gradient-to-br from-[#2A3A52] to-[#101C2C] border-2 border-[#101C2C] ring-1 ring-[#C5A46D]/50 flex items-center justify-center text-[9px] sm:text-[10px] font-bold text-[#F9E7BA] shadow"
                style={{ zIndex: TRUST_AVATARS.length - i }}
              >
                {initials}
              </div>
            ))}
          </div>
          <div className="text-left">
            <div className="flex items-center gap-1">
              {[1, 2, 3, 4, 5].map((s) => (
                <Star key={s} className="w-3.5 h-3.5 text-[#C5A46D] fill-current" />
              ))}
              <span className="ml-1 text-[11px] font-bold text-white">4.9</span>
            </div>
            <span className="block text-[11px] sm:text-xs text-slate-300 mt-0.5">
              Trusted by <strong className="text-[#F9E7BA]">1,450+ families</strong> investing in Scheme 45
            </span>
          </div>
        </div>

        {/* Quick CTA Buttons */}
        <div
          className="mt-8 flex flex-wrap items-center justify-center gap-4 animate-fadeInUp"
          style={isStatic ? undefined : { animationDelay: '1.45s' }}
        >
          <button
            onClick={() => setCurrentView('properties')}
            className="px-7 py-3.5 rounded-xl bg-[#C5A46D] hover:bg-[#D4AF37] active:scale-95 text-[#101C2C] font-bold text-sm tracking-wide transition-all duration-200 shadow-lg shadow-[#C5A46D]/25 hover:shadow-[#C5A46D]/40 flex items-center gap-2 cursor-pointer"
          >
            <span>Explore Properties</span>
            <ArrowRight className="w-4 h-4" />
          </button>
          <button
            onClick={() => setCurrentView('projects')}
            className="px-7 py-3.5 rounded-xl bg-white/10 hover:bg-white/15 active:scale-95 text-white font-semibold text-sm tracking-wide transition-all duration-200 backdrop-blur-md border border-white/20 flex items-center gap-2 cursor-pointer"
          >
            <span>Discover Projects</span>
            <Building className="w-4 h-4 text-[#C5A46D]" />
          </button>
          <button
            onClick={() => setCurrentView('guide')}
            className="px-5 py-3.5 rounded-xl bg-[#101C2C]/80 hover:bg-[#101C2C] active:scale-95 text-slate-300 hover:text-white font-medium text-sm transition-all border border-[#C5A46D]/30 flex items-center gap-2 cursor-pointer"
          >
            <Compass className="w-4 h-4 text-[#C5A46D]" />
            <span>Scheme 45 Master Plan</span>
          </button>
        </div>

        {/* Premium Property Search Interface */}
        <div
          className="mt-12 w-full max-w-5xl animate-fadeInUp"
          style={isStatic ? undefined : { animationDelay: '1.6s' }}
        >
          <div className="glass-panel p-4 sm:p-6 rounded-2xl shadow-2xl border border-[#C5A46D]/30 text-left">
            <div className="flex items-center justify-between pb-4 border-b border-white/10 mb-5">
              <div className="flex items-center gap-2 text-white font-semibold text-sm">
                <Search className="w-4 h-4 text-[#C5A46D]" />
                <span>Search Scheme 45 Real Estate Portfolio</span>
              </div>
              <span className="text-xs text-slate-400 hidden sm:inline">
                Over {properties.filter((p) => !p.deletedAt).length} active plots & villas available
              </span>
            </div>

            <form onSubmit={handleSearchSubmit} className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3.5">
              {/* Property Type */}
              <div className="space-y-1">
                <label className="text-[11px] font-semibold text-slate-300 uppercase tracking-wider block">
                  Property Type
                </label>
                <div className="relative">
                  <select
                    value={selectedType}
                    onChange={(e) => setSelectedType(e.target.value as PropertyType | 'All')}
                    className="w-full bg-[#0B1220]/90 text-white text-xs sm:text-sm rounded-lg px-3 py-2.5 border border-slate-700 focus:border-[#C5A46D] focus:outline-none appearance-none cursor-pointer"
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
              </div>

              {/* Taiser Town Phase */}
              <div className="space-y-1">
                <label className="text-[11px] font-semibold text-[#F9E7BA] uppercase tracking-wider block">
                  Taiser Town Phase
                </label>
                <select
                  value={selectedPhase}
                  onChange={(e) => setSelectedPhase(e.target.value as 'Phase 1' | 'Phase 2' | 'Phase 3' | 'All')}
                  className="w-full bg-[#0B1220]/90 text-[#F9E7BA] font-medium text-xs sm:text-sm rounded-lg px-3 py-2.5 border border-[#C5A46D]/50 focus:border-[#C5A46D] focus:outline-none appearance-none cursor-pointer"
                >
                  <option value="All">All Phases (1, 2, 3)</option>
                  <option value="Phase 1">Phase 1 (Possession Ready)</option>
                  <option value="Phase 2">Phase 2 (Developing Boulevards)</option>
                  <option value="Phase 3">Phase 3 (Affordable & Farmhouses)</option>
                </select>
              </div>

              {/* Sector */}
              <div className="space-y-1">
                <label className="text-[11px] font-semibold text-slate-300 uppercase tracking-wider block">
                  Sector / Location
                </label>
                <select
                  value={selectedSector}
                  onChange={(e) => setSelectedSector(e.target.value)}
                  className="w-full bg-[#0B1220]/90 text-white text-xs sm:text-sm rounded-lg px-3 py-2.5 border border-slate-700 focus:border-[#C5A46D] focus:outline-none appearance-none cursor-pointer"
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
              <div className="space-y-1">
                <label className="text-[11px] font-semibold text-slate-300 uppercase tracking-wider block">
                  Budget (PKR)
                </label>
                <select
                  value={selectedBudget}
                  onChange={(e) => setSelectedBudget(e.target.value)}
                  className="w-full bg-[#0B1220]/90 text-white text-xs sm:text-sm rounded-lg px-3 py-2.5 border border-slate-700 focus:border-[#C5A46D] focus:outline-none appearance-none cursor-pointer"
                >
                  <option value="All">Any Price Range</option>
                  <option value="under-25">Under 25 Lacs</option>
                  <option value="25-50">25 to 50 Lacs</option>
                  <option value="50-100">50 Lacs to 1 Crore</option>
                  <option value="1cr-plus">Above 1 Crore</option>
                </select>
              </div>

              {/* Size */}
              <div className="space-y-1">
                <label className="text-[11px] font-semibold text-slate-300 uppercase tracking-wider block">
                  Plot / Unit Size
                </label>
                <select
                  value={selectedSize}
                  onChange={(e) => setSelectedSize(e.target.value)}
                  className="w-full bg-[#0B1220]/90 text-white text-xs sm:text-sm rounded-lg px-3 py-2.5 border border-slate-700 focus:border-[#C5A46D] focus:outline-none appearance-none cursor-pointer"
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
                  className="w-full py-2.5 px-4 rounded-lg bg-[#C5A46D] hover:bg-[#D4AF37] active:scale-95 text-[#101C2C] font-bold text-sm tracking-wide transition-all flex items-center justify-center gap-2 cursor-pointer shadow-md shadow-[#C5A46D]/20 h-[42px]"
                >
                  <Search className="w-4 h-4" />
                  <span>Search</span>
                </button>
              </div>
            </form>
          </div>
        </div>

        {/* Key Statistics Strip — animated count-up */}
        <div className="mt-16 grid grid-cols-2 md:grid-cols-4 gap-6 w-full max-w-5xl pt-8 border-t border-slate-800/80">
          {[
            { value: cmsConfig.statsAcresPlanned, label: 'Acres Master-Planned' },
            { value: '80+', label: 'Sectors & Blocks' },
            { value: cmsConfig.statsPropertiesCount, label: 'Verified Properties' },
            { value: cmsConfig.statsSatisfiedClients, label: 'Satisfied Investors' },
          ].map((stat) => (
            <div key={stat.label} className="space-y-1">
              <div className="font-serif-luxury text-3xl sm:text-4xl font-bold text-white">
                <StatsCounter value={stat.value} />
              </div>
              <div className="text-xs text-[#C5A46D] uppercase tracking-wider font-semibold">
                {stat.label}
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Scroll-down indicator */}
      <button
        onClick={scrollToNext}
        aria-label="Scroll down to explore"
        className="absolute bottom-5 left-1/2 -translate-x-1/2 z-10 flex flex-col items-center gap-1 text-slate-300 hover:text-[#C5A46D] transition-colors cursor-pointer group"
      >
        <span className="text-[10px] uppercase tracking-[0.25em] font-semibold">Scroll</span>
        <span className="w-7 h-11 rounded-full border-2 border-slate-400/50 group-hover:border-[#C5A46D]/70 flex items-start justify-center p-1.5">
          <ChevronDown className="w-4 h-4 animate-scrollHint text-[#C5A46D]" />
        </span>
      </button>
    </div>
  );
};
