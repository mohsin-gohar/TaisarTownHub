import React, { useState } from 'react';
import { useApp } from '../../context/AppContext';
import {
  Building2,
  Phone,
  Mail,
  MapPin,
  Clock,
  ExternalLink,
  ShieldCheck,
  CheckCircle2,
  Sparkles,
  ArrowRight,
  ArrowUp,
  Send,
  Facebook,
  Instagram,
  Linkedin,
  Youtube
} from 'lucide-react';
import { generateWhatsAppLink } from '../../utils/logic';
import { APP_MODE } from '../../config/appMode';

export const Footer: React.FC = () => {
  const { cmsConfig, currentTheme, setCurrentView, togglePresentationMode } = useApp();
  const [newsletterEmail, setNewsletterEmail] = useState('');
  const [newsletterDone, setNewsletterDone] = useState(false);
  const [showBackToTop, setShowBackToTop] = useState(false);

  React.useEffect(() => {
    const onScroll = () => setShowBackToTop(window.scrollY > 700);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
    return () => window.removeEventListener('scroll', onScroll);
  }, []);

  const handleNewsletter = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newsletterEmail.trim() || !/^\S+@\S+\.\S+$/.test(newsletterEmail)) return;
    // Demo capture only — no backend wiring in demo mode.
    setNewsletterDone(true);
    setNewsletterEmail('');
  };

  const whatsAppLink = generateWhatsAppLink(
    cmsConfig.contactWhatsApp,
    'Salam, I would like to consult with TaisarTownHub regarding available plots in Scheme 45.'
  );

  return (
    <footer className="bg-[#0B1220] border-t border-[#C5A46D]/20 text-slate-400 text-sm">
      {/* Top Banner / Call to Action */}
      <div className="bg-gradient-to-r from-[#101C2C] via-[#1A2638] to-[#101C2C] border-b border-[#C5A46D]/15 py-12 px-4 sm:px-6 lg:px-8">
        <div className="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-6">
          <div className="space-y-2 text-center md:text-left">
            <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-[#C5A46D]/15 text-[#C5A46D] border border-[#C5A46D]/30">
              <Sparkles className="w-3.5 h-3.5" /> Scheme 45 Real Estate Advisory
            </span>
            <h3 className="font-serif-luxury text-2xl sm:text-3xl text-white font-bold">
              Looking for Verified Land or Commercial Investment?
            </h3>
            <p className="text-slate-300 max-w-xl text-sm">
              Our on-ground consultants conduct biometric file verification, on-site demarcation, and possession transfers across all Taiser Town sectors.
            </p>
          </div>
          <div className="flex flex-wrap items-center gap-3">
            <a
              href={whatsAppLink}
              target="_blank"
              rel="noreferrer"
              className="px-6 py-3 rounded-lg bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C] font-bold text-sm transition-all shadow-lg shadow-[#C5A46D]/20 flex items-center gap-2"
            >
              <span>Instant WhatsApp Inquiry</span>
              <ArrowRight className="w-4 h-4" />
            </a>
            <button
              onClick={() => setCurrentView('contact')}
              className="px-6 py-3 rounded-lg bg-slate-800/80 hover:bg-slate-700 text-white font-medium text-sm transition-all border border-slate-700 cursor-pointer"
            >
              Book Site Visit
            </button>
          </div>
        </div>
      </div>

      {/* Main Footer Links */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-10">
          {/* Brand Info */}
          <div className="lg:col-span-2 space-y-4">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-[#C5A46D] to-[#8C6D37] flex items-center justify-center shadow-md border border-[#F9E7BA]/40">
                <Building2 className="w-5 h-5 text-[#101C2C]" />
              </div>
              <div>
                <span className="font-serif-luxury text-xl font-bold text-white">
                  {currentTheme.agencyName}
                </span>
                <span className="block text-[10px] uppercase tracking-widest text-[#C5A46D] font-semibold">
                  Taiser Town Scheme 45
                </span>
              </div>
            </div>
            <p className="text-slate-400 text-xs sm:text-sm leading-relaxed">
              {cmsConfig.aboutDescription}
            </p>
            <div className="pt-2 flex items-center gap-2 text-xs text-emerald-400">
              <ShieldCheck className="w-4 h-4 text-emerald-400" />
              <span>Independent Real Estate Demo & Agency Platform</span>
            </div>
            {/* Social Links */}
            <div className="pt-3 flex items-center gap-2.5">
              {[
                { Icon: Facebook, label: 'Facebook' },
                { Icon: Instagram, label: 'Instagram' },
                { Icon: Linkedin, label: 'LinkedIn' },
                { Icon: Youtube, label: 'YouTube' },
              ].map(({ Icon, label }) => (
                <a
                  key={label}
                  href="#"
                  onClick={(e) => e.preventDefault()}
                  aria-label={`${currentTheme.agencyName} on ${label} (demo link)`}
                  title={`${label} (demo)`}
                  className="w-9 h-9 rounded-lg bg-[#101C2C] border border-slate-700 flex items-center justify-center text-slate-400 hover:text-[#C5A46D] hover:border-[#C5A46D]/50 hover:shadow-[0_0_12px_rgba(197,164,109,0.35)] hover:-translate-y-0.5 transition-all cursor-pointer"
                >
                  <Icon className="w-4 h-4" />
                </a>
              ))}
            </div>
            {/* Quick Demo Controls — demo build only (public/production visitors never see it) */}
            {APP_MODE === 'demo' && (
              <div className="pt-2">
                <button
                  onClick={togglePresentationMode}
                  className="text-xs text-[#C5A46D] hover:underline flex items-center gap-1 cursor-pointer"
                >
                  <span>Launch Client Presentation Mode</span>
                  <ExternalLink className="w-3 h-3" />
                </button>
              </div>
            )}
          </div>

          {/* Popular Sectors */}
          <div className="space-y-3">
            <h4 className="text-white font-semibold text-sm uppercase tracking-wider font-serif-luxury">
              Popular Sectors
            </h4>
            <ul className="space-y-2 text-xs">
              {['Sector 70-D (Executive Enclave)', 'Sector 71 (Park View & Villas)', 'Sector 50 (Main 150ft Commercial)', 'Sector 30-A (MDA Homes)', 'Sector 40 (Boulevard Plots)', 'Sector 84 (Green Farmhouses)'].map((sec) => (
                <li key={sec}>
                  <button
                    onClick={() => setCurrentView('properties')}
                    className="hover:text-[#C5A46D] transition-colors cursor-pointer text-left"
                  >
                    {sec}
                  </button>
                </li>
              ))}
            </ul>
          </div>

          {/* Real Estate Categories */}
          <div className="space-y-3">
            <h4 className="text-white font-semibold text-sm uppercase tracking-wider font-serif-luxury">
              Property Types
            </h4>
            <ul className="space-y-2 text-xs">
              {[
                '80 Sq. Yd. Residential Plots',
                '120 Sq. Yd. West Open Plots',
                '240 Sq. Yd. Corner Plots',
                '400 Sq. Yd. Avenue Plots',
                'Main Boulevard Commercial',
                'Ready MDA Model Villas',
                'Retail Shops & Arcades'
              ].map((type) => (
                <li key={type}>
                  <button
                    onClick={() => setCurrentView('properties')}
                    className="hover:text-[#C5A46D] transition-colors cursor-pointer text-left"
                  >
                    {type}
                  </button>
                </li>
              ))}
            </ul>
          </div>

          {/* Contact Details */}
          <div className="space-y-3">
            <h4 className="text-white font-semibold text-sm uppercase tracking-wider font-serif-luxury">
              Agency Contact
            </h4>
            <div className="space-y-2.5 text-xs">
              <div className="flex items-start gap-2">
                <MapPin className="w-4 h-4 text-[#C5A46D] shrink-0 mt-0.5" />
                <span>{cmsConfig.officeAddress}</span>
              </div>
              <div className="flex items-center gap-2">
                <Phone className="w-4 h-4 text-[#C5A46D] shrink-0" />
                <span>{cmsConfig.contactPhone}</span>
              </div>
              <div className="flex items-center gap-2">
                <Mail className="w-4 h-4 text-[#C5A46D] shrink-0" />
                <span>{cmsConfig.contactEmail}</span>
              </div>
              <div className="flex items-center gap-2">
                <Clock className="w-4 h-4 text-[#C5A46D] shrink-0" />
                <span>{cmsConfig.workingHours}</span>
              </div>
            </div>

            {/* Newsletter (demo UI only) */}
            <div className="pt-4 space-y-2">
              <h4 className="text-white font-semibold text-xs uppercase tracking-wider">
                Market Insights Newsletter
              </h4>
              {newsletterDone ? (
                <div className="flex items-center gap-2 text-xs text-emerald-400 animate-fadeIn">
                  <CheckCircle2 className="w-4 h-4" />
                  <span>You're on the list — demo subscription saved locally.</span>
                </div>
              ) : (
                <form onSubmit={handleNewsletter} className="flex gap-2">
                  <input
                    type="email"
                    value={newsletterEmail}
                    onChange={(e) => setNewsletterEmail(e.target.value)}
                    placeholder="you@email.com"
                    aria-label="Email address for newsletter"
                    className="flex-1 min-w-0 bg-[#101C2C] text-white text-xs rounded-lg px-3 py-2.5 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                  />
                  <button
                    type="submit"
                    aria-label="Subscribe to newsletter"
                    className="px-3 py-2.5 rounded-lg bg-[#C5A46D] hover:bg-[#D4AF37] active:scale-95 text-[#101C2C] font-bold transition-all cursor-pointer"
                  >
                    <Send className="w-4 h-4" />
                  </button>
                </form>
              )}
              <span className="block text-[10px] text-slate-500">Demo mode — no data leaves your browser.</span>
            </div>
          </div>
        </div>

        {/* Demo Disclaimer Box */}
        <div className="mt-12 pt-6 border-t border-slate-800/80 text-[11px] text-slate-500 space-y-2">
          <p>
            <strong className="text-slate-400">DISCLAIMER:</strong> TaisarTownHub is a real estate agency platform for Taiser Town Scheme 45, Karachi. Plot details, prices, and availability are subject to verification through Malir Development Authority (MDA) official channels. Please confirm current status with our advisory desk before any transaction.
          </p>
          <div className="flex flex-col sm:flex-row items-center justify-between pt-4 border-t border-slate-800/50 gap-4">
            <div>
              &copy; {new Date().getFullYear()} {currentTheme.agencyName}. All rights reserved. Built for professional real estate presentations.
            </div>
            <div className="flex items-center gap-4 text-xs">
              <button
                onClick={() => setCurrentView('guide')}
                className="hover:text-slate-300"
              >
                Scheme 45 Master Plan
              </button>
              <span>•</span>
              <button
                onClick={() => setCurrentView('contact')}
                className="hover:text-slate-300"
              >
                Office Location
              </button>
              <span>•</span>
              <button
                onClick={() => setCurrentView('login')}
                className="text-[#C5A46D] hover:underline"
              >
                Portal Sign In
              </button>
              <span>•</span>
              <button
                onClick={() => setCurrentView('register')}
                className="text-slate-400 hover:text-[#C5A46D]"
              >
                Register as Dealer
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Back-to-top (appears after scrolling; bottom-left to avoid toast zone) */}
      {showBackToTop && (
        <button
          onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })}
          aria-label="Back to top"
          title="Back to top"
          className="fixed bottom-5 left-5 z-40 w-11 h-11 rounded-full bg-[#101C2C]/95 border border-[#C5A46D]/40 shadow-xl backdrop-blur-md flex items-center justify-center text-[#C5A46D] hover:bg-[#C5A46D] hover:text-[#101C2C] transition-all animate-fadeInUp cursor-pointer"
        >
          <ArrowUp className="w-5 h-5" />
        </button>
      )}
    </footer>
  );
};
