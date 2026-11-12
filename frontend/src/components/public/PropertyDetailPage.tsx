import React, { useState, useEffect } from 'react';
import { useApp } from '../../context/AppContext';
import {
  ArrowLeft,
  MapPin,
  Maximize2,
  Calendar,
  CheckCircle2,
  ShieldCheck,
  Calculator,
  Send,
  Phone,
  Home,
  Compass,
  Sparkles,
  Share2,
  Play
} from 'lucide-react';
import {
  formatPKRCurrency,
  calculateInstallmentPlan,
  toPublicProperty
} from '../../utils/logic';
import { Property, PublicProperty } from '../../types';
import { Lightbox } from '../common/Lightbox';
import { EmptyState } from '../common/EmptyState';
import { ImageWithFallback } from '../common/ImageWithFallback';

const VIDEO_SENTINEL = '__tth_video__';

export const PropertyDetailPage: React.FC = () => {
  const {
    properties,
    selectedPropertyId,
    setCurrentView,
    cmsConfig,
    addInquiry,
    showToast
  } = useApp();

  // Soft-deleted listings must never render, even via a stale selected id.
  const activeProperties = properties.filter((p) => !p.deletedAt);
  const rawProperty = activeProperties.find((p) => p.id === selectedPropertyId) || activeProperties[0];
  const property: PublicProperty | undefined = rawProperty ? toPublicProperty(rawProperty) : undefined;

  const [activeImage, setActiveImage] = useState<string>(property?.images?.[0] || '');
  const [lightboxIndex, setLightboxIndex] = useState<number | null>(null);

  // Client-side routing keeps this component mounted while the selected listing
  // changes, so re-sync the gallery and the pre-filled inquiry whenever the
  // property id changes (otherwise the previous listing's image/message sticks).
  useEffect(() => {
    setActiveImage(property?.images?.[0] || '');
    setLightboxIndex(null);
    setMessage(
      property
        ? `Salam, I am interested in Property ID ${property.id}: ${property.title}. Please provide file verification and token procedure.`
        : ''
    );
  }, [property?.id]);

  // Installment Calculator State
  const [downPaymentPct, setDownPaymentPct] = useState<number>(25);
  const [tenureYears, setTenureYears] = useState<number>(3);

  // Inquiry Form State
  const [fullName, setFullName] = useState('');
  const [phone, setPhone] = useState('');
  const [message, setMessage] = useState(
    property
      ? `Salam, I am interested in Property ID ${property.id}: ${property.title}. Please provide file verification and token procedure.`
      : ''
  );

  if (!property) {
    return (
      <div className="py-24 px-4 text-center max-w-lg mx-auto min-h-[60vh] flex items-center justify-center">
        <EmptyState
          icon={<Home className="w-8 h-8 text-[#C5A46D]" />}
          title="Listing Not Available"
          description="The selected property is currently unavailable or has been archived."
          actionLabel="Explore All Verified Properties"
          onAction={() => setCurrentView('properties')}
        />
      </div>
    );
  }

  // Gallery model: images + optional virtual video slot
  const gallery: string[] = property.images ?? [];
  const hasVideo = !!property.videoUrl;
  const activeIsVideo = activeImage === VIDEO_SENTINEL && hasVideo;
  const mainSrc = activeIsVideo ? '' : activeImage || gallery[0];

  const openLightbox = () => {
    const idx = activeIsVideo ? gallery.length : gallery.indexOf(mainSrc);
    setLightboxIndex(idx < 0 ? 0 : idx);
  };

  const installmentResult = calculateInstallmentPlan(
    property.pricePKR,
    downPaymentPct,
    tenureYears
  );

  const handleInquirySubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!fullName || !phone) {
      showToast('error', 'Please enter your full name and phone number.');
      return;
    }

    addInquiry({
      fullName,
      phone,
      subject: `Property Inquiry: ${property.title}`,
      preferredPropertyOrProject: property.title,
      message,
      source: 'Property Page'
    });

    setFullName('');
    setPhone('');
  };

  return (
    <div className="py-12 px-4 sm:px-6 lg:px-8 bg-[#0B1220] min-h-screen">
      <div className="max-w-7xl mx-auto space-y-10">
        {/* Navigation Bar */}
        <div className="flex items-center justify-between">
          <button
            onClick={() => {
              setCurrentView('properties');
              window.scrollTo({ top: 0, behavior: 'smooth' });
            }}
            className="inline-flex items-center gap-2 text-xs font-semibold text-slate-300 hover:text-[#C5A46D] transition-colors cursor-pointer"
          >
            <ArrowLeft className="w-4 h-4" />
            <span>Back to All Properties</span>
          </button>

          <div className="flex flex-wrap items-center gap-2">
            <span className="px-3 py-1 rounded-full text-xs font-semibold bg-[#C5A46D] text-[#101C2C] shadow">
              {property.type}
            </span>
            <span className="px-3 py-1 rounded-full text-xs font-semibold bg-[#101C2C] text-[#F9E7BA] border border-[#C5A46D]/40">
              {property.phase || 'Phase 1'}
            </span>
            {property.plotNumber && (
              <span className="px-2.5 py-1 rounded-full text-xs font-mono font-semibold bg-slate-800 text-[#C5A46D] border border-slate-700">
                Plot {property.plotNumber}
              </span>
            )}
            <span className={`px-3 py-1 rounded-full text-xs font-semibold backdrop-blur-md ${
              property.status === 'Available' ? 'bg-emerald-600/90 text-white' : 'bg-rose-600 text-white'
            }`}>
              {property.status}
            </span>
          </div>
        </div>

        {/* Title Header */}
        <div className="flex flex-col md:flex-row md:items-end justify-between gap-6 pb-6 border-b border-slate-800">
          <div className="space-y-2">
            <div className="flex items-center gap-2 text-xs text-[#C5A46D]">
              <MapPin className="w-4 h-4" />
              <span>{property.location}</span>
            </div>
            <h1 className="font-serif-luxury text-3xl sm:text-4xl font-bold text-white max-w-4xl">
              {property.title}
            </h1>
          </div>

          <div className="text-left md:text-right space-y-1">
            <span className="text-xs text-slate-400 uppercase tracking-wider block">Demand Price</span>
            <div className="font-serif-luxury text-3xl sm:text-4xl font-bold text-[#F9E7BA]">
              {property.formattedPrice}
            </div>
            {Number(property.sizeSqYards) > 0 && (
              <span className="text-[11px] text-slate-400 block">
                PKR {Math.round((property.pricePKR || 0) / property.sizeSqYards).toLocaleString()} / Sq. Yard
              </span>
            )}
          </div>
        </div>

        {/* Image Showcase with lightbox & video overlay */}
        <div className="space-y-4">
          <div
            className="group relative aspect-16/9 sm:aspect-21/9 max-h-[500px] rounded-2xl overflow-hidden border border-[#C5A46D]/30 bg-slate-900 shadow-2xl cursor-zoom-in"
            onClick={openLightbox}
            role="button"
            aria-label="Open gallery full screen"
            tabIndex={0}
            onKeyDown={(e) => {
              if (e.key === 'Enter' || e.key === ' ') openLightbox();
            }}
          >
            {activeIsVideo ? (
              <video
                src={property.videoUrl}
                controls
                playsInline
                className="w-full h-full object-cover"
                onClick={(e) => e.stopPropagation()}
              />
            ) : (
              <ImageWithFallback
                src={mainSrc}
                alt={property.title}
                fetchPriority="high"
                decoding="async"
                className="w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-700"
              />
            )}

            {/* Video indicator */}
            {hasVideo && !activeIsVideo && (
              <button
                onClick={(e) => {
                  e.stopPropagation();
                  setActiveImage(VIDEO_SENTINEL);
                }}
                aria-label="Play property video"
                className="absolute top-4 right-4 px-3 py-1.5 rounded-full bg-black/70 backdrop-blur-md border border-[#C5A46D]/40 text-[#F9E7BA] text-[11px] font-bold flex items-center gap-1.5 hover:bg-[#C5A46D] hover:text-[#101C2C] transition-colors cursor-pointer"
              >
                <Play className="w-3.5 h-3.5" />
                Watch Video Tour
              </button>
            )}

            {/* Expand affordance */}
            {!activeIsVideo && (
              <span className="absolute bottom-4 right-4 p-2.5 rounded-full bg-black/70 backdrop-blur-md border border-white/15 text-white opacity-0 group-hover:opacity-100 transition-opacity">
                <Maximize2 className="w-4 h-4 text-[#C5A46D]" />
              </span>
            )}
          </div>

          <div className="flex items-center gap-3 overflow-x-auto pb-2">
            {gallery.map((img, idx) => (
              <button
                key={idx}
                onClick={() => setActiveImage(img)}
                aria-label={`Show image ${idx + 1}`}
                className={`relative w-24 h-16 rounded-xl overflow-hidden shrink-0 border-2 transition-all cursor-pointer ${
                  activeImage === img && !activeIsVideo ? 'border-[#C5A46D] scale-105' : 'border-slate-800 opacity-60 hover:opacity-100'
                }`}
              >
                <ImageWithFallback src={img} alt="" loading="lazy" decoding="async" className="w-full h-full object-cover" />
              </button>
            ))}
            {hasVideo && (
              <button
                onClick={() => setActiveImage(VIDEO_SENTINEL)}
                aria-label="Play property video"
                className={`relative w-24 h-16 rounded-xl shrink-0 border-2 flex flex-col items-center justify-center gap-1 transition-all cursor-pointer ${
                  activeIsVideo ? 'border-[#C5A46D] bg-[#C5A46D]/15' : 'border-slate-800 bg-[#101C2C] opacity-70 hover:opacity-100'
                }`}
              >
                <Play className="w-4 h-4 text-[#C5A46D]" />
                <span className="text-[9px] uppercase tracking-wider font-bold text-slate-300">Video Tour</span>
              </button>
            )}
          </div>
        </div>

        {/* Full-screen gallery lightbox (keyboard: ← → Esc) */}
        {property && (
          <Lightbox
            images={gallery}
            videoUrl={property.videoUrl}
            title={property.title}
            openIndex={lightboxIndex}
            onClose={() => setLightboxIndex(null)}
            onNavigate={setLightboxIndex}
          />
        )}

        {/* Details & Calculator Grid */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-10">
          {/* Main Info (8 cols) */}
          <div className="lg:col-span-8 space-y-10">
            {/* Quick Specs Grid */}
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 p-6 rounded-2xl bg-[#101C2C] border border-slate-800">
              <div className="space-y-1">
                <span className="text-[10px] text-slate-400 uppercase font-semibold">Taiser Phase</span>
                <div className="font-serif-luxury text-lg font-bold text-[#F9E7BA] flex items-center gap-1.5">
                  <Sparkles className="w-4 h-4 text-[#C5A46D]" />
                  <span>{property.phase || 'Phase 1'}</span>
                </div>
              </div>

              <div className="space-y-1">
                <span className="text-[10px] text-slate-400 uppercase font-semibold">Plot Size</span>
                <div className="font-serif-luxury text-xl font-bold text-white flex items-center gap-1.5">
                  <Maximize2 className="w-4 h-4 text-[#C5A46D]" />
                  <span>{property.sizeSqYards} Sq. Yd</span>
                </div>
              </div>

              <div className="space-y-1">
                <span className="text-[10px] text-slate-400 uppercase font-semibold">Possession</span>
                <div className="text-sm font-bold text-emerald-400 flex items-center gap-1.5">
                  <ShieldCheck className="w-4 h-4" />
                  <span>{property.possessionReady ? 'Ready Physical' : 'In Development'}</span>
                </div>
              </div>

              <div className="space-y-1">
                <span className="text-[10px] text-slate-400 uppercase font-semibold">File / Allotment</span>
                <div className="text-sm font-bold text-[#C5A46D] flex items-center gap-1.5 font-mono">
                  <CheckCircle2 className="w-4 h-4" />
                  <span>{property.fileNumber ? `#${property.fileNumber}` : 'Verified'}</span>
                </div>
              </div>
            </div>

            {/* Description */}
            <div className="bg-[#101C2C] p-8 rounded-2xl border border-slate-800 space-y-4 shadow-xl">
              <h3 className="font-serif-luxury text-2xl font-bold text-white">
                Detailed Property Description
              </h3>
              <p className="text-slate-300 text-sm leading-relaxed whitespace-pre-line font-light">
                {property.description}
              </p>
            </div>

            {/* Features & Amenities List */}
            <div className="bg-[#101C2C] p-8 rounded-2xl border border-slate-800 space-y-4 shadow-xl">
              <h3 className="font-serif-luxury text-2xl font-bold text-white">
                Location & Construction Highlights
              </h3>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                {property.features.map((feat, i) => (
                  <div key={i} className="flex items-center gap-2.5 text-xs text-slate-200">
                    <CheckCircle2 className="w-4 h-4 text-[#C5A46D] shrink-0" />
                    <span>{feat}</span>
                  </div>
                ))}
              </div>
            </div>

            {/* Installment Calculator */}
            <div className="bg-[#101C2C] p-8 rounded-2xl border border-[#C5A46D]/30 space-y-6 shadow-2xl">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-[#C5A46D]/15 text-[#C5A46D] flex items-center justify-center">
                  <Calculator className="w-5 h-5" />
                </div>
                <div>
                  <h3 className="font-serif-luxury text-xl font-bold text-white">
                    Estimated Financing & Installment Planner
                  </h3>
                  <p className="text-xs text-slate-400">
                    Interactive simulation for customized down payment and installment milestones.
                  </p>
                </div>
              </div>

              <div className="space-y-6 pt-2">
                {/* Down payment slider */}
                <div className="space-y-2">
                  <div className="flex justify-between text-xs text-slate-300">
                    <span>Down Payment Percentage: <strong>{downPaymentPct}%</strong></span>
                    <span className="text-[#C5A46D] font-bold">
                      {formatPKRCurrency(installmentResult.downPayment)}
                    </span>
                  </div>
                  <input
                    type="range"
                    min={15}
                    max={50}
                    step={5}
                    value={downPaymentPct}
                    onChange={(e) => setDownPaymentPct(Number(e.target.value))}
                    className="w-full accent-[#C5A46D] cursor-pointer"
                  />
                  <div className="flex justify-between text-[10px] text-slate-500">
                    <span>15%</span>
                    <span>30%</span>
                    <span>50%</span>
                  </div>
                </div>

                {/* Tenure Selector */}
                <div className="space-y-2">
                  <label className="text-xs text-slate-300 block">Payment Tenure:</label>
                  <div className="grid grid-cols-4 gap-2">
                    {[1, 2, 3, 5].map((years) => (
                      <button
                        key={years}
                        onClick={() => setTenureYears(years)}
                        className={`py-2 rounded-lg text-xs font-semibold transition-colors cursor-pointer ${
                          tenureYears === years
                            ? 'bg-[#C5A46D] text-[#101C2C]'
                            : 'bg-[#0B1220] text-slate-300 border border-slate-700 hover:border-slate-500'
                        }`}
                      >
                        {years} {years === 1 ? 'Year' : 'Years'}
                      </button>
                    ))}
                  </div>
                </div>

                {/* Results Card */}
                <div className="p-4 rounded-xl bg-[#0B1220] border border-slate-800 grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <span className="text-[10px] text-slate-400 uppercase tracking-wider block">
                      Estimated Monthly Installment
                    </span>
                    <span className="font-serif-luxury text-xl font-bold text-emerald-400">
                      PKR {installmentResult.monthlyInstallment.toLocaleString()} / mo
                    </span>
                    <span className="text-[10px] text-slate-500 block">
                      Across {installmentResult.totalMonths} monthly payments
                    </span>
                  </div>

                  <div>
                    <span className="text-[10px] text-slate-400 uppercase tracking-wider block">
                      Remaining Principal Balance
                    </span>
                    <span className="font-serif-luxury text-lg font-bold text-white">
                      {formatPKRCurrency(installmentResult.remainingAmount)}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Contact Agent Sidebar (4 cols) */}
          <div className="lg:col-span-4 space-y-6">
            <div className="bg-[#101C2C] p-6 sm:p-8 rounded-2xl border border-[#C5A46D]/30 shadow-2xl space-y-6 sticky top-28">
              <div>
                <span className="text-[10px] uppercase font-bold text-[#C5A46D] tracking-wider block">
                  Designated Property Specialist
                </span>
                <div className="flex items-center gap-3 mt-3 pb-3 border-b border-slate-800">
                  <div className="w-12 h-12 rounded-full bg-slate-800 border border-[#C5A46D]/40 flex items-center justify-center font-bold text-white">
                    {property.contactPerson?.split(' ').map(n => n[0]).join('') || 'TT'}
                  </div>
                  <div>
                    <h4 className="font-serif-luxury font-bold text-white text-base">
                      {property.contactPerson || 'Taisar Town Advisory Desk'}
                    </h4>
                    <span className="text-xs text-[#C5A46D]">Scheme 45 Senior Advisor</span>
                  </div>
                </div>
              </div>

              {/* Inquiry Form */}
              <form onSubmit={handleInquirySubmit} className="space-y-4">
                <div className="space-y-1">
                  <label className="text-xs font-semibold text-slate-300">Your Full Name</label>
                  <input
                    type="text"
                    required
                    value={fullName}
                    onChange={(e) => setFullName(e.target.value)}
                    placeholder="e.g. Asad Siddiqui"
                    className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2.5 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                  />
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-semibold text-slate-300">Phone / WhatsApp</label>
                  <input
                    type="tel"
                    required
                    value={phone}
                    onChange={(e) => setPhone(e.target.value)}
                    placeholder="e.g. +92 300 0000000"
                    className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2.5 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                  />
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-semibold text-slate-300">Message</label>
                  <textarea
                    rows={3}
                    value={message}
                    onChange={(e) => setMessage(e.target.value)}
                    className="w-full bg-[#0B1220] text-white text-xs rounded-lg p-3 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                  />
                </div>

                <button
                  type="submit"
                  className="w-full py-3 px-4 rounded-xl bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C] font-bold text-xs transition-colors flex items-center justify-center gap-2 cursor-pointer shadow-md shadow-[#C5A46D]/20"
                >
                  <Send className="w-3.5 h-3.5" />
                  <span>Send Property Inquiry</span>
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};
