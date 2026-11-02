import React, { useState } from 'react';
import { useApp } from '../../context/AppContext';
import {
  Building2,
  Phone,
  MessageCircle,
  ShieldCheck,
  CheckCircle2,
  FileText,
  Star,
  Sparkles,
  ArrowRight,
  MapPin,
  ExternalLink
} from 'lucide-react';
import { Dealer } from '../../types';
import { AgencyDetailsModal } from '../common/AgencyDetailsModal';
import { generateWhatsAppLink } from '../../utils/logic';

export const AgenciesSection: React.FC = () => {
  const { dealers, properties } = useApp();
  const [selectedDealer, setSelectedDealer] = useState<Dealer | null>(null);

  return (
    <section className="py-20 px-4 sm:px-6 lg:px-8 bg-[#0B1220] border-t border-[#C5A46D]/15">
      <div className="max-w-7xl mx-auto space-y-12">
        {/* Section Header */}
        <div className="text-center max-w-3xl mx-auto space-y-3">
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-[#C5A46D]/15 text-[#C5A46D] border border-[#C5A46D]/30">
            <Building2 className="w-3.5 h-3.5" /> Scheme 45 Dealership Network
          </span>
          <h2 className="font-serif-luxury text-3xl sm:text-4xl font-bold text-white">
            Verified Partner Agencies & Property Consultants
          </h2>
          <p className="text-slate-400 text-sm sm:text-base leading-relaxed">
            Click any agency card below to inspect their active verified property files, Phase 1-3 plot allocations, and biometric clearance records.
          </p>
        </div>

        {/* Agency Cards Grid (Mobile, Tablet, Desktop Responsive) */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          {dealers.map((dealer) => {
            const agencyName = dealer.agencyOrBranch || dealer.agencyAffiliation || 'TaisarTownHub Senior Advisory';
            
            // Count files managed by this agency (soft-deleted listings excluded)
            const fileCount = properties.filter(
              (p) => !p.deletedAt && (p.assignedDealerId === dealer.id || p.contactPerson === dealer.fullName)
            ).length;

            const whatsAppMsg = `Salam ${dealer.fullName}! I saw your agency "${agencyName}" on TaisarTownHub. Please share currently available files in Taiser Town Scheme 45.`;
            const waLink = generateWhatsAppLink(dealer.whatsApp || dealer.phone, whatsAppMsg);

            return (
              <div
                key={dealer.id}
                className="bg-[#101C2C] rounded-2xl border border-slate-800 hover:border-[#C5A46D]/60 transition-all duration-300 hover:-translate-y-1 p-6 flex flex-col justify-between shadow-xl space-y-5 group"
              >
                <div className="space-y-4">
                  {/* Avatar & Verification Badge */}
                  <div className="flex items-start justify-between">
                    <div className="relative">
                      <img
                        src={dealer.avatar || 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=200&q=80'}
                        alt={dealer.fullName}
                        className="w-16 h-16 rounded-2xl object-cover border-2 border-[#C5A46D]/40 group-hover:border-[#C5A46D] transition shadow"
                      />
                      <span className="absolute -bottom-1 -right-1 w-5 h-5 rounded-full bg-emerald-500 text-white flex items-center justify-center text-[10px] shadow">
                        ✓
                      </span>
                    </div>

                    <div className="text-right">
                      <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-[#C5A46D]/15 text-[#C5A46D] border border-[#C5A46D]/30 block">
                        MDA Verified
                      </span>
                      <div className="flex items-center gap-1 text-[#F9E7BA] text-xs font-bold mt-1 justify-end">
                        <Star className="w-3.5 h-3.5 fill-current" />
                        <span>5.0</span>
                      </div>
                    </div>
                  </div>

                  {/* Agency and Consultant Name */}
                  <div className="space-y-1">
                    <h3 className="font-serif-luxury font-bold text-white text-base group-hover:text-[#C5A46D] transition-colors line-clamp-1">
                      {agencyName}
                    </h3>
                    <div className="text-xs text-slate-300 font-semibold">
                      {dealer.fullName}
                    </div>
                    <p className="text-[11px] text-slate-400 line-clamp-1">
                      {dealer.role || 'Senior Land Specialist'}
                    </p>
                  </div>

                  {/* Stock pill */}
                  <div className="p-3 rounded-xl bg-[#0B1220] border border-slate-800 space-y-1.5 text-xs">
                    <div className="flex justify-between items-center">
                      <span className="text-slate-400">Available Files:</span>
                      <span className="font-bold text-[#F9E7BA] font-mono">
                        {fileCount} Verified Files
                      </span>
                    </div>
                    <div className="flex justify-between items-center text-[11px]">
                      <span className="text-slate-400">Deals Closed:</span>
                      <span className="text-emerald-400 font-semibold">
                        {dealer.dealsClosed || dealer.completedDealsCount || 6}+ Successful
                      </span>
                    </div>
                  </div>
                </div>

                {/* Actions */}
                <div className="space-y-2 pt-2 border-t border-slate-800">
                  <button
                    onClick={() => setSelectedDealer(dealer)}
                    className="w-full py-2.5 px-3 rounded-xl bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C] font-bold text-xs transition flex items-center justify-center gap-1.5 cursor-pointer shadow-md shadow-[#C5A46D]/20"
                  >
                    <FileText className="w-3.5 h-3.5" />
                    <span>View Files & Details</span>
                    <ArrowRight className="w-3.5 h-3.5" />
                  </button>

                  <div className="flex items-center gap-2">
                    <a
                      href={waLink}
                      target="_blank"
                      rel="noreferrer"
                      className="flex-1 py-1.5 px-2 rounded-lg bg-emerald-600/20 hover:bg-emerald-600 text-emerald-400 hover:text-white border border-emerald-500/30 text-[11px] font-semibold transition flex items-center justify-center gap-1"
                    >
                      <MessageCircle className="w-3 h-3" />
                      <span>WhatsApp</span>
                    </a>

                    <a
                      href={`tel:${dealer.phone}`}
                      className="flex-1 py-1.5 px-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-[11px] font-semibold transition flex items-center justify-center gap-1 border border-slate-700"
                    >
                      <Phone className="w-3 h-3 text-[#C5A46D]" />
                      <span>Call Desk</span>
                    </a>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </div>

      {/* Interactive Agency Details & Files Modal Popup */}
      <AgencyDetailsModal
        dealer={selectedDealer}
        isOpen={!!selectedDealer}
        onClose={() => setSelectedDealer(null)}
      />
    </section>
  );
};
