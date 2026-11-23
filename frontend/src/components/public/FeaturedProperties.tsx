import React from 'react';
import { useApp } from '../../context/AppContext';
import {
  MapPin,
  Maximize2,
  Tag,
  ArrowRight,
  Flame,
  CheckCircle2,
  Sparkles,
  Home
} from 'lucide-react';
import { Property, PublicProperty } from '../../types';
import { ImageWithFallback } from '../../components/common/ImageWithFallback';
import { toPublicProperty } from '../../utils/logic';
import { Reveal } from '../common/Reveal';
import { FavoriteHeart } from '../common/FavoriteHeart';
import { WhatsAppCta } from '../common/WhatsAppCta';
import { propertyWhatsAppMessage, resolveWhatsAppLink } from '../../utils/whatsapp';

export const FeaturedProperties: React.FC = () => {
  const { properties, setSelectedPropertyId, setCurrentView, cmsConfig } = useApp();

  const handleOpenProperty = (property: { id: string }) => {
    setSelectedPropertyId(property.id);
    setCurrentView('property-detail');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  // Crawlable path to a dynamic detail page (excluded from the sitemap).
  const propertyHref = (id: string) => `/properties?property=${encodeURIComponent(id)}`;

  const onPropertyLinkClick =
    (id: string) => (e: React.MouseEvent<HTMLAnchorElement>) => {
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
      e.preventDefault();
      handleOpenProperty({ id });
    };

  const activeProperties = properties.filter((p) => !p.deletedAt);
  const featured = activeProperties.filter((p) => p.isFeatured).slice(0, 6).map(toPublicProperty);

  return (
    <section className="py-20 px-4 sm:px-6 lg:px-8 bg-[#0B1220]/50 border-t border-[#E7C878]/15">
      <div className="max-w-7xl mx-auto">
        {/* Header */}
        <div className="flex flex-col md:flex-row md:items-end justify-between mb-12">
          <div className="space-y-2">
            <span className="inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-[#E7C878]">
              <Sparkles className="w-3.5 h-3.5" /> High-Value Opportunities
            </span>
            <h2 className="font-serif-luxury text-3xl sm:text-4xl font-bold text-white">
              Featured Properties & Verified Plots
            </h2>
            <p className="text-slate-400 text-sm max-w-xl">
              Hand-picked sale listings, ready-possession plots and executive homes with transparent pricing.
            </p>
          </div>
          <button
            onClick={() => {
              setCurrentView('properties');
              window.scrollTo({ top: 0, behavior: 'smooth' });
            }}
            className="mt-4 md:mt-0 inline-flex items-center gap-2 text-sm font-semibold text-[#E7C878] hover:text-[#F6E3B4] group transition-colors cursor-pointer"
          >
            <span>Browse All Properties ({activeProperties.length})</span>
            <ArrowRight className="w-4 h-4 group-hover:translate-x-1 transition-transform" />
          </button>
        </div>

        {/* Property Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          {featured.map((prop, idx) => {
            const whatsapp = resolveWhatsAppLink(
              { propertyWhatsApp: prop.contactWhatsApp, agencyPhone: prop.contactPhone },
              propertyWhatsAppMessage(prop.title)
            );
            return (
              <Reveal key={prop.id} delay={Math.min(idx * 0.06, 0.3)}>
                <div className="group bg-[#07141E] rounded-2xl overflow-hidden border border-slate-800 hover:border-[#E7C878]/50 shadow-xl transition-all duration-300 hover:-translate-y-1 flex flex-col justify-between h-full">
                {/* Image & Badges */}
                <div
                  className="relative h-56 overflow-hidden cursor-pointer"
                  onClick={() => handleOpenProperty(prop)}
                >
                  <ImageWithFallback
                    src={prop.images?.[0]}
                    alt={prop.title}
                    className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                  />
                  <div className="absolute inset-0 bg-gradient-to-t from-[#07141E] via-transparent to-black/30" />

                  {/* Top Badges */}
                  <div className="absolute top-3 left-3 flex flex-wrap gap-1.5">
                    <span className="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-[#E7C878] text-[#0B1220] shadow">
                      {prop.type}
                    </span>
                    {prop.status === 'Hot Deal' && (
                      <span className="px-2 py-0.5 rounded-full text-[11px] font-bold bg-rose-600 text-white flex items-center gap-1 shadow">
                        <Flame className="w-3 h-3 fill-current" /> Hot Deal
                      </span>
                    )}
                    {prop.possessionReady && (
                      <span className="px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-700/90 text-white backdrop-blur-md">
                        Ready Possession
                      </span>
                    )}
                  </div>

                  {/* Price Tag overlay with per-sq-yd reference */}
                  <div className="absolute bottom-3 left-3 flex flex-col">
                    <span className="px-3 py-1 rounded-lg bg-black/80 backdrop-blur-md border border-[#E7C878]/30 font-serif-luxury font-bold text-base text-[#F6E3B4] shadow">
                      {prop.formattedPrice}
                    </span>
                    <span className="mt-1 px-2 text-[10px] text-slate-300 bg-black/60 rounded inline-block">
                      PKR {Math.round(prop.pricePKR / Math.max(prop.sizeSqYards, 1)).toLocaleString()} / Sq. Yd
                    </span>
                  </div>

                  {/* Wishlist toggle (persisted) */}
                  <div className="absolute top-3 right-3">
                    <FavoriteHeart id={prop.id} title={prop.title} />
                  </div>
                </div>

                {/* Details Body */}
                <div className="p-5 flex-1 flex flex-col justify-between space-y-4">
                  <div className="space-y-2">
                    <div className="flex items-center gap-1.5 text-xs text-[#E7C878]">
                      <MapPin className="w-3.5 h-3.5 shrink-0" />
                      <span className="truncate">{prop.location}</span>
                    </div>

                    <h3 className="font-serif-luxury text-lg font-bold text-white group-hover:text-[#E7C878] transition-colors line-clamp-1">
                      <a
                        href={propertyHref(prop.id)}
                        onClick={onPropertyLinkClick(prop.id)}
                        className="hover:text-[#E7C878] transition-colors"
                      >
                        {prop.title}
                      </a>
                    </h3>

                    <p className="text-xs text-slate-400 line-clamp-2 leading-relaxed">
                      {prop.description}
                    </p>
                  </div>

                  {/* Size & Spec Badges */}
                  <div className="flex flex-wrap items-center gap-2 py-2 border-t border-b border-slate-800/80 text-xs text-slate-300">
                    <span className="inline-flex items-center gap-1 px-2 py-1 rounded bg-[#0B1220] border border-slate-700">
                      <Maximize2 className="w-3 h-3 text-[#E7C878]" />
                      <strong>{prop.sizeSqYards}</strong> Sq. Yds
                    </span>
                    {prop.westOpen && (
                      <span className="px-2 py-1 rounded bg-[#0B1220] border border-slate-700 text-[11px] text-slate-300">
                        West Open
                      </span>
                    )}
                    {prop.cornerPlot && (
                      <span className="px-2 py-1 rounded bg-[#0B1220] border border-slate-700 text-[11px] text-slate-300">
                        Corner Plot
                      </span>
                    )}
                    {prop.parkFacing && (
                      <span className="px-2 py-1 rounded bg-[#0B1220] border border-slate-700 text-[11px] text-slate-300">
                        Park Facing
                      </span>
                    )}
                    {prop.bedrooms && (
                      <span className="inline-flex items-center gap-1 px-2 py-1 rounded bg-[#0B1220] border border-slate-700">
                        <Home className="w-3 h-3 text-[#E7C878]" />
                        {prop.bedrooms} Bed
                      </span>
                    )}
                  </div>

                  {/* Actions */}
                  <div className="pt-2 flex flex-col gap-2">
                    <a
                      href={propertyHref(prop.id)}
                      onClick={onPropertyLinkClick(prop.id)}
                      className="w-full py-2.5 px-3 rounded-xl bg-[#E7C878] hover:bg-[#F3C464] text-[#0B1220] font-bold text-xs transition-colors flex items-center justify-center gap-1.5 cursor-pointer shadow-md shadow-[#E7C878]/20"
                    >
                      <span>View Details &amp; Dossier</span>
                      <ArrowRight className="w-3.5 h-3.5" />
                    </a>
                    {whatsapp && (
                      <WhatsAppCta
                        href={whatsapp.url}
                        label="Ask on WhatsApp"
                        variant="outline"
                        className="w-full py-2.5 text-xs border-emerald-600/40"
                        title={`Ask about ${prop.title || 'this property'} on WhatsApp`}
                      />
                    )}
                  </div>
                </div>
                </div>
              </Reveal>
            );
          })}
        </div>
      </div>
    </section>
  );
};
