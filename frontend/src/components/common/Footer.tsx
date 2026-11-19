import React, { useState } from 'react';
import { useApp } from '../../context/AppContext';
import { api } from '../../services/api';
import {
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

  const handleNewsletter = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newsletterEmail.trim() || !/^\S+@\S+\.\S+$/.test(newsletterEmail)) return;
    const saved = await api.subscribe(newsletterEmail.trim());
    if (!saved) return;
    setNewsletterDone(true);
    setNewsletterEmail('');
  };

  return (
    <footer className="bg-[#07141E] border-t border-[#E7C878]/20 text-slate-400 text-sm">
      {/* Top Banner / Call to Action */}
      <div className="bg-gradient-to-r from-[#0B1220] via-[#1A2638] to-[#0B1220] border-b border-[#E7C878]/15 py-12 px-4 sm:px-6 lg:px-8">
        <div className="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-6">
          <div className="space-y-2 text-center md:text-left">
            <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-[#E7C878]/15 text-[#E7C878] border border-[#E7C878]/30">
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
            <button
              onClick={() => setCurrentView('contact')}
              className="px-6 py-3 rounded-lg bg-[#E7C878] hover:bg-[#F3C464] text-[#0B1220] font-bold text-sm transition-all shadow-lg shadow-[#E7C878]/20 flex items-center gap-2 cursor-pointer"
            >
              <span>Inquire with Advisory Desk</span>
              <ArrowRight className="w-4 h-4" />
            </button>
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
              <div className="w-10 h-10 rounded-lg bg-white/5 flex items-center justify-center shadow-md border border-[#F6E3B4]/40 overflow-hidden">
                <img src="/logo.jpg" alt="Logo" className="w-full h-full object-contain" />
              </div>
              <div>
                <span className="font-serif-luxury text-xl font-bold text-white">
                  {currentTheme.agencyName}
                </span>
                <span className="block text-[10px] uppercase tracking-widest text-[#E7C878] font-semibold">
                  Taiser Town Scheme 45
                </span>
              </div>
            </div>
            <p className="text-slate-400 text-xs sm:text-sm leading-relaxed">
              {cmsConfig.aboutDescription}
            </p>
            <div className="pt-2 flex items-center gap-2 text-xs text-emerald-400">
              <ShieldCheck className="w-4 h-4 text-emerald-400" />
              <span>Independent Real Estate Advisory & Agency Platform</span>
            </div>
            {/* Social Links */}
            <div className="pt-3 flex items-center gap-2.5">
              {[
                { Icon: Facebook, label: 'Facebook', href: cmsConfig.facebookUrl || 'https://facebook.com' },
                { Icon: Instagram, label: 'Instagram', href: cmsConfig.instagramUrl || 'https://instagram.com' },
                { Icon: Linkedin, label: 'LinkedIn', href: 'https://linkedin.com' },
                { Icon: Youtube, label: 'YouTube', href: cmsConfig.youtubeUrl || 'https://youtube.com' },
              ].map(({ Icon, label, href }) => (
                <a
                  key={label}
                  href={href}
                  target="_blank" rel="noopener noreferrer"
                  aria-label={`${currentTheme.agencyName} on ${label}`}
                  title={label}
                  className="w-9 h-9 rounded-lg bg-[#0B1220] border border-slate-700 flex items-center justify-center text-slate-400 hover:text-[#E7C878] hover:border-[#E7C878]/50 hover:shadow-[0_0_12px_rgba(231,200,120,0.35)] hover:-translate-y-0.5 transition-all cursor-pointer"
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
                  className="text-xs text-[#E7C878] hover:underline flex items-center gap-1 cursor-pointer"
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
                    className="hover:text-[#E7C878] transition-colors cursor-pointer text-left"
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
                    className="hover:text-[#E7C878] transition-colors cursor-pointer text-left"
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
                <MapPin className="w-4 h-4 text-[#E7C878] shrink-0 mt-0.5" />
                <span>{cmsConfig.officeAddress}</span>
              </div>
              <div className="flex items-center gap-2">
                <Phone className="w-4 h-4 text-[#E7C878] shrink-0" />
                <span>{cmsConfig.contactPhone}</span>
              </div>
              <div className="flex items-center gap-2">
                <Mail className="w-4 h-4 text-[#E7C878] shrink-0" />
                <a href={`mailto:${cmsConfig.contactEmail}`} className="min-w-0 break-all hover:text-[#E7C878]">{cmsConfig.contactEmail}</a>
              </div>
              <div className="flex items-center gap-2">
                <Clock className="w-4 h-4 text-[#E7C878] shrink-0" />
                <span>{cmsConfig.workingHours}</span>
              </div>
            </div>

            {/* Newsletter */}
            <div className="pt-4 space-y-2">
              <h4 className="text-white font-semibold text-xs uppercase tracking-wider">
                Market Insights Newsletter
              </h4>
              {newsletterDone ? (
                <div className="flex items-center gap-2 text-xs text-emerald-400 animate-fadeIn">
                  <CheckCircle2 className="w-4 h-4" />
                  <span>You're on the list - subscription saved to our database.</span>
                </div>
              ) : (
                <form onSubmit={handleNewsletter} className="flex gap-2">
                  <input
                    type="email"
                    value={newsletterEmail}
                    onChange={(e) => setNewsletterEmail(e.target.value)}
                    placeholder="you@email.com"
                    aria-label="Email address for newsletter"
                    className="flex-1 min-w-0 bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2.5 border border-slate-700 focus:border-[#E7C878] focus:outline-none"
                  />
                  <button
                    type="submit"
                    aria-label="Subscribe to newsletter"
                    className="px-3 py-2.5 rounded-lg bg-[#E7C878] hover:bg-[#F3C464] active:scale-95 text-[#0B1220] font-bold transition-all cursor-pointer"
                  >
                    <Send className="w-4 h-4" />
                  </button>
                </form>
              )}
              <span className="block text-[10px] text-slate-500">We respect your privacy. Unsubscribe anytime.</span>
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
              &copy; {new Date().getFullYear()} {currentTheme.agencyName}. All rights reserved.
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
                className="text-[#E7C878] hover:underline"
              >
                Portal Sign In
              </button>
              <span>•</span>
              <button
                onClick={() => setCurrentView('register')}
                className="text-slate-400 hover:text-[#E7C878]"
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
          className="fixed bottom-5 left-5 z-40 w-11 h-11 rounded-full bg-[#0B1220]/95 border border-[#E7C878]/40 shadow-xl backdrop-blur-md flex items-center justify-center text-[#E7C878] hover:bg-[#E7C878] hover:text-[#0B1220] transition-all animate-fadeInUp cursor-pointer"
        >
          <ArrowUp className="w-5 h-5" />
        </button>
      )}
    </footer>
  );
};




