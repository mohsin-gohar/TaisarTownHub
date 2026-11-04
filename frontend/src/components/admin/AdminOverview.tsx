import React from 'react';
import { useApp } from '../../context/AppContext';
import {
  Building2,
  Home,
  Image,
  Inbox,
  TrendingUp,
  Clock,
  CheckCircle2,
  AlertCircle,
  ArrowUpRight,
  Eye,
  Plus,
  DollarSign,
  FileText,
  Users,
  MapPin,
  Sparkles,
  ShieldCheck
} from 'lucide-react';
import { formatPKRCurrency, calculateERPFinancials } from '../../utils/logic';

export const AdminOverview: React.FC = () => {
  const {
    projects,
    properties,
    mediaItems,
    inquiries,
    transactions,
    paymentEntries,
    commissionEntries,
    sellers,
    buyers,
    dealers,
    propertyFiles,
    setAdminTab,
    setCurrentView
  } = useApp();

  const pendingInquiries = inquiries.filter((i) => i.status === 'Pending').length;
  const verifiedFiles = propertyFiles.filter((f) => f.verificationStatus === 'Verified').length;
  const financials = calculateERPFinancials(transactions, paymentEntries, commissionEntries);

  return (
    <div className="space-y-8 animate-fadeIn">
      {/* Top Banner / Welcome */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-6 rounded-2xl bg-gradient-to-r from-[#101C2C] to-[#1A2638] border border-[#C5A46D]/30 shadow-xl">
        <div className="space-y-1">
          <div className="flex items-center gap-2">
            <h2 className="font-serif-luxury text-2xl font-bold text-white">
              Agency Control Center & Real Estate ERP
            </h2>
            <span className="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-[#C5A46D] text-[#101C2C]">
              v2.0 ERP
            </span>
          </div>
          <p className="text-xs text-slate-300">
            Real-time synchronization between public listings, landowner registry, dealer commissions, and transaction contracts.
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2.5">
          <button
            onClick={() => setAdminTab('transactions')}
            className="px-3.5 py-2 rounded-lg bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C] font-bold text-xs flex items-center gap-1.5 cursor-pointer shadow"
          >
            <FileText className="w-3.5 h-3.5" />
            <span>Manage Deals</span>
          </button>
          <button
            onClick={() => setAdminTab('plot_locator')}
            className="px-3.5 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-white font-medium text-xs flex items-center gap-1.5 border border-slate-700 cursor-pointer"
          >
            <MapPin className="w-3.5 h-3.5 text-[#C5A46D]" />
            <span>Sector Map</span>
          </button>
          <button
            onClick={() => setAdminTab('properties')}
            className="px-3.5 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-white font-medium text-xs flex items-center gap-1.5 border border-slate-700 cursor-pointer"
          >
            <Plus className="w-3.5 h-3.5 text-[#C5A46D]" />
            <span>Add Property</span>
          </button>
        </div>
      </div>

      {/* Primary ERP Financial KPIs */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div className="bg-[#101C2C] p-6 rounded-2xl border border-slate-800 space-y-2 shadow-lg">
          <div className="flex items-center justify-between">
            <span className="text-xs text-slate-400 font-semibold uppercase">Total Sales Volume</span>
            <div className="p-2 rounded-xl bg-[#C5A46D]/15 text-[#C5A46D]">
              <DollarSign className="w-5 h-5" />
            </div>
          </div>
          <div className="font-serif-luxury text-3xl font-bold text-white">
            {formatPKRCurrency(financials.totalSalesVolume)}
          </div>
          <div className="text-[11px] text-[#C5A46D] flex items-center gap-1">
            <TrendingUp className="w-3.5 h-3.5" />
            <span className="font-semibold">+12.4% this quarter</span>
            <span className="text-slate-500">• {financials.totalDealsCount} active deals</span>
          </div>
        </div>

        <div className="bg-[#101C2C] p-6 rounded-2xl border border-slate-800 space-y-2 shadow-lg">
          <div className="flex items-center justify-between">
            <span className="text-xs text-slate-400 font-semibold uppercase">Advances Collected</span>
            <div className="p-2 rounded-xl bg-[#C5A46D]/15 text-[#C5A46D]">
              <CheckCircle2 className="w-5 h-5" />
            </div>
          </div>
          <div className="font-serif-luxury text-3xl font-bold text-[#C5A46D]">
            {formatPKRCurrency(financials.totalAdvancesCollected)}
          </div>
          <div className="text-[11px] text-slate-400">
            Escrow deposits & token payments
          </div>
        </div>

        <div className="bg-[#101C2C] p-6 rounded-2xl border border-slate-800 space-y-2 shadow-lg">
          <div className="flex items-center justify-between">
            <span className="text-xs text-slate-400 font-semibold uppercase">Receivables at Transfer</span>
            <div className="p-2 rounded-xl bg-[#C5A46D]/15 text-[#C5A46D]">
              <Clock className="w-5 h-5" />
            </div>
          </div>
          <div className="font-serif-luxury text-3xl font-bold text-white">
            {formatPKRCurrency(financials.pendingReceivables)}
          </div>
          <div className="text-[11px] text-slate-400">
            Due upon MDA transfer completion
          </div>
        </div>

        <div className="bg-[#101C2C] p-6 rounded-2xl border border-slate-800 space-y-2 shadow-lg">
          <div className="flex items-center justify-between">
            <span className="text-xs text-slate-400 font-semibold uppercase">Dealer Commissions</span>
            <div className="p-2 rounded-xl bg-[#C5A46D]/15 text-[#C5A46D]">
              <Users className="w-5 h-5" />
            </div>
          </div>
          <div className="font-serif-luxury text-3xl font-bold text-[#C5A46D]">
            {formatPKRCurrency(financials.totalCommissionPayable)}
          </div>
          <div className="text-[11px] text-slate-400">
            {dealers.length} Registered agents & facilitators
          </div>
        </div>
      </div>

      {/* Handwritten Requirement Spotlight Box */}
      <div className="p-5 rounded-2xl bg-[#0B1220] border border-[#C5A46D]/40 shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div className="space-y-1">
          <div className="flex items-center gap-2">
            <Sparkles className="w-4 h-4 text-[#C5A46D]" />
            <span className="font-bold text-white text-sm">
              Handwritten Notes Record: Plot R-66 (Sector 63, Scheme 45)
            </span>
            <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-[#C5A46D]/20 text-[#F9E7BA] border border-[#C5A46D]/40">
              Integrated & Verified
            </span>
          </div>
          <p className="text-xs text-slate-300">
            Dimensions: <strong>24&apos; Front × 45&apos; Depth</strong> (120 Sq. Yds / 1,080 sq.ft) • Facing <strong>100-Foot Main Boulevard</strong>.
            Seller: <strong>Haji Abdul Rasheed</strong> • Buyer: <strong>Muhammad Usman</strong> • Agent: <strong>Saleem Siddiqui</strong> (Agreed Commission: <strong>PKR 20,000</strong>).
          </p>
        </div>

        <div className="flex items-center gap-2 shrink-0">
          <button
            onClick={() => setAdminTab('plot_locator')}
            className="px-3 py-1.5 rounded-lg bg-[#C5A46D]/20 hover:bg-[#C5A46D] text-[#C5A46D] hover:text-[#101C2C] text-xs font-bold transition cursor-pointer"
          >
            Locate Plot R-66
          </button>
          <button
            onClick={() => setAdminTab('transactions')}
            className="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold cursor-pointer"
          >
            View Deal Voucher
          </button>
        </div>
      </div>

      {/* Visual Analytics & Portfolio Breakdown */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
        {/* Inquiry Traffic Chart (SVG) (8 cols) */}
        <div className="lg:col-span-8 bg-[#101C2C] p-6 sm:p-8 rounded-2xl border border-slate-800 shadow-xl space-y-6">
          <div className="flex items-center justify-between">
            <div>
              <h3 className="font-serif-luxury text-lg font-bold text-white">
                Monthly Buyer Interest & Inquiry Volume
              </h3>
              <p className="text-xs text-slate-400">Scheme 45 plots vs commercial shops query trends</p>
            </div>
            <span className="px-3 py-1 rounded-lg bg-slate-800 text-[11px] text-[#C5A46D] font-semibold border border-slate-700">
              2026 Quarter 1
            </span>
          </div>

          {/* Clean SVG Area & Bar Visual */}
          <div className="relative h-56 w-full flex items-end justify-between gap-3 pt-6 px-2">
            {[
              { month: 'Oct', count: 18, height: '40%' },
              { month: 'Nov', count: 28, height: '55%' },
              { month: 'Dec', count: 42, height: '70%' },
              { month: 'Jan', count: 58, height: '85%' },
              { month: 'Feb', count: 72, height: '100%' }
            ].map((col, idx) => (
              <div key={idx} className="flex-1 flex flex-col items-center gap-2 h-full justify-end group">
                <span className="text-[10px] text-slate-400 font-bold opacity-0 group-hover:opacity-100 transition-opacity">
                  {col.count} Leads
                </span>
                <div
                  className="w-full max-w-[48px] rounded-t-lg bg-gradient-to-t from-[#C5A46D]/20 to-[#C5A46D] group-hover:to-[#F9E7BA] transition-all duration-300 relative"
                  style={{ height: col.height }}
                >
                  <div className="absolute top-1.5 left-1/2 -translate-x-1/2 w-2 h-2 rounded-full bg-white shadow" />
                </div>
                <span className="text-xs font-semibold text-slate-300">
                  {col.month}
                </span>
              </div>
            ))}
          </div>

          <div className="flex items-center justify-between pt-4 border-t border-slate-800 text-xs text-slate-400">
            <span>Average inquiry conversion rate: <strong>32.4%</strong></span>
            <span className="text-[#C5A46D]">Highest demand: Sector 70-D & 71</span>
          </div>
        </div>

        {/* Property Categories Distribution (4 cols) */}
        <div className="lg:col-span-4 bg-[#101C2C] p-6 rounded-2xl border border-slate-800 shadow-xl space-y-6">
          <div>
            <h3 className="font-serif-luxury text-lg font-bold text-white">
              Inventory Distribution
            </h3>
            <p className="text-xs text-slate-400">Current portfolio breakdown</p>
          </div>

          <div className="space-y-4">
            {[
              { label: 'Residential Plots', pct: 45, color: 'bg-[#C5A46D]' },
              { label: 'Commercial Plots & Shops', pct: 25, color: 'bg-[#E0C79A]' },
              { label: 'Ready Villas & Houses', pct: 20, color: 'bg-[#8C6D37]' },
              { label: 'Farmhouses & Leisure', pct: 10, color: 'bg-[#F9E7BA]' }
            ].map((cat, i) => (
              <div key={i} className="space-y-1.5">
                <div className="flex justify-between text-xs text-slate-300">
                  <span>{cat.label}</span>
                  <span className="font-bold">{cat.pct}%</span>
                </div>
                <div className="w-full h-2 rounded-full bg-slate-800 overflow-hidden">
                  <div className={`h-full ${cat.color}`} style={{ width: `${cat.pct}%` }} />
                </div>
              </div>
            ))}
          </div>

          <div className="p-4 rounded-xl bg-[#0B1220] border border-slate-800 text-xs text-slate-400 space-y-1">
            <span className="font-bold text-white block">Central Media Storage</span>
            <span>Total stored files: {mediaItems.filter((item) => !item.deletedAt).length} assets across 6 folders.</span>
          </div>
        </div>
      </div>

      {/* Latest Inquiries Table Preview */}
      <div className="bg-[#101C2C] p-6 sm:p-8 rounded-2xl border border-slate-800 shadow-xl space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h3 className="font-serif-luxury text-xl font-bold text-white">
              Recent Lead Stream & Incoming Requests
            </h3>
            <p className="text-xs text-slate-400">Captured through website search, project, and property forms</p>
          </div>
          <button
            onClick={() => setAdminTab('inquiries')}
            className="text-xs font-semibold text-[#C5A46D] hover:underline cursor-pointer"
          >
            Manage All Inquiries ({inquiries.length}) &rarr;
          </button>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-[#0B1220] text-slate-400 uppercase tracking-wider text-[10px]">
              <tr>
                <th className="py-3 px-4 rounded-l-lg">Client Name</th>
                <th className="py-3 px-4">Phone / WhatsApp</th>
                <th className="py-3 px-4">Subject & Interest</th>
                <th className="py-3 px-4">Status</th>
                <th className="py-3 px-4 rounded-r-lg text-right">Quick Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800/80">
              {inquiries.slice(0, 4).map((inq) => (
                <tr key={inq.id} className="hover:bg-slate-800/40 transition-colors">
                  <td className="py-3.5 px-4 font-bold text-white">
                    {inq.fullName}
                  </td>
                  <td className="py-3.5 px-4 text-slate-300 font-mono">
                    {inq.phone}
                  </td>
                  <td className="py-3.5 px-4 text-slate-300 max-w-xs truncate">
                    {inq.preferredPropertyOrProject || inq.subject}
                  </td>
                  <td className="py-3.5 px-4">
                    <span className={`px-2.5 py-1 rounded-full text-[10px] font-bold border ${
                      inq.status === 'Pending' ? 'bg-[#C5A46D]/10 text-[#C5A46D] border-[#C5A46D]/30' :
                      inq.status === 'Contacted' ? 'bg-[#C5A46D]/20 text-[#F9E7BA] border-[#C5A46D]/40' :
                      inq.status === 'Resolved' ? 'bg-[#C5A46D]/30 text-white border-[#C5A46D]/50' :
                      'bg-slate-800 text-slate-300 border-slate-700'
                    }`}>
                      {inq.status}
                    </span>
                  </td>
                  <td className="py-3.5 px-4 text-right">
                    <button
                      onClick={() => setAdminTab('inquiries')}
                      className="px-2.5 py-1 rounded bg-slate-800 hover:bg-[#C5A46D] hover:text-[#101C2C] text-[#C5A46D] text-[11px] font-semibold transition-colors cursor-pointer"
                    >
                      Open CRM
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
};
