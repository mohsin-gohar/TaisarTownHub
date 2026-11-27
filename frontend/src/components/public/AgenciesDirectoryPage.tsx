import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Building2, Search, BadgeCheck, MapPin, ArrowRight, Filter, RefreshCw } from 'lucide-react';
import { api } from '../../services/api';
import { useApp } from '../../context/AppContext';
import { WhatsAppCta } from '../common/WhatsAppCta';
import { AGENCY_WHATSAPP_MESSAGE, resolveWhatsAppLink } from '../../utils/whatsapp';

interface AgencyCardData {
  id: string;
  name: string;
  slug?: string;
  logo_url?: string | null;
  cover_url?: string | null;
  description?: string | null;
  city?: string | null;
  office_address?: string | null;
  verification_status?: string;
  member_count?: number;
  listing_count?: number;
  sale_listing_count?: number;
  rent_listing_count?: number;
  since?: string | null;
  phone?: string | null;
  whatsapp?: string | null;
  website?: string | null;
}

const SkeletonCard = () => (
  <div className="rounded-2xl border border-slate-800 bg-[#0B1220] overflow-hidden animate-pulse">
    <div className="h-28 bg-slate-800/60" />
    <div className="p-5 space-y-3">
      <div className="h-4 w-2/3 bg-slate-800 rounded" />
      <div className="h-3 w-full bg-slate-800 rounded" />
      <div className="h-3 w-4/5 bg-slate-800 rounded" />
      <div className="h-9 w-full bg-slate-800 rounded mt-4" />
    </div>
  </div>
);

export const AgenciesDirectoryPage: React.FC = () => {
  const { setCurrentView, showToast } = useApp();
  const [agencies, setAgencies] = useState<AgencyCardData[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [search, setSearch] = useState('');
  const [city, setCity] = useState('');
  const [hasListings, setHasListings] = useState(false);
  const [sort, setSort] = useState<'name' | 'newest' | 'listings'>('name');
  const [page, setPage] = useState(1);
  const [pagination, setPagination] = useState<any>(null);
  const requestSeq = useRef(0);

  const load = async (nextPage = 1) => {
    const seq = ++requestSeq.current;
    setLoading(true);
    setError('');
    const body = await api.getPublicAgencies({
      search: search.trim() || undefined,
      city: city.trim() || undefined,
      has_listings: hasListings ? 1 : undefined,
      sort,
      page: nextPage,
      per_page: 12,
    });
    if (seq !== requestSeq.current) return;
    setLoading(false);
    if (!body || body.success === false) {
      setError(body?.message || 'Could not load agencies right now.');
      setAgencies([]);
      setPagination(null);
      return;
    }
    const items = Array.isArray(body.agencies?.data) ? body.agencies.data : Array.isArray(body.agencies) ? body.agencies : [];
    setAgencies(items);
    setPagination(body.agencies?.current_page ? body.agencies : null);
    setPage(nextPage);
  };

  useEffect(() => {
    void load(1);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [sort, hasListings]);

  const cities = useMemo(() => {
    const set = new Set<string>();
    agencies.forEach((a) => { if (a.city) set.add(a.city); });
    if (city && !set.has(city)) set.add(city);
    return Array.from(set).sort();
  }, [agencies, city]);

  const openAgency = (agency: AgencyCardData) => {
    const slug = agency.slug || agency.id;
    window.history.pushState({}, '', `/agencies/${encodeURIComponent(slug)}`);
    window.dispatchEvent(new PopStateEvent('popstate'));
    // Fallback for environments not reacting to synthetic popstate:
    setCurrentView('agency-profile');
  };

  return (
    <div className="min-h-screen bg-[#07141E] pt-10 pb-20 px-4 sm:px-6 lg:px-8">
      <div className="max-w-7xl mx-auto space-y-8">
        <div className="flex flex-col lg:flex-row lg:items-end justify-between gap-6">
          <div className="space-y-3 max-w-3xl">
            <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-[#E7C878]/15 text-[#E7C878] border border-[#E7C878]/30">
              <Building2 className="w-3.5 h-3.5" /> Verified Agency Network
            </span>
            <h1 className="font-serif-luxury text-3xl sm:text-4xl lg:text-5xl font-bold text-white">
              Find Trusted Real Estate Agencies
            </h1>
            <p className="text-slate-400 text-sm sm:text-base">
              Browse verified agencies, explore their public sale listings, and connect with the right team for Taisar Town.
            </p>
          </div>
          <button
            onClick={() => void load(page)}
            className="self-start lg:self-auto inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-700 bg-[#0B1220] text-slate-200 hover:text-white hover:border-[#E7C878]/50 transition text-sm font-semibold"
          >
            <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} /> Refresh
          </button>
        </div>

        <div className="bg-[#0B1220] border border-slate-800 rounded-2xl p-4 sm:p-5 space-y-4 shadow-xl">
          <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
            <label className="md:col-span-2 space-y-1">
              <span className="text-[11px] font-semibold text-slate-300 uppercase">Search agency</span>
              <div className="relative">
                <Search className="w-4 h-4 text-slate-500 absolute left-3 top-1/2 -translate-y-1/2" />
                <input
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  onKeyDown={(e) => { if (e.key === 'Enter') void load(1); }}
                  placeholder="Agency name, city or description"
                  className="w-full bg-[#07141E] text-white text-sm rounded-xl pl-9 pr-3 py-2.5 border border-slate-700 focus:border-[#E7C878] focus:outline-none"
                />
              </div>
            </label>
            <label className="space-y-1">
              <span className="text-[11px] font-semibold text-slate-300 uppercase">City</span>
              <input
                value={city}
                onChange={(e) => setCity(e.target.value)}
                list="agency-cities"
                placeholder="Any city"
                className="w-full bg-[#07141E] text-white text-sm rounded-xl px-3 py-2.5 border border-slate-700 focus:border-[#E7C878] focus:outline-none"
              />
              <datalist id="agency-cities">
                {cities.map((c) => <option key={c} value={c} />)}
              </datalist>
            </label>
            <label className="space-y-1">
              <span className="text-[11px] font-semibold text-slate-300 uppercase">Sort by</span>
              <select
                value={sort}
                onChange={(e) => setSort(e.target.value as any)}
                className="w-full bg-[#07141E] text-white text-sm rounded-xl px-3 py-2.5 border border-slate-700 focus:border-[#E7C878] focus:outline-none"
              >
                <option value="name">Name</option>
                <option value="newest">Newest</option>
                <option value="listings">Most public listings</option>
              </select>
            </label>
          </div>
          <div className="flex flex-wrap items-center justify-between gap-3">
            <label className="inline-flex items-center gap-2 text-sm text-slate-300 cursor-pointer select-none">
              <input type="checkbox" checked={hasListings} onChange={(e) => setHasListings(e.target.checked)} className="accent-[#E7C878]" />
              <Filter className="w-4 h-4 text-[#E7C878]" /> Only agencies with public listings
            </label>
            <button
              onClick={() => void load(1)}
              className="px-5 py-2.5 rounded-xl bg-[#E7C878] hover:bg-[#F3C464] text-[#0B1220] font-bold text-sm transition shadow-lg shadow-[#E7C878]/20"
            >
              Search agencies
            </button>
          </div>
        </div>

        {error && (
          <div className="rounded-xl border border-rose-500/30 bg-rose-500/10 p-4 text-sm text-rose-200">
            {error}
          </div>
        )}

        {loading ? (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {Array.from({ length: 6 }).map((_, i) => <SkeletonCard key={i} />)}
          </div>
        ) : error ? null : agencies.length === 0 ? (
          <div className="rounded-2xl border border-slate-800 bg-[#0B1220] p-10 text-center space-y-3">
            <Building2 className="w-10 h-10 text-slate-600 mx-auto" />
            <h3 className="text-white font-semibold text-lg">No agencies match your filters</h3>
            <p className="text-slate-400 text-sm">Try clearing the search or city filter to see all verified agencies.</p>
            <button
              onClick={() => { setSearch(''); setCity(''); setHasListings(false); setSort('name'); void load(1); }}
              className="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-white text-sm"
            >
              Clear filters
            </button>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {agencies.map((agency) => {
              const initials = agency.name.split(' ').map((p) => p[0]).join('').slice(0, 2).toUpperCase();
              const whatsapp = resolveWhatsAppLink(
                { agencyWhatsApp: agency.whatsapp, agencyPhone: agency.phone },
                AGENCY_WHATSAPP_MESSAGE
              );
              return (
                <article key={agency.id} className="group rounded-2xl border border-slate-800 bg-[#0B1220] overflow-hidden hover:border-[#E7C878]/50 transition-all duration-300 hover:-translate-y-0.5 shadow-xl flex flex-col">
                  <div className="relative h-28 bg-gradient-to-br from-slate-800 via-[#07141E] to-slate-900">
                    {agency.cover_url ? (
                      <img src={agency.cover_url} alt="" className="w-full h-full object-cover opacity-80" loading="lazy" />
                    ) : null}
                    <div className="absolute inset-0 bg-gradient-to-t from-[#0B1220] to-transparent" />
                    <div className="absolute -bottom-6 left-5 w-14 h-14 rounded-xl border-2 border-[#0B1220] bg-[#07141E] overflow-hidden flex items-center justify-center shadow-lg">
                      {agency.logo_url ? (
                        <img src={agency.logo_url} alt={`${agency.name} logo`} className="w-full h-full object-cover" loading="lazy" />
                      ) : (
                        <span className="text-[#E7C878] font-bold text-sm">{initials}</span>
                      )}
                    </div>
                  </div>

                  <div className="pt-8 px-5 pb-5 flex-1 flex flex-col gap-4">
                    <div className="space-y-1.5">
                      <div className="flex items-start justify-between gap-2">
                        <h2 className="font-serif-luxury text-lg font-bold text-white leading-snug group-hover:text-[#E7C878] transition-colors">
                          {agency.name}
                        </h2>
                        {agency.verification_status === 'verified' && (
                          <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 shrink-0">
                            <BadgeCheck className="w-3 h-3" /> Verified
                          </span>
                        )}
                      </div>
                      {(agency.city || agency.office_address) && (
                        <p className="text-xs text-slate-400 flex items-center gap-1.5">
                          <MapPin className="w-3.5 h-3.5 text-[#E7C878]" />
                          <span className="line-clamp-1">{agency.city || agency.office_address}</span>
                        </p>
                      )}
                      {agency.description && (
                        <p className="text-sm text-slate-300 line-clamp-2 leading-relaxed">
                          {agency.description}
                        </p>
                      )}
                    </div>

                    <div className="grid grid-cols-3 gap-2 text-center">
                      <div className="rounded-lg bg-[#07141E] border border-slate-800 p-2">
                        <div className="text-sm font-bold text-white">{agency.listing_count ?? 0}</div>
                        <div className="text-[10px] text-slate-400 uppercase tracking-wide">Listings</div>
                      </div>
                      <div className="rounded-lg bg-[#07141E] border border-slate-800 p-2">
                        <div className="text-sm font-bold text-white">{agency.member_count ?? 0}</div>
                        <div className="text-[10px] text-slate-400 uppercase tracking-wide">Team</div>
                      </div>
                      <div className="rounded-lg bg-[#07141E] border border-slate-800 p-2">
                        <div className="text-sm font-bold text-white">{agency.since || '—'}</div>
                        <div className="text-[10px] text-slate-400 uppercase tracking-wide">Since</div>
                      </div>
                    </div>

                    <div className="mt-auto pt-1 flex flex-wrap items-center gap-2">
                      <button
                        onClick={() => openAgency(agency)}
                        className="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-[#E7C878] hover:bg-[#F3C464] text-[#0B1220] font-bold text-sm transition"
                      >
                        View agency profile <ArrowRight className="w-4 h-4" />
                      </button>
                      {whatsapp && (
                        <WhatsAppCta
                          href={whatsapp.url}
                          variant="outline"
                          label="WhatsApp"
                          ariaLabel={`Chat with ${agency.name} on WhatsApp`}
                          className="px-3 py-2.5 text-xs"
                        />
                      )}
                    </div>
                  </div>
                </article>
              );
            })}
          </div>
        )}

        {pagination && pagination.last_page > 1 && (
          <div className="flex items-center justify-center gap-2 pt-4">
            <button
              disabled={page <= 1 || loading}
              onClick={() => void load(page - 1)}
              className="px-4 py-2 rounded-lg border border-slate-700 bg-[#0B1220] text-slate-200 disabled:opacity-40"
            >
              Previous
            </button>
            <span className="text-sm text-slate-400">Page {pagination.current_page} of {pagination.last_page}</span>
            <button
              disabled={page >= pagination.last_page || loading}
              onClick={() => void load(page + 1)}
              className="px-4 py-2 rounded-lg border border-slate-700 bg-[#0B1220] text-slate-200 disabled:opacity-40"
            >
              Next
            </button>
          </div>
        )}

        <div className="rounded-2xl border border-[#E7C878]/20 bg-gradient-to-r from-[#0B1220] to-[#07141E] p-6 sm:p-8 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
          <div>
            <h3 className="text-white font-serif-luxury text-xl font-bold">Are you a real estate agency?</h3>
            <p className="text-slate-400 text-sm mt-1">Register your agency, get verified, and showcase your sale listings to buyers.</p>
          </div>
          <button
            onClick={() => { window.history.pushState({}, '', '/register'); window.dispatchEvent(new PopStateEvent('popstate')); }}
            className="px-5 py-2.5 rounded-xl bg-[#E7C878] hover:bg-[#F3C464] text-[#0B1220] font-bold text-sm whitespace-nowrap"
          >
            Register your agency
          </button>
        </div>
      </div>
    </div>
  );
};
