import React, { useState } from 'react';
import { useApp } from '../../context/AppContext';
import {
  Building2,
  MapPin,
  Search,
  Filter,
  CheckCircle2,
  ArrowRight,
  Sparkles
} from 'lucide-react';
import { formatPKRCurrency, toPublicProject } from '../../utils/logic';
import { Project, ProjectCategory } from '../../types';
import { Reveal } from '../common/Reveal';
import { EmptyState } from '../common/EmptyState';
import { ImageWithFallback } from '../common/ImageWithFallback';

export const ProjectsListingPage: React.FC = () => {
  const { projects, setSelectedProjectId, setCurrentView } = useApp();
  const [selectedCategory, setSelectedCategory] = useState<string>('All');
  const [searchQuery, setSearchQuery] = useState<string>('');

  const categories = [
    'All',
    'Residential Projects',
    'Commercial Projects',
    'Housing Societies',
    'Development Projects'
  ];

  const filteredProjects = projects.filter((p) => !p.deletedAt).map(toPublicProject).filter((p) => {
    if (selectedCategory !== 'All' && p.category !== selectedCategory) return false;
    if (searchQuery.trim() !== '') {
      const q = searchQuery.toLowerCase();
      return (
        p.title.toLowerCase().includes(q) ||
        p.location.toLowerCase().includes(q) ||
        p.sector.toLowerCase().includes(q)
      );
    }
    return true;
  });

  const handleOpenProject = (id: string) => {
    setSelectedProjectId(id);
    setCurrentView('project-detail');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  return (
    <div className="py-12 px-4 sm:px-6 lg:px-8 bg-[#0B1220] min-h-screen space-y-10">
      <div className="max-w-7xl mx-auto space-y-10">
        {/* Title Header */}
        <div className="text-center max-w-3xl mx-auto space-y-3">
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-[#C5A46D]/15 text-[#C5A46D] border border-[#C5A46D]/30">
            <Sparkles className="w-3.5 h-3.5" /> Flagship Master Schemes
          </span>
          <h1 className="font-serif-luxury text-3xl sm:text-4xl lg:text-5xl font-bold text-white">
            Housing Societies & Commercial Developments
          </h1>
          <p className="text-slate-400 text-sm">
            Explore verified master-planned enclaves, gated societies, and commercial hubs in Taiser Town Scheme 45, Karachi.
          </p>
        </div>

        {/* Search & Category Filter Bar */}
        <div className="flex flex-col md:flex-row items-center justify-between gap-4 p-4 rounded-2xl bg-[#101C2C] border border-slate-800">
          {/* Category Tabs */}
          <div className="flex flex-wrap items-center gap-1.5 w-full md:w-auto">
            {categories.map((cat) => {
              const isActive = selectedCategory === cat;
              return (
                <button
                  key={cat}
                  onClick={() => setSelectedCategory(cat)}
                  className={`px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-colors cursor-pointer ${
                    isActive
                      ? 'bg-[#C5A46D] text-[#101C2C] shadow'
                      : 'text-slate-300 hover:text-white hover:bg-slate-800'
                  }`}
                >
                  {cat}
                </button>
              );
            })}
          </div>

          {/* Search Input */}
          <div className="relative w-full md:w-72">
            <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input
              type="text"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              placeholder="Search by project name or sector..."
              className="w-full bg-[#0B1220] text-white text-xs rounded-lg pl-9 pr-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
            />
          </div>
        </div>

        {/* Projects Grid — or premium empty state */}
        {filteredProjects.length === 0 ? (
          <EmptyState
            icon={<Building2 className="w-9 h-9" />}
            chip="No Projects"
            title="No Projects Match Your Search"
            description="Try a different project name or sector — our development portfolio grows every quarter across Phases 1–3."
            actionLabel="Clear Search"
            onAction={() => {
              setSearchQuery('');
              setSelectedCategory('All');
            }}
          />
        ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          {filteredProjects.map((project, idx) => (
            <Reveal key={project.id} delay={Math.min(idx * 0.07, 0.35)} className="h-full">
            <div
              className="group bg-[#101C2C] rounded-2xl overflow-hidden border border-slate-800 hover:border-[#C5A46D]/50 shadow-xl transition-all duration-300 hover:-translate-y-1 flex flex-col justify-between h-full"
            >
              <div
                className="relative h-60 overflow-hidden cursor-pointer"
                onClick={() => handleOpenProject(project.id)}
              >
                <ImageWithFallback
                  src={project.coverImage}
                  alt={project.title}
                  loading="lazy"
                  decoding="async"
                  className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                />
                <div className="absolute inset-0 bg-gradient-to-t from-[#101C2C] via-transparent to-black/30" />

                <div className="absolute top-4 left-4 flex flex-col gap-1.5 items-start">
                  <span className="px-3 py-1 rounded-full text-xs font-semibold bg-emerald-600/90 text-white backdrop-blur-md">
                    {project.status}
                  </span>
                  <span className="px-2.5 py-0.5 rounded-full text-[10px] font-medium bg-black/60 text-slate-200 border border-white/10">
                    {project.category}
                  </span>
                </div>

                <div className="absolute bottom-3 left-4 right-4 flex items-center justify-between text-xs text-white">
                  <span className="flex items-center gap-1 font-medium truncate">
                    <MapPin className="w-3.5 h-3.5 text-[#C5A46D] shrink-0" />
                    {project.sector}
                  </span>
                  <span className="px-2 py-0.5 rounded bg-black/60 text-slate-300 text-[10px]">
                    Est. {project.completionYear}
                  </span>
                </div>
              </div>

              <div className="p-6 flex-1 flex flex-col justify-between space-y-4">
                <div className="space-y-2">
                  <h3
                    onClick={() => handleOpenProject(project.id)}
                    className="font-serif-luxury text-xl font-bold text-white group-hover:text-[#C5A46D] transition-colors cursor-pointer line-clamp-1"
                  >
                    {project.title}
                  </h3>
                  <p className="text-xs text-slate-400 line-clamp-2 leading-relaxed">
                    {project.description}
                  </p>
                </div>

                <div className="pt-2 border-t border-slate-800/80">
                  <div className="text-[11px] text-slate-500 uppercase tracking-wider font-semibold mb-2">
                    Key Highlights
                  </div>
                  <div className="flex flex-wrap gap-1.5">
                    {project.facilities.slice(0, 3).map((f, i) => (
                      <span
                        key={i}
                        className="px-2 py-1 rounded bg-[#0B1220] border border-slate-800 text-[11px] text-slate-300 flex items-center gap-1"
                      >
                        <CheckCircle2 className="w-3 h-3 text-[#C5A46D]" />
                        {f.name}
                      </span>
                    ))}
                  </div>
                </div>

                <div className="pt-4 border-t border-slate-800 flex items-center justify-between">
                  <div>
                    <span className="text-[10px] uppercase text-slate-400 block font-medium">
                      Starting Price
                    </span>
                    <span className="font-serif-luxury text-lg font-bold text-[#F9E7BA]">
                      {formatPKRCurrency(project.startingPricePKR)}
                    </span>
                  </div>

                  <button
                    onClick={() => handleOpenProject(project.id)}
                    className="px-4 py-2 rounded-lg bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C] font-bold text-xs transition-colors flex items-center gap-1.5 cursor-pointer shadow"
                  >
                    <span>View Project</span>
                    <ArrowRight className="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform" />
                  </button>
                </div>
              </div>
            </div>
            </Reveal>
          ))}
        </div>
        )}
      </div>
    </div>
  );
};

