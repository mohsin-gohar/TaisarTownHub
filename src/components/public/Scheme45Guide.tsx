import React from 'react';
import { useApp } from '../../context/AppContext';
import {
  Compass,
  MapPin,
  CheckCircle2,
  FileCheck,
  Building,
  ShieldAlert,
  ArrowRight,
  TrendingUp,
  Droplets,
  Zap,
  Car
} from 'lucide-react';
import { generateWhatsAppLink } from '../../utils/logic';

export const Scheme45Guide: React.FC = () => {
  const { cmsConfig, setCurrentView } = useApp();

  const whatsAppLink = generateWhatsAppLink(
    cmsConfig.contactWhatsApp,
    'Salam! I want to understand Taiser Town Scheme 45 sectors and file verification.'
  );

  return (
    <section className="py-20 px-4 sm:px-6 lg:px-8 bg-[#0B1220]">
      <div className="max-w-7xl mx-auto space-y-16">
        {/* Title */}
        <div className="text-center max-w-3xl mx-auto space-y-4">
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-[#C5A46D]/15 text-[#C5A46D] border border-[#C5A46D]/30">
            <Compass className="w-3.5 h-3.5" /> Comprehensive Authority & Sector Guide
          </span>
          <h2 className="font-serif-luxury text-3xl sm:text-4xl lg:text-5xl font-bold text-white">
            Understanding Taiser Town Scheme 45, Karachi
          </h2>
          <p className="text-slate-400 text-sm sm:text-base leading-relaxed">
            Spanning over 20,570 acres under the Malir Development Authority (MDA), Taiser Town is Karachi’s most expansive master-planned urban expansion corridor.
          </p>
        </div>

        {/* Master Plan Highlights Grid */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
          <div className="glass-panel p-6 rounded-2xl border border-slate-800 space-y-4">
            <div className="w-12 h-12 rounded-xl bg-[#C5A46D]/15 text-[#C5A46D] flex items-center justify-center border border-[#C5A46D]/30">
              <MapPin className="w-6 h-6" />
            </div>
            <h3 className="font-serif-luxury text-xl font-bold text-white">
              Phase 1 vs Phase 2 Sectors
            </h3>
            <p className="text-xs text-slate-300 leading-relaxed">
              <strong>Phase 1:</strong> Includes mature sectors like Sector 70-D, Sector 71, Sector 72, Sector 50, and Sector 30-A where physical possession, road carpets, and utility connections are active.
            </p>
            <p className="text-xs text-slate-400 leading-relaxed">
              <strong>Phase 2:</strong> Comprises extensive expansion sectors (Sector 77, 80+, etc.) offering tremendous capital appreciation potential over 2 to 4 year investment horizons.
            </p>
          </div>

          <div className="glass-panel p-6 rounded-2xl border border-slate-800 space-y-4">
            <div className="w-12 h-12 rounded-xl bg-[#C5A46D]/15 text-[#C5A46D] flex items-center justify-center border border-[#C5A46D]/30">
              <Car className="w-6 h-6" />
            </div>
            <h3 className="font-serif-luxury text-xl font-bold text-white">
              Arterial Connectivity
            </h3>
            <p className="text-xs text-slate-300 leading-relaxed">
              Direct linkage via the Northern Bypass (M-10 Motorway) provides 15-minute signal-free access to Gulshan-e-Maymar, Super Highway (M-9), and Lyari Expressway toward downtown Karachi.
            </p>
            <p className="text-xs text-slate-400 leading-relaxed">
              Internal road network boasts 150ft and 100ft dual boulevards with wide green medians and planned public transit corridors.
            </p>
          </div>

          <div className="glass-panel p-6 rounded-2xl border border-slate-800 space-y-4">
            <div className="w-12 h-12 rounded-xl bg-[#C5A46D]/15 text-[#C5A46D] flex items-center justify-center border border-[#C5A46D]/30">
              <FileCheck className="w-6 h-6" />
            </div>
            <h3 className="font-serif-luxury text-xl font-bold text-white">
              Biometric Transfer & Safety
            </h3>
            <p className="text-xs text-slate-300 leading-relaxed">
              Every allotment file transfer is conducted directly at Malir Development Authority (MDA) with Nadra biometric verification, ensuring zero risk of double allotment.
            </p>
            <p className="text-xs text-slate-400 leading-relaxed">
              Our agency provides complete documentation verification, title searches, and on-ground demarcation before financial closure.
            </p>
          </div>
        </div>

        {/* Development Checklist */}
        <div className="bg-[#101C2C] rounded-2xl p-8 border border-[#C5A46D]/20 space-y-6">
          <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
              <h3 className="font-serif-luxury text-2xl font-bold text-white">
                Sector-by-Sector Development Status
              </h3>
              <p className="text-xs text-slate-400 mt-1">
                Updated February 2026 ground assessment report for investors & builders.
              </p>
            </div>
            <a
              href={whatsAppLink}
              target="_blank"
              rel="noreferrer"
              className="px-5 py-2.5 rounded-lg bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C] font-bold text-xs transition-colors flex items-center gap-2 self-start md:self-auto"
            >
              <span>Request Sector Map & Rates</span>
              <ArrowRight className="w-3.5 h-3.5" />
            </a>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-4 border-t border-slate-800">
            <div className="p-4 rounded-xl bg-[#0B1220] border border-slate-800">
              <div className="text-sm font-bold text-[#F9E7BA]">Sector 70-D & 71</div>
              <div className="text-xs text-emerald-400 font-medium mt-1">Possession Ready</div>
              <div className="text-[11px] text-slate-400 mt-2">Active construction, carpeted streets, water boring, K-Electric polls installed.</div>
            </div>

            <div className="p-4 rounded-xl bg-[#0B1220] border border-slate-800">
              <div className="text-sm font-bold text-[#F9E7BA]">Sector 50 (Commercial)</div>
              <div className="text-xs text-[#C5A46D] font-medium mt-1">Commercial Boulevard</div>
              <div className="text-[11px] text-slate-400 mt-2">150ft road frontages, retail shopping arcades, high rental potential.</div>
            </div>

            <div className="p-4 rounded-xl bg-[#0B1220] border border-slate-800">
              <div className="text-sm font-bold text-[#F9E7BA]">Sector 30-A (MDA Homes)</div>
              <div className="text-xs text-emerald-400 font-medium mt-1">Ready Villas</div>
              <div className="text-[11px] text-slate-400 mt-2">Model duplex and single-storey residential homes with family occupancy.</div>
            </div>

            <div className="p-4 rounded-xl bg-[#0B1220] border border-slate-800">
              <div className="text-sm font-bold text-[#F9E7BA]">Sector 77 & Phase 2</div>
              <div className="text-xs text-amber-400 font-medium mt-1">Investment Phase</div>
              <div className="text-[11px] text-slate-400 mt-2">Low entry price (under 20 Lacs for 80 sq yd) with high future yields.</div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
};
