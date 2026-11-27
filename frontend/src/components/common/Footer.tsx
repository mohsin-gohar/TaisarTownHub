import React, { useState } from 'react';
import { useApp } from '../../context/AppContext';
import { api } from '../../services/api';
import {
  ArrowUp,
  Send,
  CheckCircle2,
  Facebook,
  Instagram,
  Youtube
} from 'lucide-react';
import { APP_MODE } from '../../config/appMode';

/**
 * Public footer — matches the approved reference layout:
 * brand + socials | Quick Links | Services | Support | Newsletter,
 * with a legal disclaimer and a bottom bar carrying the copyright and
 * the "Built with love for Taisar Town" line.
 */
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

  const quickLinks = [
    { label: 'Home', view: 'home' },
    { label: 'Properties', view: 'properties' },
    { label: 'Agencies', view: 'agencies' },
    { label: 'About Us', view: 'about' },
    { label: 'Contact', view: 'contact' },
  ];

  const serviceLinks = [
    { label: 'MDA File Verification', view: 'services' },
    { label: 'Plot Demarcation', view: 'services' },
    { label: 'Investment Advisory', view: 'services' },
    { label: 'Turnkey Villa Sales', view: 'services' },
  ];

  const supportLinks = [
    { label: 'Help & Support', view: 'contact' },
    { label: 'Video Gallery', view: 'gallery' },
    { label: 'Scheme 45 Master Plan', view: 'guide' },
    { label: 'Portal Login', view: 'login' },
  ];

  const socialLinks = [
    { Icon: Facebook, label: 'Facebook', href: cmsConfig.facebookUrl },
    { Icon: Youtube, label: 'YouTube', href: cmsConfig.youtubeUrl },
    { Icon: Instagram, label: 'Instagram', href: cmsConfig.instagramUrl },
  ].filter(({ href }) => {
    const value = (href || '').trim();
    if (!value) return false;
    // Never send visitors to a generic social-network homepage as if it were
    // the agency's official profile. Only configured profile URLs are shown.
    return !/^https?:\/\/(www\.)?(facebook|youtube|linkedin|x|instagram)\.com\/?$/i.test(value);
  });

  const renderLinkList = (items: Array<{ label: string; view: string }>) => (
    <ul className="space-y-2 text-xs">
      {items.map(({ label, view }) => (
        <li key={label}>
          <button
            type="button"
            onClick={() => setCurrentView(view)}
            className="hover:text-[#E7C878] transition-colors cursor-pointer text-left"
          >
            {label}
          </button>
        </li>
      ))}
    </ul>
  );

  return (
    <footer className="bg-[#07141E] border-t border-[#E7C878]/20 text-slate-400 text-sm">
      {/* Main Footer Links */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-10">
          {/* Brand Info */}
          <div className="lg:col-span-2 space-y-4">
            <div className="flex items-center gap-3">
              <div className="w-11 h-11 rounded-lg bg-white/5 flex items-center justify-center shadow-md border border-[#F6E3B4]/40 overflow-hidden">
                <img src="/logo.jpg" alt={`${currentTheme.agencyName} logo`} className="w-full h-full object-contain" />
              </div>
              <div>
                <span className="font-serif-luxury text-xl font-bold text-white">
                  {currentTheme.agencyName}
                </span>
                <span className="block text-[11px] tracking-widest text-[#E7C878] font-semibold">
                  Find &middot; Sell &middot; Invest
                </span>
              </div>
            </div>
            <p className="text-slate-400 text-xs sm:text-sm leading-relaxed max-w-md">
              Verified Taiser Town Scheme 45 sale listings from approved agencies — property discovery,
              master-plan insight and direct agency contact on one secure platform.
            </p>
            {/* Only configured official social profiles are shown. */}
            {socialLinks.length > 0 && (
              <div className="pt-1 flex items-center gap-2.5">
                {socialLinks.map(({ Icon, label, href }) => (
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
            )}
            {/* Quick Demo Controls — demo build only (public/production visitors never see it) */}
            {APP_MODE === 'demo' && (
              <div className="pt-2">
                <button
                  onClick={togglePresentationMode}
                  className="text-xs text-[#E7C878] hover:underline flex items-center gap-1 cursor-pointer"
                >
                  <span>Launch Client Presentation Mode</span>
                </button>
              </div>
            )}
          </div>

          {/* Quick Links */}
          <div className="space-y-3">
            <h4 className="text-white font-semibold text-sm uppercase tracking-wider font-serif-luxury">
              Quick Links
            </h4>
            {renderLinkList(quickLinks)}
          </div>

          {/* Services */}
          <div className="space-y-3">
            <h4 className="text-white font-semibold text-sm uppercase tracking-wider font-serif-luxury">
              Services
            </h4>
            {renderLinkList(serviceLinks)}
          </div>

          {/* Support */}
          <div className="space-y-3">
            <h4 className="text-white font-semibold text-sm uppercase tracking-wider font-serif-luxury">
              Support
            </h4>
            {renderLinkList(supportLinks)}
          </div>

          {/* Newsletter */}
          <div className="space-y-3">
            <h4 className="text-white font-semibold text-sm uppercase tracking-wider font-serif-luxury">
              Newsletter
            </h4>
            <p className="text-xs">Get the latest updates</p>
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
                  placeholder="Enter your email"
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
            <span className="block text-[10px] text-slate-400">We respect your privacy. Unsubscribe anytime.</span>
          </div>
        </div>

        {/* Disclaimer */}
        <div className="mt-12 pt-6 border-t border-slate-800/80 text-[11px] text-slate-400 space-y-2">
          <p>
            <strong className="text-slate-400">DISCLAIMER:</strong> TaisarTownHub is a real estate agency platform for Taiser Town Scheme 45, Karachi. Plot details, prices, and availability are subject to verification through Malir Development Authority (MDA) official channels. Please confirm current status with our advisory desk before any transaction.
          </p>
        </div>
      </div>

      {/* Bottom bar — copyright + Built with love line (reference layout) */}
      <div className="border-t border-slate-800/80">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
          <div>
            &copy; {new Date().getFullYear()} {currentTheme.agencyName}. All rights reserved.
          </div>
          <div className="flex items-center gap-1.5">
            <span>Built with</span>
            <span className="text-red-500" role="img" aria-label="love">❤️</span>
            <span>for Taisar Town</span>
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
