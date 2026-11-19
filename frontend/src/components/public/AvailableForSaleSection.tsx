import React from 'react';
import { useApp } from '../../context/AppContext';
import { ArrowRight, MapPin, Maximize2, Heart, BadgeCheck } from 'lucide-react';
import { ImageWithFallback } from '../common/ImageWithFallback';
import { formatPKRCurrency } from '../../utils/logic';

/**
 * Light "Available for Sale" band on the public homepage.
 *
 * The reference theme places the sale inventory on an ivory/white surface with
 * white cards between the dark hero and the dark feature sections. Cards are
 * real anchors (crawlable / middle-clickable) with an SPA transition on a
 * plain left-click, matching the listings page behaviour.
 */
export const AvailableForSaleSection: React.FC = () => {
  const { properties, setCurrentView, setSelectedPropertyId } = useApp();

  const listings = properties
    .filter((property) => !property.deletedAt)
    .slice(0, 4);

  if (listings.length === 0) return null;

  const openProperty = (id: string) => {
    setSelectedPropertyId(id);
    setCurrentView('property-detail');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const onCardClick = (id: string) => (e: React.MouseEvent<HTMLAnchorElement>) => {
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
    e.preventDefault();
    openProperty(id);
  };

  return (
    <section className="sale-showcase surface-ivory py-20 sm:py-24 px-4 sm:px-6 lg:px-8">
      <div className="max-w-7xl mx-auto">
        <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-10">
          <div className="max-w-3xl">
            <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-[#E7C878]/20 text-[#8A6A1F] border border-[#E7C878]/60">
              <BadgeCheck className="w-3.5 h-3.5" /> Available for Sale
            </span>
            <h2 className="font-serif-luxury text-3xl sm:text-4xl lg:text-5xl font-bold text-[#0B1220] mt-4">
              Taiser Town Properties for Sale
            </h2>
            <p className="text-[#475569] text-sm sm:text-base mt-3">
              Find the best residential and commercial properties in Taiser Town.
            </p>
          </div>
          <a
            href="/properties?purpose=sale"
            onClick={(e) => {
              if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
              e.preventDefault();
              setCurrentView('properties');
            }}
            className="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl border border-[#B98A2F] text-[#8A6A1F] hover:bg-[#E7C878] hover:text-[#0B1220] font-bold text-sm transition-colors shrink-0"
          >
            View All Properties <ArrowRight className="w-4 h-4" />
          </a>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          {listings.map((property) => {
            const image = property.images?.[0];
            const href = `/properties?property=${encodeURIComponent(property.id)}`;
            return (
              <article
                key={property.id}
                className="sale-card card-light rounded-2xl overflow-hidden flex flex-col transition-transform duration-300 hover:-translate-y-1"
              >
                <a
                  href={href}
                  onClick={onCardClick(property.id)}
                  className="relative block aspect-[4/3] overflow-hidden bg-[#0B1220]"
                >
                  <ImageWithFallback
                    src={image}
                    alt={property.title}
                    className="w-full h-full object-cover"
                  />
                  <span className="absolute top-3 left-3 inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-[#F3C464] text-[#0B1220] text-[11px] font-bold shadow">
                    For Sale
                  </span>
                  <span className="absolute top-3 right-3 inline-flex items-center justify-center w-8 h-8 rounded-full bg-white/90 text-[#0B1220] shadow">
                    <Heart className="w-4 h-4" />
                  </span>
                </a>

                <div className="p-5 flex flex-col gap-2 flex-1">
                  <h3 className="font-serif-luxury text-lg font-bold text-[#0B1220] line-clamp-1">
                    <a
                      href={href}
                      onClick={onCardClick(property.id)}
                      className="hover:text-[#8A6A1F] transition-colors"
                    >
                      {property.title}
                    </a>
                  </h3>
                  <p className="text-xs text-[#475569] flex items-center gap-1 line-clamp-1">
                    <MapPin className="w-3.5 h-3.5 text-[#B98A2F] shrink-0" />
                    {property.location || `${property.sector || 'Taiser Town'}, Karachi`}
                  </p>
                  <div className="text-lg font-bold text-[#B98A2F]">
                    {property.formattedPrice || formatPKRCurrency(property.pricePKR)}
                  </div>
                  <p className="text-xs text-[#475569] flex items-center gap-1">
                    <Maximize2 className="w-3.5 h-3.5 text-[#B98A2F] shrink-0" />
                    {property.sizeSqYards ? `${property.sizeSqYards} Sq. Yds` : property.type}
                    {property.bedrooms ? ` · ${property.bedrooms} Beds` : ''}
                    {property.bathrooms ? ` · ${property.bathrooms} Baths` : ''}
                  </p>

                  <a
                    href={href}
                    onClick={onCardClick(property.id)}
                    className="mt-auto inline-flex items-center justify-center gap-1.5 w-full py-2.5 rounded-xl border border-[#B98A2F] text-[#8A6A1F] hover:bg-[#F3C464] hover:text-[#0B1220] font-bold text-xs transition-colors"
                  >
                    View Details <ArrowRight className="w-3.5 h-3.5" />
                  </a>
                </div>
              </article>
            );
          })}
        </div>
      </div>
    </section>
  );
};
