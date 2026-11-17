import React, { useEffect, useMemo, useState } from 'react';
import { Eye, EyeOff, RefreshCw, ExternalLink, ShieldCheck, AlertCircle, Home } from 'lucide-react';
import { api } from '../../services/api';
import { useApp } from '../../context/AppContext';

type Listing = {
  id: string;
  title?: string;
  location?: string;
  sector?: string;
  block?: string;
  pricePKR?: number;
  formattedPrice?: string;
  type?: string;
  listing_purpose?: string;
  approval_status?: string;
  visibility?: string;
  isFeatured?: boolean;
  status?: string;
  published_at?: string | null;
  expires_at?: string | null;
  images?: string[];
};

const formatPrice = (p?: number, formatted?: string) => {
  if (formatted) return formatted;
  if (!p) return '—';
  if (p >= 10000000) return `PKR ${(p / 10000000).toFixed(2)} Crore`;
  if (p >= 100000) return `PKR ${(p / 100000).toFixed(2)} Lac`;
  return `PKR ${p.toLocaleString()}`;
};

export const AdminProperties: React.FC = () => {
  const { showToast, confirmAction } = useApp();
  const [listings, setListings] = useState<Listing[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [busyId, setBusyId] = useState<string | null>(null);

  const load = async () => {
    setLoading(true);
    setError('');
    const data = await api.getProperties({ scope: 'agency', per_page: 200 });
    setLoading(false);
    if (!Array.isArray(data)) {
      setListings([]);
      setError('Could not load your agency listings. Make sure you are signed in with an approved agency account and have permission to view properties.');
      return;
    }
    setListings(data as Listing[]);
  };

  useEffect(() => { void load(); }, []);

  const stats = useMemo(() => {
    const approved = listings.filter((l) => l.approval_status === 'approved').length;
    const publicVisible = listings.filter((l) => l.approval_status === 'approved' && l.visibility === 'public').length;
    const hidden = listings.filter((l) => l.approval_status === 'approved' && l.visibility !== 'public').length;
    const pending = listings.filter((l) => l.approval_status === 'pending').length;
    return { total: listings.length, approved, publicVisible, hidden, pending };
  }, [listings]);

  const canPublish = (l: Listing) => l.approval_status === 'approved' && (!l.expires_at || new Date(l.expires_at).getTime() > Date.now());

  const toggleVisibility = async (listing: Listing) => {
    const next = listing.visibility === 'public' ? 'private' : 'public';
    if (next === 'public' && !canPublish(listing)) {
      showToast('error', 'Only approved, unexpired listings can be made public.');
      return;
    }

    const actionText = next === 'public' ? 'make this listing visible on the public marketplace' : 'hide this listing from the public marketplace';
    const confirmed = await confirmAction(`Do you want to ${actionText}?`, {
      title: next === 'public' ? 'Publish listing' : 'Hide listing',
      confirmLabel: next === 'public' ? 'Publish' : 'Hide',
    });
    if (!confirmed) return;

    setBusyId(listing.id);
    const res = await api.setPropertyMarketplaceVisibility(listing.id, next);
    setBusyId(null);
    if (!res.ok) {
      showToast('error', res.body?.message || 'Could not update listing visibility.');
      return;
    }
    const updated = res.body?.property || {};
    setListings((prev) => prev.map((item) => item.id === listing.id ? { ...item, ...updated, visibility: next } : item));
    showToast('success', res.body?.message || (next === 'public' ? 'Listing is now public.' : 'Listing is now hidden.'));
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
        <div className="space-y-1">
          <h2 className="font-serif-luxury text-2xl font-bold text-white">Public Listings & Marketplace Visibility</h2>
          <p className="text-sm text-slate-400">Control which of your approved listings appear on the public website and your agency profile. Approval and moderation rules are enforced by the backend.</p>
        </div>
        <div className="flex items-center gap-2">
          <button onClick={() => { window.history.pushState({}, '', '/agencies'); window.dispatchEvent(new PopStateEvent('popstate')); }} className="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-700 bg-[#101C2C] text-slate-200 hover:text-white text-sm">
            <ExternalLink className="w-4 h-4" /> View agencies
          </button>
          <button onClick={() => void load()} className="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C] font-semibold text-sm">
            <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} /> Refresh
          </button>
        </div>
      </div>

      <div className="grid grid-cols-2 lg:grid-cols-5 gap-3">
        {[
          { label: 'Total listings', value: stats.total },
          { label: 'Approved', value: stats.approved },
          { label: 'Public now', value: stats.publicVisible },
          { label: 'Hidden', value: stats.hidden },
          { label: 'Pending approval', value: stats.pending },
        ].map((item) => (
          <div key={item.label} className="rounded-xl border border-slate-800 bg-[#101C2C] p-4">
            <div className="text-xl font-bold text-white">{item.value}</div>
            <div className="text-[11px] uppercase tracking-wide text-slate-400 mt-1">{item.label}</div>
          </div>
        ))}
      </div>

      {error && (
        <div className="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-200 flex gap-2">
          <AlertCircle className="w-4 h-4 mt-0.5 shrink-0" /> <span>{error}</span>
        </div>
      )}

      <div className="rounded-2xl border border-slate-800 bg-[#101C2C] overflow-hidden">
        <div className="px-4 sm:px-5 py-4 border-b border-slate-800 flex items-center justify-between gap-3">
          <div className="flex items-center gap-2 text-slate-300 text-sm">
            <ShieldCheck className="w-4 h-4 text-[#C5A46D]" /> Visibility changes apply immediately to public API/pages for eligible listings.
          </div>
        </div>

        {loading ? (
          <div className="p-10 text-center text-slate-400">Loading agency listings...</div>
        ) : listings.length === 0 ? (
          <div className="p-10 text-center space-y-2">
            <Home className="w-10 h-10 text-slate-600 mx-auto" />
            <h3 className="text-white font-semibold">No agency listings found</h3>
            <p className="text-slate-400 text-sm">Create or assign listings to this agency. Once approved, you can control their public visibility here.</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-[#0B1220] text-slate-400 text-xs uppercase tracking-wide">
                <tr>
                  <th className="px-4 py-3">Listing</th>
                  <th className="px-4 py-3">Type / Purpose</th>
                  <th className="px-4 py-3">Price</th>
                  <th className="px-4 py-3">Approval</th>
                  <th className="px-4 py-3">Marketplace</th>
                  <th className="px-4 py-3">Action</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800">
                {listings.map((listing) => {
                  const isPublic = listing.visibility === 'public' && listing.approval_status === 'approved';
                  const eligible = canPublish(listing);
                  const busy = busyId === listing.id;
                  return (
                    <tr key={listing.id} className="hover:bg-white/[0.02]">
                      <td className="px-4 py-4">
                        <div className="flex items-center gap-3 min-w-[240px]">
                          <div className="w-14 h-14 rounded-lg overflow-hidden bg-[#0B1220] border border-slate-800 shrink-0 flex items-center justify-center">
                            {listing.images?.[0] ? <img src={listing.images[0]} alt="" className="w-full h-full object-cover" /> : <Home className="w-5 h-5 text-slate-600" />}
                          </div>
                          <div className="min-w-0">
                            <div className="font-semibold text-white truncate max-w-[280px]">{listing.title || 'Untitled listing'}</div>
                            <div className="text-xs text-slate-400 truncate max-w-[280px]">{[listing.location, listing.sector, listing.block].filter(Boolean).join(' · ') || 'Location not set'}</div>
                            {listing.isFeatured ? <span className="inline-flex mt-1 px-2 py-0.5 rounded text-[10px] font-bold bg-[#C5A46D]/15 text-[#C5A46D] border border-[#C5A46D]/30">Featured</span> : null}
                          </div>
                        </div>
                      </td>
                      <td className="px-4 py-4 text-slate-300">
                        <div>{listing.type || '—'}</div>
                        <div className="text-xs text-slate-500 capitalize">{listing.listing_purpose || 'sale'}</div>
                      </td>
                      <td className="px-4 py-4 text-[#F9E7BA] font-semibold whitespace-nowrap">{formatPrice(listing.pricePKR, listing.formattedPrice)}</td>
                      <td className="px-4 py-4">
                        <span className={`inline-flex px-2.5 py-1 rounded-full text-xs font-bold border ${
                          listing.approval_status === 'approved' ? 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30' :
                          listing.approval_status === 'pending' ? 'bg-amber-500/15 text-amber-300 border-amber-500/30' :
                          listing.approval_status === 'rejected' ? 'bg-rose-500/15 text-rose-300 border-rose-500/30' :
                          'bg-slate-700/40 text-slate-300 border-slate-600'
                        }`}>
                          {listing.approval_status || 'draft'}
                        </span>
                      </td>
                      <td className="px-4 py-4">
                        <div className="flex flex-col gap-1.5">
                          <span className={`inline-flex w-fit px-2.5 py-1 rounded-full text-xs font-bold border ${isPublic ? 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30' : 'bg-slate-700/40 text-slate-300 border-slate-600'}`}>
                            {isPublic ? 'Public' : 'Hidden'}
                          </span>
                          {!eligible && listing.approval_status === 'approved' && <span className="text-[11px] text-amber-300">Expired/unavailable</span>}
                          {listing.approval_status !== 'approved' && <span className="text-[11px] text-slate-400">Must be approved to publish</span>}
                        </div>
                      </td>
                      <td className="px-4 py-4">
                        <button
                          onClick={() => void toggleVisibility(listing)}
                          disabled={busy || (!eligible && !isPublic)}
                          className={`inline-flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-bold transition disabled:opacity-40 disabled:cursor-not-allowed ${
                            isPublic ? 'bg-slate-800 hover:bg-slate-700 text-white' : 'bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C]'
                          }`}
                        >
                          {busy ? <RefreshCw className="w-3.5 h-3.5 animate-spin" /> : isPublic ? <EyeOff className="w-3.5 h-3.5" /> : <Eye className="w-3.5 h-3.5" />}
                          {isPublic ? 'Hide' : 'Publish'}
                        </button>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
};
