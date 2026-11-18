import React, { useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import {
  ArrowLeft, BadgeCheck, Building2, Globe, Mail, MapPin, Phone, MessageCircle,
  X, ChevronLeft, ChevronRight, Home, BedDouble, Bath, Ruler, ShieldCheck, Eye,
} from 'lucide-react';
import { api } from '../../services/api';
import { useApp } from '../../context/AppContext';
import { ImageWithFallback } from '../common/ImageWithFallback';

type Listing = {
  id: string;
  title?: string;
  description?: string;
  pricePKR?: number;
  formattedPrice?: string;
  location?: string;
  phase?: string;
  sector?: string;
  block?: string;
  sizeSqYards?: number;
  type?: string;
  status?: string;
  images?: string[];
  isFeatured?: boolean;
  bedrooms?: number;
  bathrooms?: number;
  possessionReady?: boolean;
  mdaApproved?: boolean;
  plotNumber?: string;
  fileNumber?: string;
  listing_purpose?: string;
  published_at?: string;
  slug?: string;
  videoUrl?: string;
  features?: string[];
  contactPerson?: string;
  contactPhone?: string;
  contactWhatsApp?: string;
  agencyName?: string;
  agency_id?: string;
};

type AgencyProfile = {
  id: string;
  name: string;
  slug?: string;
  logo_url?: string | null;
  cover_url?: string | null;
  description?: string | null;
  email?: string | null;
  phone?: string | null;
  whatsapp?: string | null;
  city?: string | null;
  office_address?: string | null;
  website?: string | null;
  verification_status?: string;
  member_count?: number;
  listing_count?: number;
  sale_listing_count?: number;
  rent_listing_count?: number;
  since?: string | null;
  stats?: { public_listings?: number; sale_listings?: number; rent_listings?: number; team_members?: number };
  listings?: { data?: Listing[]; current_page?: number; last_page?: number; total?: number } | Listing[];
};

const formatPrice = (p?: number, formatted?: string) => {
  if (formatted) return formatted;
  if (!p) return 'Price on request';
  if (p >= 10000000) return `PKR ${(p / 10000000).toFixed(2)} Crore`;
  if (p >= 100000) return `PKR ${(p / 100000).toFixed(2)} Lac`;
  return `PKR ${p.toLocaleString()}`;
};

const PropertyQuickViewModal: React.FC<{
  listing: Listing | null;
  agencyName?: string;
  onClose: () => void;
  onContact: (listing: Listing) => void;
}> = ({ listing, agencyName, onClose, onContact }) => {
  const [index, setIndex] = useState(0);
  useEffect(() => { setIndex(0); }, [listing?.id]);
  useEffect(() => {
    if (!listing) return;
    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
      if (e.key === 'ArrowRight') setIndex((i) => Math.min(i + 1, Math.max((listing.images?.length || 1) - 1, 0)));
      if (e.key === 'ArrowLeft') setIndex((i) => Math.max(i - 1, 0));
    };
    window.addEventListener('keydown', onKey);
    return () => { document.body.style.overflow = prev; window.removeEventListener('keydown', onKey); };
  }, [listing, onClose]);

  if (!listing || typeof document === 'undefined') return null;
  const images = (listing.images && listing.images.length ? listing.images : []).filter(Boolean);
  const hasImages = images.length > 0;
  const purpose = listing.listing_purpose === 'rent' ? 'For Rent' : 'For Sale';

  return createPortal(
    <div className="fixed inset-0 z-[120] flex items-center justify-center p-3 sm:p-6 bg-black/85 backdrop-blur-md" onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="relative w-full max-w-5xl max-h-[92vh] bg-[#101C2C] border border-[#C5A46D]/30 rounded-2xl overflow-hidden shadow-2xl flex flex-col" role="dialog" aria-modal="true" aria-label={listing.title || 'Property details'}>
        <button onClick={onClose} className="absolute top-3 right-3 z-10 p-2 rounded-xl bg-black/60 hover:bg-black/80 text-white" aria-label="Close"><X className="w-5 h-5" /></button>
        <div className="grid grid-cols-1 lg:grid-cols-2 overflow-y-auto">
          <div className="relative bg-[#0B1220] min-h-[260px] sm:min-h-[360px]">
            {hasImages ? (
              <>
                <ImageWithFallback src={images[index]} alt={listing.title || 'Property image'} className="w-full h-full object-cover min-h-[260px] sm:min-h-[360px]" />
                {images.length > 1 && (
                  <>
                    <button onClick={() => setIndex((i) => (i - 1 + images.length) % images.length)} className="absolute left-3 top-1/2 -translate-y-1/2 p-2 rounded-full bg-black/60 text-white"><ChevronLeft className="w-5 h-5" /></button>
                    <button onClick={() => setIndex((i) => (i + 1) % images.length)} className="absolute right-3 top-1/2 -translate-y-1/2 p-2 rounded-full bg-black/60 text-white"><ChevronRight className="w-5 h-5" /></button>
                    <div className="absolute bottom-3 right-3 px-2.5 py-1 rounded-full bg-black/70 text-white text-xs font-semibold">{index + 1} / {images.length}</div>
                  </>
                )}
              </>
            ) : (
              <div className="w-full h-full min-h-[260px] flex items-center justify-center text-slate-500"><Home className="w-12 h-12" /></div>
            )}
            <span className="absolute top-3 left-3 px-2.5 py-1 rounded-full text-xs font-bold bg-[#C5A46D] text-[#101C2C]">{purpose}</span>
          </div>

          <div className="p-5 sm:p-7 space-y-5">
            <div className="space-y-2">
              <p className="text-xs uppercase tracking-wider text-[#C5A46D] font-semibold">{listing.type || 'Property'}</p>
              <h2 className="font-serif-luxury text-2xl font-bold text-white leading-snug">{listing.title || 'Property listing'}</h2>
              <p className="text-[#F9E7BA] font-serif-luxury text-2xl font-bold">{formatPrice(listing.pricePKR, listing.formattedPrice)}</p>
              <p className="text-sm text-slate-300 flex items-center gap-1.5"><MapPin className="w-4 h-4 text-[#C5A46D]" />{[listing.location, listing.sector, listing.block].filter(Boolean).join(' · ') || 'Location on request'}</p>
            </div>

            <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
              <div className="rounded-lg bg-[#0B1220] border border-slate-800 p-2.5 text-center">
                <Ruler className="w-4 h-4 text-[#C5A46D] mx-auto mb-1" />
                <div className="text-xs font-bold text-white">{listing.sizeSqYards ? `${listing.sizeSqYards} sq yd` : '—'}</div>
                <div className="text-[10px] text-slate-400">Size</div>
              </div>
              <div className="rounded-lg bg-[#0B1220] border border-slate-800 p-2.5 text-center">
                <BedDouble className="w-4 h-4 text-[#C5A46D] mx-auto mb-1" />
                <div className="text-xs font-bold text-white">{listing.bedrooms ?? '—'}</div>
                <div className="text-[10px] text-slate-400">Bedrooms</div>
              </div>
              <div className="rounded-lg bg-[#0B1220] border border-slate-800 p-2.5 text-center">
                <Bath className="w-4 h-4 text-[#C5A46D] mx-auto mb-1" />
                <div className="text-xs font-bold text-white">{listing.bathrooms ?? '—'}</div>
                <div className="text-[10px] text-slate-400">Bathrooms</div>
              </div>
              <div className="rounded-lg bg-[#0B1220] border border-slate-800 p-2.5 text-center">
                <ShieldCheck className="w-4 h-4 text-[#C5A46D] mx-auto mb-1" />
                <div className="text-xs font-bold text-white">{listing.possessionReady ? 'Ready' : '—'}</div>
                <div className="text-[10px] text-slate-400">Possession</div>
              </div>
            </div>

            <div className="flex flex-wrap gap-2 text-xs">
              {listing.plotNumber && <span className="px-2.5 py-1 rounded-lg bg-[#0B1220] border border-slate-700 text-slate-200">Plot {listing.plotNumber}</span>}
              {listing.fileNumber && <span className="px-2.5 py-1 rounded-lg bg-[#0B1220] border border-slate-700 text-slate-200">File #{listing.fileNumber}</span>}
              {listing.mdaApproved && <span className="px-2.5 py-1 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-300">MDA approved</span>}
              {listing.isFeatured && <span className="px-2.5 py-1 rounded-lg bg-[#C5A46D]/10 border border-[#C5A46D]/30 text-[#F9E7BA]">Featured</span>}
            </div>

            {listing.description && (
              <div className="space-y-1.5">
                <h3 className="text-sm font-semibold text-white">Description</h3>
                <p className="text-sm text-slate-300 leading-relaxed whitespace-pre-line">{listing.description}</p>
              </div>
            )}

            {Array.isArray(listing.features) && listing.features.length > 0 && (
              <div className="space-y-1.5">
                <h3 className="text-sm font-semibold text-white">Highlights</h3>
                <div className="flex flex-wrap gap-1.5">
                  {listing.features.slice(0, 12).map((f, i) => (
                    <span key={i} className="px-2 py-1 rounded-md bg-slate-800 text-slate-200 text-xs">{f}</span>
                  ))}
                </div>
              </div>
            )}

            <div className="pt-2 border-t border-slate-800 flex flex-col sm:flex-row gap-2">
              <button onClick={() => onContact(listing)} className="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C] font-bold text-sm">
                <Phone className="w-4 h-4" /> Contact {agencyName || 'agency'}
              </button>
              <a href={`/properties?property=${encodeURIComponent(listing.id)}`} onClick={(e) => { e.preventDefault(); window.history.pushState({}, '', `/properties?property=${encodeURIComponent(listing.id)}`); window.dispatchEvent(new PopStateEvent('popstate')); }} className="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-slate-700 text-slate-200 hover:text-white text-sm font-semibold">
                <Eye className="w-4 h-4" /> Open full page
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>,
    document.body
  );
};

const PropertyCard: React.FC<{ listing: Listing; onView: () => void; onContact: () => void }> = ({ listing, onView, onContact }) => {
  const image = listing.images?.[0];
  const purpose = listing.listing_purpose === 'rent' ? 'For Rent' : 'For Sale';
  return (
    <article className="group rounded-2xl border border-slate-800 bg-[#101C2C] overflow-hidden hover:border-[#C5A46D]/50 transition-all shadow-lg flex flex-col sm:flex-row">
      <div className="relative w-full sm:w-64 md:w-72 h-52 sm:h-auto shrink-0 bg-[#0B1220] cursor-pointer" onClick={onView}>
        {image ? (
          <ImageWithFallback src={image} alt={listing.title || 'Property'} className="w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-500" loading="lazy" />
        ) : (
          <div className="w-full h-full flex items-center justify-center text-slate-600"><Home className="w-10 h-10" /></div>
        )}
        <span className="absolute top-3 left-3 px-2.5 py-1 rounded-full text-xs font-bold bg-[#C5A46D] text-[#101C2C]">{purpose}</span>
        {Array.isArray(listing.images) && listing.images.length > 1 && (
          <span className="absolute bottom-3 right-3 px-2 py-0.5 rounded-md bg-black/70 text-white text-[11px] font-semibold">{listing.images.length} photos</span>
        )}
      </div>
      <div className="flex-1 p-4 sm:p-5 flex flex-col gap-3">
        <div className="space-y-1">
          <p className="text-[11px] uppercase tracking-wider text-[#C5A46D] font-semibold">{listing.type || 'Property'}{listing.sector ? ` · ${listing.sector}` : ''}</p>
          <h3 className="font-serif-luxury text-lg font-bold text-white leading-snug line-clamp-2 group-hover:text-[#C5A46D] transition-colors">{listing.title || 'Property listing'}</h3>
          <p className="text-[#F9E7BA] font-serif-luxury text-xl font-bold">{formatPrice(listing.pricePKR, listing.formattedPrice)}</p>
          <p className="text-sm text-slate-400 flex items-center gap-1.5"><MapPin className="w-3.5 h-3.5 text-[#C5A46D]" /><span className="line-clamp-1">{listing.location || listing.sector || 'Location on request'}</span></p>
        </div>
        <div className="flex flex-wrap items-center gap-3 text-xs text-slate-300">
          {listing.sizeSqYards ? <span className="inline-flex items-center gap-1"><Ruler className="w-3.5 h-3.5 text-[#C5A46D]" /> {listing.sizeSqYards} sq yd</span> : null}
          {listing.bedrooms ? <span className="inline-flex items-center gap-1"><BedDouble className="w-3.5 h-3.5 text-[#C5A46D]" /> {listing.bedrooms} beds</span> : null}
          {listing.bathrooms ? <span className="inline-flex items-center gap-1"><Bath className="w-3.5 h-3.5 text-[#C5A46D]" /> {listing.bathrooms} baths</span> : null}
          {listing.plotNumber ? <span className="px-2 py-0.5 rounded bg-[#0B1220] border border-slate-700">Plot {listing.plotNumber}</span> : null}
        </div>
        {listing.description ? <p className="text-sm text-slate-400 line-clamp-2">{listing.description}</p> : null}
        <div className="mt-auto pt-2 flex flex-wrap gap-2">
          <button onClick={onView} className="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C] font-bold text-sm">View details</button>
          <button onClick={onContact} className="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl border border-slate-700 text-slate-200 hover:text-white hover:border-slate-500 text-sm font-semibold">Contact</button>
        </div>
      </div>
    </article>
  );
};

export const AgencyProfilePage: React.FC<{ slug: string }> = ({ slug }) => {
  const { setCurrentView, showToast } = useApp();
  const [agency, setAgency] = useState<AgencyProfile | null>(null);
  const [loading, setLoading] = useState(true);
  const [notFound, setNotFound] = useState(false);
  const [selected, setSelected] = useState<Listing | null>(null);

  useEffect(() => {
    let active = true;
    (async () => {
      setLoading(true);
      setNotFound(false);
      const body = await api.getPublicAgency(slug, { per_page: 50, purpose: 'sale' });
      if (!active) return;
      setLoading(false);
      if (!body || body.success === false || !body.agency) {
        setNotFound(true);
        setAgency(null);
        return;
      }
      setAgency(body.agency as AgencyProfile);
    })();
    return () => { active = false; };
  }, [slug]);

  const listings: Listing[] = useMemo(() => {
    const raw = agency?.listings;
    const rows = Array.isArray(raw) ? raw : raw && Array.isArray(raw.data) ? raw.data : [];
    return rows.filter((listing) => !listing.listing_purpose || listing.listing_purpose === 'sale');
  }, [agency]);

  const scrollToContact = (listing?: Listing) => {
    if (listing) setSelected(null);
    document.getElementById('agency-contact')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  const backToDirectory = () => {
    window.history.pushState({}, '', '/agencies');
    window.dispatchEvent(new PopStateEvent('popstate'));
    setCurrentView('agencies');
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-[#0B1220] pt-10 pb-20 px-4 sm:px-6 lg:px-8">
        <div className="max-w-6xl mx-auto space-y-6 animate-pulse">
          <div className="h-56 rounded-2xl bg-slate-800/50" />
          <div className="h-8 w-1/3 bg-slate-800 rounded" />
          <div className="h-32 rounded-2xl bg-slate-800/40" />
        </div>
      </div>
    );
  }

  if (notFound || !agency) {
    return (
      <div className="min-h-screen bg-[#0B1220] pt-16 pb-20 px-4 text-center">
        <div className="max-w-lg mx-auto space-y-4">
          <Building2 className="w-12 h-12 text-slate-600 mx-auto" />
          <h1 className="text-white text-2xl font-bold">Agency not found</h1>
          <p className="text-slate-400">This agency profile is unavailable or no longer public.</p>
          <button onClick={backToDirectory} className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#C5A46D] text-[#101C2C] font-bold"><ArrowLeft className="w-4 h-4" /> Back to agencies</button>
        </div>
      </div>
    );
  }

  const initials = agency.name.split(' ').map((p) => p[0]).join('').slice(0, 2).toUpperCase();
  const stats = agency.stats || {
    public_listings: agency.listing_count ?? listings.length,
    sale_listings: agency.sale_listing_count ?? listings.filter((l) => l.listing_purpose === 'sale').length,
    rent_listings: agency.rent_listing_count ?? listings.filter((l) => l.listing_purpose === 'rent').length,
    team_members: agency.member_count ?? 0,
  };

  return (
    <div className="min-h-screen bg-[#0B1220] pb-20">
      <div className="relative h-56 sm:h-72 lg:h-80 bg-gradient-to-br from-slate-800 via-[#101C2C] to-[#0B1220]">
        {agency.cover_url ? <img src={agency.cover_url} alt="" className="w-full h-full object-cover opacity-70" /> : null}
        <div className="absolute inset-0 bg-gradient-to-t from-[#0B1220] via-[#0B1220]/40 to-transparent" />
        <div className="absolute top-4 left-4">
          <button onClick={backToDirectory} className="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-black/50 backdrop-blur text-white text-sm hover:bg-black/70 border border-white/10">
            <ArrowLeft className="w-4 h-4" /> Agencies
          </button>
        </div>
      </div>

      <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 -mt-16 relative z-10 space-y-8">
        <section className="rounded-2xl border border-slate-800 bg-[#101C2C]/95 backdrop-blur p-5 sm:p-7 shadow-2xl">
          <div className="flex flex-col sm:flex-row sm:items-end gap-5">
            <div className="w-20 h-20 sm:w-24 sm:h-24 rounded-2xl border-4 border-[#101C2C] bg-[#0B1220] overflow-hidden flex items-center justify-center shadow-xl shrink-0">
              {agency.logo_url ? <img src={agency.logo_url} alt={`${agency.name} logo`} className="w-full h-full object-cover" /> : <span className="text-[#C5A46D] font-bold text-xl">{initials}</span>}
            </div>
            <div className="flex-1 min-w-0 space-y-2">
              <div className="flex flex-wrap items-center gap-2">
                <h1 className="font-serif-luxury text-2xl sm:text-3xl font-bold text-white">{agency.name}</h1>
                {agency.verification_status === 'verified' && (
                  <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">
                    <BadgeCheck className="w-3.5 h-3.5" /> Verified agency
                  </span>
                )}
              </div>
              <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-300">
                {(agency.city || agency.office_address) && <span className="inline-flex items-center gap-1.5"><MapPin className="w-4 h-4 text-[#C5A46D]" /> {agency.city || agency.office_address}</span>}
                {agency.since && <span className="text-slate-400">Active since {agency.since}</span>}
              </div>
              {agency.description && <p className="text-slate-300 text-sm sm:text-base leading-relaxed max-w-3xl">{agency.description}</p>}
            </div>
            <div className="flex flex-wrap gap-2">
              {agency.phone && <a href={`tel:${agency.phone}`} className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C] font-bold text-sm"><Phone className="w-4 h-4" /> Call</a>}
              {agency.whatsapp && <a href={`https://wa.me/${agency.whatsapp.replace(/\D/g, '')}`} target="_blank" rel="noreferrer" className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm"><MessageCircle className="w-4 h-4" /> WhatsApp</a>}
            </div>
          </div>
        </section>

        <section className="grid grid-cols-2 sm:grid-cols-4 gap-3">
          {[
            { label: 'For sale', value: stats.sale_listings ?? 0 },
            { label: 'Team members', value: stats.team_members ?? 0 },
          ].map((item) => (
            <div key={item.label} className="rounded-xl border border-slate-800 bg-[#101C2C] p-4 text-center">
              <div className="text-xl sm:text-2xl font-bold text-white">{item.value}</div>
              <div className="text-[11px] uppercase tracking-wide text-slate-400 mt-1">{item.label}</div>
            </div>
          ))}
        </section>

        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
          <div className="lg:col-span-8 space-y-5">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <div>
                <h2 className="font-serif-luxury text-2xl font-bold text-white">Properties by {agency.name}</h2>
                <p className="text-sm text-slate-400">Only approved, publicly visible listings are shown here.</p>
              </div>
              <span className="px-3 py-1.5 rounded-lg text-xs font-semibold bg-[#C5A46D]/10 text-[#C5A46D] border border-[#C5A46D]/30">Properties for sale</span>
            </div>

            {listings.length === 0 ? (
              <div className="rounded-2xl border border-slate-800 bg-[#101C2C] p-10 text-center space-y-2">
                <Home className="w-10 h-10 text-slate-600 mx-auto" />
                <h3 className="text-white font-semibold">No public listings right now</h3>
                <p className="text-slate-400 text-sm">This agency has no approved public listings matching the selected filter. Contact them directly for availability.</p>
              </div>
            ) : (
              <div className="space-y-4">
                {listings.map((listing) => (
                  <PropertyCard
                    key={listing.id}
                    listing={listing}
                    onView={() => setSelected(listing)}
                    onContact={() => scrollToContact(listing)}
                  />
                ))}
              </div>
            )}
          </div>

          <aside className="lg:col-span-4 space-y-5">
            <section className="rounded-2xl border border-slate-800 bg-[#101C2C] p-5 space-y-4">
              <h3 className="font-serif-luxury text-lg font-bold text-white">Contact information</h3>
              <div className="space-y-3 text-sm">
                {agency.office_address && <div className="flex gap-2.5 text-slate-300"><MapPin className="w-4 h-4 text-[#C5A46D] mt-0.5 shrink-0" /><span>{agency.office_address}{agency.city ? `, ${agency.city}` : ''}</span></div>}
                {agency.phone && <a href={`tel:${agency.phone}`} className="flex gap-2.5 text-slate-300 hover:text-white"><Phone className="w-4 h-4 text-[#C5A46D] mt-0.5" /><span>{agency.phone}</span></a>}
                {agency.whatsapp && <a href={`https://wa.me/${agency.whatsapp.replace(/\D/g, '')}`} target="_blank" rel="noreferrer" className="flex gap-2.5 text-slate-300 hover:text-white"><MessageCircle className="w-4 h-4 text-[#C5A46D] mt-0.5" /><span>WhatsApp: {agency.whatsapp}</span></a>}
                {agency.email && <a href={`mailto:${agency.email}`} className="flex gap-2.5 text-slate-300 hover:text-white"><Mail className="w-4 h-4 text-[#C5A46D] mt-0.5" /><span className="break-all">{agency.email}</span></a>}
                {agency.website && <a href={agency.website.startsWith('http') ? agency.website : `https://${agency.website}`} target="_blank" rel="noreferrer" className="flex gap-2.5 text-slate-300 hover:text-white"><Globe className="w-4 h-4 text-[#C5A46D] mt-0.5" /><span className="break-all">{agency.website}</span></a>}
              </div>
            </section>

            <section id="agency-contact" className="rounded-2xl border border-[#C5A46D]/25 bg-[#101C2C] p-5 space-y-4">
              <h3 className="font-serif-luxury text-lg font-bold text-white">Send an inquiry</h3>
              <p className="text-sm text-slate-400">Interested in this agency's listings? Reach out directly using the contact options above, or browse their properties and submit an inquiry from a listing.</p>
              <button onClick={() => { window.history.pushState({}, '', '/contact'); window.dispatchEvent(new PopStateEvent('popstate')); }} className="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-slate-700 text-slate-200 hover:text-white font-semibold text-sm">
                Open contact page
              </button>
            </section>
          </aside>
        </div>
      </div>

      <PropertyQuickViewModal
        listing={selected}
        agencyName={agency.name}
        onClose={() => setSelected(null)}
        onContact={scrollToContact}
      />
    </div>
  );
};
