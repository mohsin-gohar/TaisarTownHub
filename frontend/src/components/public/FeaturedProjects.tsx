import React from 'react';
import { useApp } from '../../context/AppContext';
import {
  Building2,
  MapPin,
  Calendar,
  CheckCircle,
  ArrowRight,
  ShieldCheck,
  Layers,
  Sparkles
} from 'lucide-react';
import { formatPKRCurrency } from '../../utils/logic';
import { Project } from '../../types';
import { toPublicProject } from '../../utils/logic';
import { ImageWithFallback } from '../../components/common/ImageWithFallback';
import { Reveal } from '../common/Reveal';

export const FeaturedProjects: React.FC = () => {
  const { projects, setSelectedProjectId, setCurrentView } = useApp();

  const handleOpenProject = (project: Project) => {
    setSelectedProjectId(project.id);
    setCurrentView('project-detail');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  return (
    <section className="py-20 px-4 sm:px-6 lg:px-8 bg-[#07141E] border-t border-[#E7C878]/15">
      <div className="max-w-7xl mx-auto">
        {/* Section Header */}
        <div className="flex flex-col md:flex-row md:items-end justify-between mb-12">
          <div className="space-y-2">
            <span className="inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-[#E7C878]">
              <Sparkles className="w-3.5 h-3.5" /> Flagship Developments
            </span>
            <h2 className="font-serif-luxury text-3xl sm:text-4xl font-bold text-white">
              Featured Housing & Commercial Projects
            </h2>
            <p className="text-slate-400 text-sm max-w-xl">
              Carefully curated master-planned communities, gated enclaves, and high-footfall commercial avenues in Taiser Town Scheme 45.
            </p>
          </div>
          <button
            onClick={() => {
              setCurrentView('projects');
              window.scrollTo({ top: 0, behavior: 'smooth' });
            }}
            className="mt-4 md:mt-0 inline-flex items-center gap-2 text-sm font-semibold text-[#E7C878] hover:text-[#F6E3B4] group transition-colors cursor-pointer"
          >
            <span>View All Projects ({projects.length})</span>
            <ArrowRight className="w-4 h-4 group-hover:translate-x-1 transition-transform" />
          </button>
        </div>

        {/* Project Cards Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          {projects.filter((p) => !p.deletedAt).map(toPublicProject).map((project, idx) => {
            const isPossession = project.status === 'Ready for Possession';
            const isBooking = project.status === 'Booking Open';

            return (
              <Reveal key={project.id} delay={Math.min(idx * 0.07, 0.35)} className="h-full">
              <div className="group bg-[#0B1220] rounded-2xl overflow-hidden border border-slate-800 hover:border-[#E7C878]/50 shadow-xl transition-all duration-300 hover:-translate-y-1 flex flex-col h-full">
                {/* Image Container with Badges */}
                <div className="relative h-60 overflow-hidden cursor-pointer" onClick={() => handleOpenProject(project)}>
                  <ImageWithFallback
                    src={project.coverImage}
                    alt={project.title}
                    className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                  />
                  <div className="absolute inset-0 bg-gradient-to-t from-[#0B1220] via-transparent to-black/40" />

                  {/* Status Badge */}
                  <div className="absolute top-4 left-4 flex flex-col gap-1.5 items-start">
                    <span
                      className={`px-3 py-1 rounded-full text-xs font-semibold backdrop-blur-md shadow-md ${
                        isPossession
                          ? 'bg-emerald-600/90 text-white'
                          : isBooking
                          ? 'bg-[#E7C878]/95 text-[#0B1220]'
                          : 'bg-[#E7C878]/90 text-[#0B1220]'
                      }`}
                    >
                      {project.status}
                    </span>
                    <span className="px-2.5 py-0.5 rounded-full text-[10px] font-medium bg-black/60 text-slate-200 backdrop-blur-md border border-white/10">
                      {project.category}
                    </span>
                  </div>

                  {/* Location badge */}
                  <div className="absolute bottom-3 left-4 right-4 flex items-center justify-between text-xs text-white">
                    <span className="flex items-center gap-1.5 font-medium truncate drop-shadow">
                      <MapPin className="w-3.5 h-3.5 text-[#E7C878] shrink-0" />
                      {project.sector}
                    </span>
                    <span className="px-2 py-0.5 rounded bg-black/60 text-slate-300 text-[10px]">
                      Est. {project.completionYear}
                    </span>
                  </div>
                </div>

                {/* Content Body */}
                <div className="p-6 flex-1 flex flex-col justify-between space-y-4">
                  <div className="space-y-2">
                    <h3
                      onClick={() => handleOpenProject(project)}
                      className="font-serif-luxury text-xl font-bold text-white group-hover:text-[#E7C878] transition-colors cursor-pointer line-clamp-1"
                    >
                      {project.title}
                    </h3>
                    <p className="text-xs text-slate-400 line-clamp-2 leading-relaxed">
                      {project.description}
                    </p>
                  </div>

                  {/* Facilities preview */}
                  <div className="pt-2 border-t border-slate-800/80">
                    <div className="text-[11px] text-slate-500 uppercase tracking-wider font-semibold mb-2">
                      Key Highlights
                    </div>
                    <div className="flex flex-wrap gap-1.5">
                      {project.facilities.slice(0, 3).map((f, i) => (
                        <span
                          key={i}
                          className="px-2 py-1 rounded bg-[#07141E] border border-slate-800 text-[11px] text-slate-300 flex items-center gap-1"
                        >
                          <CheckCircle className="w-3 h-3 text-[#E7C878]" />
                          {f.name}
                        </span>
                      ))}
                      {project.facilities.length > 3 && (
                        <span className="px-2 py-1 rounded bg-[#07141E] text-[11px] text-slate-400">
                          +{project.facilities.length - 3} more
                        </span>
                      )}
                    </div>
                  </div>

                  {/* Pricing and Action */}
                  <div className="pt-4 border-t border-slate-800 flex items-center justify-between">
                    <div>
                      <span className="text-[10px] uppercase text-slate-400 block font-medium">
                        Starting From
                      </span>
                      <span className="font-serif-luxury text-lg font-bold text-[#F6E3B4]">
                        {formatPKRCurrency(project.startingPricePKR)}
                      </span>
                    </div>

                    <button
                      onClick={() => handleOpenProject(project)}
                      className="px-4 py-2 rounded-lg bg-[#E7C878]/15 hover:bg-[#E7C878] text-[#E7C878] hover:text-[#0B1220] font-semibold text-xs transition-all border border-[#E7C878]/40 flex items-center gap-1.5 cursor-pointer"
                    >
                      <span>View Project</span>
                      <ArrowRight className="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform" />
                    </button>
                  </div>
                </div>
              </div>
              </Reveal>
            );
          })}
        </div>
      </div>
    </section>
  );
};

