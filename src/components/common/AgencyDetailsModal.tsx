import React, { useState } from 'react';
import { Dealer, Property } from '../../types';
import { useApp } from '../../context/AppContext';
import {
  X,
  Building2,
  Phone,
  MessageCircle,
  Mail,
  ShieldCheck,
  CheckCircle2,
  FileCheck,
  MapPin,
  ExternalLink,
  Star,
  Award,
  Layers,
  Sparkles,
  ArrowRight
} from 'lucide-react';
import { formatPKRCurrency, generateWhatsAppLink, getPropertyPhase } from '../../utils/logic';

interface AgencyDetailsModalProps {
  dealer: Dealer | null;
  isOpen: boolean;
  onClose: () => void;
}

export const AgencyDetailsModal: React.FC<AgencyDetailsModalProps> = ({
  dealer,
  isOpen,
  onClose
}) => {
  const { properties, setSelectedPropertyId, setCurrentView, cmsConfig } = useApp();
  const [activePhaseTab, setActivePhaseTab] = useState<'All' | 'Phase 1' | 'Phase 2' | 'Phase 3'>('All');

  if (!isOpen || !dealer) return null;

  // Find all properties / files associated with this dealer
  const dealerProperties = properties.filter((p) => {
    return p.assignedDealerId === dealer.id || p.contactPerson === dealer.fullName;
  });

  // Filter by Phase tab
  const filteredProperties = dealerProperties.filter((p) => {
    if (activePhaseTab === 'All') return true;
    return getPropertyPhase(p) === activePhaseTab;
  });

  const agencyName = dealer.agencyOrBranch || dealer.agencyAffiliation || 'TaisarTownHub Senior Advisory';

  const defaultWhatsAppMsg = `Salam ${dealer.fullName}! I saw your agency profile for "${agencyName}" on TaisarTownHub. Please share your current available verified files in Taiser Town Scheme 45.`;
  const agencyWhatsAppLink = generateWhatsAppLink(dealer.whatsApp || dealer.phone, defaultWhatsAppMsg);

  const handleOpenProperty = (propertyId: string) => {
    setSelectedPropertyId(propertyId);
    setCurrentView('property-detail');
    onClose();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 md:p-6 bg-black/85 backdrop-blur-md animate-fadeIn overflow-y-auto">
      <div className="relative w-full max-w-4xl bg-[#101C2C] border border-[#C5A46D]/40 rounded-2xl sm:rounded-3xl shadow-2xl overflow-hidden my-auto max-h-[92vh] flex flex-col">
        {/* Top Header Banner */}
        <div className="bg-gradient-to-r from-[#0B1220] via-[#101C2C] to-[#1A2638] border-b border-[#C5A46D]/20 p-5 sm:p-6 relative">
          <button
            onClick={onClose}
            className="absolute top-4 right-4 text-slate-400 hover:text-white p-2 rounded-xl bg-slate-800/60 hover:bg-slate-700 transition cursor-pointer"
            aria-label="Close modal"
          >
            <X className="w-5 h-5" />
          </button>

          <div className="flex flex-col sm:flex-row items-start sm:items-center gap-4 sm:gap-6 pr-10">
            <div className="relative">
              <img
                src={dealer.avatar || 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=200&q=80'}
                alt={dealer.fullName}
                className="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl object-cover border-2 border-[#C5A46D] shadow-xl"
              />
              <span className="absolute -bottom-1 -right-1 p-1 rounded-full bg-emerald-500 text-white shadow">
                <CheckCircle2 className="w-3.5 h-3.5" />
              </span>
            </div>

            <div className="space-y-1 min-w-0">
              <div className="flex flex-wrap items-center gap-2">
                <span className="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-[#C5A46D] text-[#101C2C] uppercase tracking-wider">
                  MDA Registered Agency
                </span>
                <span className="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                  Biometric Desk Verified
                </span>
              </div>

              <h2 className="font-serif-luxury text-xl sm:text-2xl font-bold text-white truncate">
                {agencyName}
              </h2>

              <p className="text-xs sm:text-sm text-[#C5A46D] font-medium flex items-center gap-1.5">
                <span>Representative: <strong>{dealer.fullName}</strong></span>
                <span className="text-slate-500">•</span>
                <span className="text-slate-300">{dealer.role || 'Senior Real Estate Consultant'}</span>
              </p>
            </div>
          </div>

          {/* Quick Contact Action Strip */}
          <div className="mt-5 pt-4 border-t border-slate-800/80 flex flex-wrap items-center justify-between gap-3">
            <div className="flex flex-wrap items-center gap-2.5">
              <a
                href={agencyWhatsAppLink}
                target="_blank"
                rel="noreferrer"
                className="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs flex items-center gap-1.5 transition shadow"
              >
                <MessageCircle className="w-4 h-4" />
                <span>WhatsApp Agency Desk</span>
              </a>

              <a
                href={`tel:${dealer.phone}`}
                className="px-4 py-2 rounded-xl bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C] font-bold text-xs flex items-center gap-1.5 transition shadow"
              >
                <Phone className="w-4 h-4" />
                <span>{dealer.phone}</span>
              </a>

              {dealer.email && (
                <a
                  href={`mailto:${dealer.email}`}
                  className="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-medium flex items-center gap-1.5 border border-slate-700 transition hidden sm:inline-flex"
                >
                  <Mail className="w-3.5 h-3.5 text-[#C5A46D]" />
                  <span>{dealer.email}</span>
                </a>
              )}
            </div>

            <div className="flex items-center gap-1 text-[#C5A46D] text-xs font-bold">
              <Star className="w-4 h-4 fill-current" />
              <span>5.0 Official Rating</span>
            </div>
          </div>
        </div>

        {/* Agency Credentials & Inventory Overview */}
        <div className="p-4 sm:p-6 bg-[#0B1220]/60 border-b border-slate-800 grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
          <div className="p-3 rounded-xl bg-[#101C2C] border border-slate-800">
            <span className="text-[10px] text-slate-400 uppercase font-semibold block">Managed Files</span>
            <span className="text-xl font-bold font-serif-luxury text-white mt-0.5 block">
              {dealerProperties.length} Files
            </span>
          </div>

          <div className="p-3 rounded-xl bg-[#101C2C] border border-slate-800">
            <span className="text-[10px] text-slate-400 uppercase font-semibold block">Deals Facilitated</span>
            <span className="text-xl font-bold font-serif-luxury text-[#C5A46D] mt-0.5 block">
              {dealer.dealsClosed || dealer.completedDealsCount || 6}+ Closed
            </span>
          </div>

          <div className="p-3 rounded-xl bg-[#101C2C] border border-slate-800">
            <span className="text-[10px] text-slate-400 uppercase font-semibold block">Scheme 45 Experience</span>
            <span className="text-xl font-bold font-serif-luxury text-white mt-0.5 block">
              10+ Years
            </span>
          </div>

          <div className="p-3 rounded-xl bg-[#101C2C] border border-slate-800">
            <span className="text-[10px] text-slate-400 uppercase font-semibold block">Commission Rule</span>
            <span className="text-xs font-bold text-emerald-400 mt-1 block">
              Transparent / 100% Fixed
            </span>
          </div>
        </div>

        {/* Section: Files & Inventory Stock */}
        <div className="p-5 sm:p-6 flex-1 overflow-y-auto space-y-4">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
              <h3 className="font-serif-luxury text-lg font-bold text-white flex items-center gap-2">
                <span>Verified Property Files in Agency Stock</span>
                <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-[#C5A46D]/20 text-[#C5A46D]">
                  {filteredProperties.length} Available
                </span>
              </h3>
              <p className="text-xs text-slate-400">
                Click any file card below to inspect allotment numbers, dimensions, and schedule biometric transfer.
              </p>
            </div>

            {/* Phase Selector Tabs */}
            <div className="flex items-center gap-1 bg-[#0B1220] p-1 rounded-xl border border-slate-800 shrink-0">
              {(['All', 'Phase 1', 'Phase 2', 'Phase 3'] as const).map((phase) => (
                <button
                  key={phase}
                  onClick={() => setActivePhaseTab(phase)}
                  className={`px-3 py-1 rounded-lg text-xs font-semibold transition cursor-pointer ${
                    activePhaseTab === phase
                      ? 'bg-[#C5A46D] text-[#101C2C] font-bold shadow'
                      : 'text-slate-400 hover:text-white'
                  }`}
                >
                  {phase}
                </button>
              ))}
            </div>
          </div>

          {/* Files Grid */}
          {filteredProperties.length === 0 ? (
            <div className="p-8 text-center bg-[#0B1220] rounded-2xl border border-slate-800 text-slate-400 text-xs">
              No files currently listed under {activePhaseTab} for this agency.
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {filteredProperties.map((prop) => {
                const phase = getPropertyPhase(prop);
                const fileWaMsg = `Salam ${dealer.fullName}! I am interested in File #${prop.fileNumber || 'Direct'} for ${prop.title} (${prop.sector}, ${phase}) priced at ${prop.formattedPrice}. Please confirm file status and allotment verification.`;
                const fileWaLink = generateWhatsAppLink(dealer.whatsApp || dealer.phone, fileWaMsg);

                return (
                  <div
                    key={prop.id}
                    className="p-4 rounded-xl bg-[#0B1220] border border-slate-800 hover:border-[#C5A46D]/60 transition-all flex flex-col justify-between space-y-3 group shadow-md"
                  >
                    <div className="space-y-2">
                      {/* Top badges */}
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-1.5">
                          <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-[#C5A46D] text-[#101C2C]">
                            {prop.plotNumber ? `Plot ${prop.plotNumber}` : prop.type}
                          </span>
                          <span className="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-800 text-slate-300">
                            {phase}
                          </span>
                        </div>
                        <span className="text-[10px] text-emerald-400 font-bold flex items-center gap-1">
                          <CheckCircle2 className="w-3 h-3" />
                          <span>Biometric Ready</span>
                        </span>
                      </div>

                      {/* Title & Location */}
                      <div>
                        <h4 className="font-serif-luxury font-bold text-sm text-white group-hover:text-[#C5A46D] transition-colors line-clamp-1">
                          {prop.title}
                        </h4>
                        <div className="text-[11px] text-slate-400 flex items-center gap-1 mt-0.5">
                          <MapPin className="w-3 h-3 text-[#C5A46D] shrink-0" />
                          <span className="truncate">{prop.location}</span>
                        </div>
                      </div>

                      {/* File Specs Pill */}
                      <div className="grid grid-cols-2 gap-2 text-[11px] p-2.5 rounded-lg bg-[#101C2C] border border-slate-800/80">
                        <div>
                          <span className="text-slate-400 block text-[10px]">Allotment File #:</span>
                          <strong className="text-white font-mono">{prop.fileNumber || 'TT-VERIFIED'}</strong>
                        </div>
                        <div>
                          <span className="text-slate-400 block text-[10px]">Dimensions / Size:</span>
                          <strong className="text-white">
                            {prop.sizeSqYards} Sq. Yds {prop.frontFeet ? `(${prop.frontFeet}'×${prop.depthFeet}')` : ''}
                          </strong>
                        </div>
                        <div>
                          <span className="text-slate-400 block text-[10px]">Frontage Road:</span>
                          <span className="text-[#C5A46D] font-bold">
                            {prop.roadWidthFeet ? `${prop.roadWidthFeet}ft Road` : prop.is100ftRoadFacing ? '100ft Boulevard' : '40ft Street'}
                          </span>
                        </div>
                        <div>
                          <span className="text-slate-400 block text-[10px]">Demand Price:</span>
                          <strong className="text-[#F9E7BA] font-bold font-serif-luxury text-xs">
                            {prop.formattedPrice}
                          </strong>
                        </div>
                      </div>
                    </div>

                    {/* Card Actions */}
                    <div className="flex items-center gap-2 pt-2 border-t border-slate-800/80">
                      <button
                        onClick={() => handleOpenProperty(prop.id)}
                        className="flex-1 py-1.5 px-3 rounded-lg bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold transition flex items-center justify-center gap-1 cursor-pointer"
                      >
                        <span>View Details</span>
                        <ArrowRight className="w-3.5 h-3.5" />
                      </button>

                      <a
                        href={fileWaLink}
                        target="_blank"
                        rel="noreferrer"
                        className="py-1.5 px-3 rounded-lg bg-emerald-600/20 hover:bg-emerald-600 text-emerald-400 hover:text-white border border-emerald-500/30 text-xs font-bold transition flex items-center gap-1.5"
                        title="Direct WhatsApp inquiry for this specific file"
                      >
                        <MessageCircle className="w-3.5 h-3.5" />
                        <span>Inquire File</span>
                      </a>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="p-4 bg-[#0B1220] border-t border-slate-800 flex items-center justify-between text-xs text-slate-400">
          <span className="hidden sm:inline">
            All files are pre-screened for Malir Development Authority biometric compliance.
          </span>
          <button
            onClick={onClose}
            className="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-medium text-xs ml-auto cursor-pointer"
          >
            Close Profile
          </button>
        </div>
      </div>
    </div>
  );
};
