import React, { useState } from 'react';
import { useApp } from '../../context/AppContext';
import {
  ArrowLeft,
  MapPin,
  Calendar,
  CheckCircle2,
  FileText,
  Play,
  Share2,
  Building2,
  Send,
  Sparkles,
  Layers
} from 'lucide-react';
import { formatPKRCurrency, toPublicProject } from '../../utils/logic';
import { VideoModal } from '../common/VideoModal';
import { Lightbox } from '../common/Lightbox';
import { EmptyState } from '../common/EmptyState';
import { ImageWithFallback } from '../common/ImageWithFallback';

export const ProjectDetailPage: React.FC = () => {
  const {
    projects,
    selectedProjectId,
    setCurrentView,
    cmsConfig,
    addInquiry,
    showToast
  } = useApp();

  const rawProject = projects.find((p) => p.id === selectedProjectId) || projects[0];
  const project = rawProject ? toPublicProject(rawProject) : undefined;
  const [activeImage, setActiveImage] = useState<string>(project?.coverImage || '');
  const [isVideoModalOpen, setIsVideoModalOpen] = useState<boolean>(false);
  const [lightboxIndex, setLightboxIndex] = useState<number | null>(null);

  // Quick inquiry state
  const [inquiryName, setInquiryName] = useState('');
  const [inquiryPhone, setInquiryPhone] = useState('');
  const [inquiryMsg, setInquiryMsg] = useState(
    project ? `I would like booking details and payment schedule for ${project.title}.` : ''
  );

  if (!project) {
    return (
      <div className="py-24 px-4 text-center max-w-lg mx-auto min-h-[60vh] flex items-center justify-center">
        <EmptyState
          icon={<Building2 className="w-8 h-8 text-[#C5A46D]" />}
          title="Project Not Found"
          description="The selected real estate development project is currently unavailable."
          actionLabel="Explore All Master Projects"
          onAction={() => setCurrentView('projects')}
        />
      </div>
    );
  }

  // Project gallery: cover + galleryImages
  const projectGallery: string[] = [project.coverImage, ...project.galleryImages];
  const openLightbox = () => {
    const idx = projectGallery.indexOf(activeImage || project.coverImage);
    setLightboxIndex(idx < 0 ? 0 : idx);
  };

  const handleInquirySubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!inquiryName || !inquiryPhone) {
      showToast('error', 'Please enter your name and phone number.');
      return;
    }

    addInquiry({
      fullName: inquiryName,
      phone: inquiryPhone,
      subject: `Project Booking Inquiry: ${project.title}`,
      preferredPropertyOrProject: project.title,
      message: inquiryMsg,
      source: 'Project Page'
    });

    setInquiryName('');
    setInquiryPhone('');
  };

  return (
    <div className="py-12 px-4 sm:px-6 lg:px-8 bg-[#0B1220] min-h-screen">
      <div className="max-w-7xl mx-auto space-y-10">
        {/* Top Navigation */}
        <div className="flex items-center justify-between">
          <button
            onClick={() => {
              setCurrentView('projects');
              window.scrollTo({ top: 0, behavior: 'smooth' });
            }}
            className="inline-flex items-center gap-2 text-xs font-semibold text-slate-300 hover:text-[#C5A46D] transition-colors cursor-pointer"
          >
            <ArrowLeft className="w-4 h-4" />
            <span>Back to All Projects</span>
          </button>

          <div className="flex items-center gap-3">
            <span className="px-3 py-1 rounded-full text-xs font-semibold bg-[#C5A46D]/15 text-[#C5A46D] border border-[#C5A46D]/30">
              {project.status}
            </span>
          </div>
        </div>

        {/* Project Header Title */}
        <div className="space-y-3">
          <div className="flex flex-wrap items-center gap-3 text-xs text-[#C5A46D]">
            <span className="flex items-center gap-1 font-semibold">
              <Building2 className="w-4 h-4" /> {project.category}
            </span>
            <span>•</span>
            <span className="flex items-center gap-1 text-slate-300">
              <MapPin className="w-3.5 h-3.5 text-[#C5A46D]" /> {project.location}
            </span>
            <span>•</span>
            <span className="text-slate-400">Completion: {project.completionYear}</span>
          </div>

          <h1 className="font-serif-luxury text-3xl sm:text-4xl lg:text-5xl font-bold text-white">
            {project.title}
          </h1>

          <p className="text-slate-300 text-sm sm:text-base font-light max-w-3xl">
            {project.tagline}
          </p>
        </div>

        {/* Image & Video Gallery Showcase */}
        <div className="space-y-4">
          <div
            className="group relative aspect-16/9 sm:aspect-21/9 max-h-[520px] rounded-2xl overflow-hidden border border-[#C5A46D]/30 bg-slate-900 shadow-2xl cursor-zoom-in"
            onClick={openLightbox}
            role="button"
            aria-label="Open project gallery full screen"
            tabIndex={0}
            onKeyDown={(e) => {
              if (e.key === 'Enter' || e.key === ' ') openLightbox();
            }}
          >
            <ImageWithFallback
              src={activeImage || project.coverImage}
              alt={project.title}
              fetchPriority="high"
              decoding="async"
              className="w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-700"
            />

            {/* Video Tour Button Overlay */}
            {project.videoUrl && (
              <button
                onClick={() => setIsVideoModalOpen(true)}
                className="absolute bottom-6 right-6 px-4 py-2.5 rounded-xl bg-[#101C2C]/90 hover:bg-[#101C2C] text-white border border-[#C5A46D]/50 backdrop-blur-md text-xs font-bold flex items-center gap-2 shadow-2xl cursor-pointer group"
              >
                <div className="w-7 h-7 rounded-full bg-[#C5A46D] text-[#101C2C] flex items-center justify-center group-hover:scale-110 transition-transform">
                  <Play className="w-3.5 h-3.5 fill-current ml-0.5" />
                </div>
                <span>Watch Architectural Tour</span>
              </button>
            )}
          </div>

          {/* Thumbnail Strip */}
          <div className="flex items-center gap-3 overflow-x-auto pb-2">
            {projectGallery.map((img, idx) => (
              <button
                key={idx}
                onClick={() => setActiveImage(img)}
                aria-label={`Show project image ${idx + 1}`}
                className={`relative w-24 h-16 rounded-xl overflow-hidden shrink-0 border-2 transition-all cursor-pointer ${
                  activeImage === img ? 'border-[#C5A46D] scale-105' : 'border-slate-800 opacity-60 hover:opacity-100'
                }`}
              >
                <ImageWithFallback src={img} alt="" loading="lazy" decoding="async" className="w-full h-full object-cover" />
              </button>
            ))}
          </div>
        </div>

        {/* Full-screen gallery lightbox (keyboard: ← → Esc) */}
        {project && (
          <Lightbox
            images={projectGallery}
            title={project.title}
            openIndex={lightboxIndex}
            onClose={() => setLightboxIndex(null)}
            onNavigate={setLightboxIndex}
          />
        )}

        {/* Main Details & Inquiry Sidebar Grid */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-10">
          {/* Main Info (8 cols) */}
          <div className="lg:col-span-8 space-y-10">
            {/* Overview Description */}
            <div className="bg-[#101C2C] p-8 rounded-2xl border border-slate-800 space-y-4 shadow-xl">
              <h3 className="font-serif-luxury text-2xl font-bold text-white">
                Project Overview & Scope
              </h3>
              <p className="text-slate-300 text-sm leading-relaxed whitespace-pre-line font-light">
                {project.description}
              </p>

              <div className="grid grid-cols-2 sm:grid-cols-3 gap-4 pt-4 border-t border-slate-800">
                <div>
                  <span className="text-[10px] text-slate-400 uppercase tracking-wider block">Starting Price</span>
                  <span className="font-serif-luxury text-lg font-bold text-[#F9E7BA]">
                    {formatPKRCurrency(project.startingPricePKR)}
                  </span>
                </div>
                <div>
                  <span className="text-[10px] text-slate-400 uppercase tracking-wider block">Developer</span>
                  <span className="text-xs font-semibold text-white truncate block">
                    {project.developerName}
                  </span>
                </div>
                <div>
                  <span className="text-[10px] text-slate-400 uppercase tracking-wider block">Available Sizes</span>
                  <span className="text-xs font-medium text-slate-300">
                    {project.plotSizes.join(', ')}
                  </span>
                </div>
              </div>
            </div>

            {/* Facilities & Amenities */}
            <div className="space-y-4">
              <h3 className="font-serif-luxury text-2xl font-bold text-white">
                World-Class Facilities & Infrastructure
              </h3>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {project.facilities.map((fac, i) => (
                  <div
                    key={i}
                    className="p-5 rounded-xl bg-[#101C2C] border border-slate-800 space-y-2 hover:border-[#C5A46D]/30 transition-colors"
                  >
                    <div className="flex items-center gap-2 text-[#C5A46D] font-bold text-sm">
                      <CheckCircle2 className="w-4 h-4" />
                      <span>{fac.name}</span>
                    </div>
                    <p className="text-xs text-slate-400 leading-relaxed font-light">
                      {fac.description}
                    </p>
                  </div>
                ))}
              </div>
            </div>

            {/* Attached Documents */}
            <div className="bg-[#101C2C] p-8 rounded-2xl border border-slate-800 space-y-4 shadow-xl">
              <h3 className="font-serif-luxury text-2xl font-bold text-white">
                Official Project Documents & Brochures
              </h3>
              <p className="text-xs text-slate-400">
                Request verified sector layouts, payment plans, and engineering specifications from our advisory desk.
              </p>

              <div className="space-y-3 pt-2">
                {project.documents.map((doc) => (
                  <div
                    key={doc.id}
                    className="flex items-center justify-between p-4 rounded-xl bg-[#0B1220] border border-slate-800 hover:border-slate-700 transition-colors"
                  >
                    <div className="flex items-center gap-3">
                      <div className="w-10 h-10 rounded-lg bg-[#C5A46D]/15 text-[#C5A46D] flex items-center justify-center">
                        <FileText className="w-5 h-5" />
                      </div>
                      <div>
                        <div className="text-xs font-bold text-white">{doc.title}</div>
                        <div className="text-[10px] text-slate-400">{doc.fileSize} • {doc.fileType.toUpperCase()}</div>
                      </div>
                    </div>

                    <button
                      type="button"
                      onClick={() => {
                        setInquiryMsg(`Please send a verified copy of "${doc.title}" for ${project.title}.`);
                        document.getElementById('project-booking')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        showToast('info', 'Add your name and phone, then send the request. It is stored with the agency inquiries.');
                      }}
                      className="px-3.5 py-1.5 rounded-lg bg-slate-800 hover:bg-[#C5A46D] hover:text-[#101C2C] text-xs font-semibold text-slate-200 transition-colors flex items-center gap-1.5 cursor-pointer"
                    >
                      <FileText className="w-3.5 h-3.5" />
                      <span>Request Copy</span>
                    </button>
                  </div>
                ))}
              </div>
            </div>
          </div>

          {/* Project Booking & Inquiry Sidebar (4 cols) */}
          <div className="lg:col-span-4 space-y-6">
            <div className="bg-[#101C2C] p-6 sm:p-8 rounded-2xl border border-[#C5A46D]/30 shadow-2xl space-y-6 sticky top-28">
              <div>
                <span className="text-[10px] uppercase font-bold text-[#C5A46D] tracking-wider block">
                  Direct Booking Desk
                </span>
                <h4 className="font-serif-luxury text-xl font-bold text-white mt-1">
                  Inquire About {project.title}
                </h4>
                <p className="text-xs text-slate-400 mt-1">
                  Get official allotment schedule, installment breakdown, and site visit passes.
                </p>
              </div>

              <form id="project-booking" onSubmit={handleInquirySubmit} className="space-y-4">
                <div className="space-y-1">
                  <label className="text-xs font-semibold text-slate-300">Your Full Name</label>
                  <input
                    type="text"
                    required
                    value={inquiryName}
                    onChange={(e) => setInquiryName(e.target.value)}
                    placeholder="e.g. Asad Farooqui"
                    className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2.5 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                  />
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-semibold text-slate-300">Phone / WhatsApp</label>
                  <input
                    type="tel"
                    required
                    value={inquiryPhone}
                    onChange={(e) => setInquiryPhone(e.target.value)}
                    placeholder="e.g. +92 321 0000000"
                    className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2.5 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                  />
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-semibold text-slate-300">Inquiry Message</label>
                  <textarea
                    rows={3}
                    value={inquiryMsg}
                    onChange={(e) => setInquiryMsg(e.target.value)}
                    className="w-full bg-[#0B1220] text-white text-xs rounded-lg p-3 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                  />
                </div>

                <button
                  type="submit"
                  className="w-full py-3 px-4 rounded-xl bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C] font-bold text-xs transition-colors flex items-center justify-center gap-2 cursor-pointer shadow-md shadow-[#C5A46D]/20"
                >
                  <Send className="w-3.5 h-3.5" />
                  <span>Send Booking Request</span>
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>

      {/* Video Modal */}
      {project.videoUrl && (
        <VideoModal
          isOpen={isVideoModalOpen}
          onClose={() => setIsVideoModalOpen(false)}
          title={project.title}
          videoUrl={project.videoUrl}
        />
      )}
    </div>
  );
};
