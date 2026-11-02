import React, { useState } from 'react';
import { useApp } from '../../context/AppContext';
import {
  Building2,
  Plus,
  Edit2,
  Trash2,
  Check,
  X,
  MapPin,
  Sparkles,
  Image,
  ExternalLink
} from 'lucide-react';
import { Project, ProjectCategory, ProjectStatus } from '../../types';
import { formatPKRCurrency } from '../../utils/logic';

export const AdminProjects: React.FC = () => {
  const {
    projects,
    addProject,
    updateProject,
    deleteProject,
    setSelectedProjectId,
    setCurrentView,
    showToast,
    confirmAction
  } = useApp();

  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingProjectId, setEditingProjectId] = useState<string | null>(null);

  // Form State
  const [formData, setFormData] = useState({
    title: '',
    tagline: '',
    category: 'Housing Societies' as ProjectCategory,
    location: 'Sector 70-D, Taiser Town, Karachi',
    sector: 'Sector 70-D',
    description: '',
    status: 'Ready for Possession' as ProjectStatus,
    isFeatured: true,
    coverImage: 'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=1200&q=80',
    startingPricePKR: 3500000,
    plotSizes: '80 Sq. Yds, 120 Sq. Yds, 240 Sq. Yds',
    completionYear: '2026',
    developerName: 'Allied Builders & MDA Consortium',
  });

  const handleOpenAdd = () => {
    setEditingProjectId(null);
    setFormData({
      title: '',
      tagline: '',
      category: 'Housing Societies',
      location: 'Sector 70-D, Taiser Town, Karachi',
      sector: 'Sector 70-D',
      description: '',
      status: 'Ready for Possession',
      isFeatured: true,
      coverImage: 'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=1200&q=80',
      startingPricePKR: 3500000,
      plotSizes: '80 Sq. Yds, 120 Sq. Yds, 240 Sq. Yds',
      completionYear: '2026',
      developerName: 'Allied Builders & MDA Consortium',
    });
    setIsModalOpen(true);
  };

  const handleOpenEdit = (project: Project) => {
    setEditingProjectId(project.id);
    setFormData({
      title: project.title,
      tagline: project.tagline,
      category: project.category,
      location: project.location,
      sector: project.sector,
      description: project.description,
      status: project.status,
      isFeatured: project.isFeatured,
      coverImage: project.coverImage,
      startingPricePKR: project.startingPricePKR,
      plotSizes: project.plotSizes.join(', '),
      completionYear: project.completionYear,
      developerName: project.developerName,
    });
    setIsModalOpen(true);
  };

  const handleSave = (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.title.trim()) {
      showToast('error', 'Please enter project title.');
      return;
    }

    const sizes = formData.plotSizes.split(',').map((s) => s.trim()).filter(Boolean);

    if (editingProjectId) {
      updateProject(editingProjectId, {
        title: formData.title,
        tagline: formData.tagline,
        category: formData.category,
        location: formData.location,
        sector: formData.sector,
        description: formData.description,
        status: formData.status,
        isFeatured: formData.isFeatured,
        coverImage: formData.coverImage,
        startingPricePKR: Number(formData.startingPricePKR),
        plotSizes: sizes,
        completionYear: formData.completionYear,
        developerName: formData.developerName,
      });
    } else {
      addProject({
        title: formData.title,
        tagline: formData.tagline,
        category: formData.category,
        location: formData.location,
        sector: formData.sector,
        description: formData.description,
        status: formData.status,
        isFeatured: formData.isFeatured,
        coverImage: formData.coverImage,
        galleryImages: [formData.coverImage],
        facilities: [
          { name: '100ft Central Boulevard', iconName: 'Compass', description: 'Dual carriageway with LED smart lighting' },
          { name: 'Underground Electrification', iconName: 'Zap', description: 'Underground safe wiring' },
          { name: 'Community Park', iconName: 'Trees', description: 'Green park with jogging tracks' }
        ],
        documents: [],
        startingPricePKR: Number(formData.startingPricePKR),
        plotSizes: sizes,
        completionYear: formData.completionYear,
        developerName: formData.developerName,
      });
    }

    setIsModalOpen(false);
  };

  const handleDelete = async (id: string, title: string) => {
    const ok = await confirmAction(`Are you sure you want to delete project "${title}"?`, {
      title: 'Delete project',
      confirmLabel: 'Delete'
    });
    if (ok) {
      deleteProject(id);
    }
  };

  return (
    <div className="space-y-6 animate-fadeIn">
      {/* Header bar */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-6 rounded-2xl bg-[#101C2C] border border-slate-800 shadow-xl">
        <div>
          <h2 className="font-serif-luxury text-2xl font-bold text-white">
            Project Portfolio Management
          </h2>
          <p className="text-xs text-slate-400">
            Create, edit, and publish master-planned schemes for Taiser Town Scheme 45.
          </p>
        </div>
        <button
          onClick={handleOpenAdd}
          className="px-4 py-2.5 rounded-xl bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C] font-bold text-xs flex items-center gap-2 cursor-pointer shadow-lg shadow-[#C5A46D]/20 self-start sm:self-auto"
        >
          <Plus className="w-4 h-4" />
          <span>Add New Project</span>
        </button>
      </div>

      {/* Projects Table */}
      <div className="bg-[#101C2C] rounded-2xl border border-slate-800 overflow-hidden shadow-xl">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-[#0B1220] text-slate-400 uppercase tracking-wider text-[10px]">
              <tr>
                <th className="py-3 px-4">Project</th>
                <th className="py-3 px-4">Category</th>
                <th className="py-3 px-4">Sector</th>
                <th className="py-3 px-4">Starting Price</th>
                <th className="py-3 px-4">Status</th>
                <th className="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800">
              {projects.filter((p) => !p.deletedAt).map((proj) => (
                <tr key={proj.id} className="hover:bg-slate-800/40 transition-colors">
                  <td className="py-3.5 px-4">
                    <div className="flex items-center gap-3">
                      <img
                        src={proj.coverImage}
                        alt={proj.title}
                        className="w-12 h-10 rounded-lg object-cover border border-slate-700"
                      />
                      <div>
                        <span className="font-bold text-white text-sm block">
                          {proj.title}
                        </span>
                        <span className="text-[11px] text-slate-400 line-clamp-1">
                          {proj.tagline}
                        </span>
                      </div>
                    </div>
                  </td>
                  <td className="py-3.5 px-4 text-slate-300">
                    {proj.category}
                  </td>
                  <td className="py-3.5 px-4 text-[#C5A46D] font-medium">
                    {proj.sector}
                  </td>
                  <td className="py-3.5 px-4 font-serif-luxury font-bold text-white">
                    {formatPKRCurrency(proj.startingPricePKR)}
                  </td>
                  <td className="py-3.5 px-4">
                    <span className={`px-2.5 py-0.5 rounded-full text-[10px] font-bold border ${
                      proj.status === 'Ready for Possession' ? 'bg-[#C5A46D]/15 text-[#F9E7BA] border-[#C5A46D]/30' :
                      proj.status === 'Booking Open' ? 'bg-[#C5A46D]/25 text-[#F9E7BA] border-[#C5A46D]/40' :
                      proj.status === 'Under Development' ? 'bg-[#C5A46D]/10 text-[#C5A46D] border-[#C5A46D]/30' :
                      'bg-slate-800 text-slate-300 border-slate-700'
                    }`}>
                      {proj.status}
                    </span>
                  </td>
                  <td className="py-3.5 px-4 text-right">
                    <div className="flex items-center justify-end gap-2">
                      <button
                        onClick={() => {
                          setSelectedProjectId(proj.id);
                          setCurrentView('project-detail');
                        }}
                        className="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300"
                        title="View Public Page"
                      >
                        <ExternalLink className="w-3.5 h-3.5" />
                      </button>
                      <button
                        onClick={() => handleOpenEdit(proj)}
                        className="p-1.5 rounded-lg bg-[#C5A46D]/20 hover:bg-[#C5A46D] text-[#C5A46D] hover:text-[#101C2C] transition-colors"
                        title="Edit Project"
                      >
                        <Edit2 className="w-3.5 h-3.5" />
                      </button>
                      <button
                        onClick={() => handleDelete(proj.id, proj.title)}
                        className="p-1.5 rounded-lg bg-slate-800 text-slate-400 hover:text-white hover:bg-slate-700 transition-colors"
                        title="Delete Project"
                      >
                        <Trash2 className="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Add / Edit Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md animate-fadeIn">
          <div className="relative w-full max-w-2xl bg-[#101C2C] border border-[#C5A46D]/40 rounded-2xl p-6 sm:p-8 shadow-2xl space-y-6 max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between pb-4 border-b border-slate-800">
              <h3 className="font-serif-luxury text-xl font-bold text-white">
                {editingProjectId ? 'Edit Project' : 'Add New Real Estate Project'}
              </h3>
              <button
                onClick={() => setIsModalOpen(false)}
                className="text-slate-400 hover:text-white p-1 rounded-lg"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSave} className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-1">
                  <label className="text-xs font-semibold text-slate-300">Project Title</label>
                  <input
                    type="text"
                    required
                    value={formData.title}
                    onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                    placeholder="e.g. Al-Samnan Residency Phase 2"
                    className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                  />
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-semibold text-slate-300">Category</label>
                  <select
                    value={formData.category}
                    onChange={(e) => setFormData({ ...formData, category: e.target.value as ProjectCategory })}
                    className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none cursor-pointer"
                  >
                    <option value="Housing Societies">Housing Societies</option>
                    <option value="Residential Projects">Residential Projects</option>
                    <option value="Commercial Projects">Commercial Projects</option>
                    <option value="Development Projects">Development Projects</option>
                  </select>
                </div>
              </div>

              <div className="space-y-1">
                <label className="text-xs font-semibold text-slate-300">Tagline / Subtitle</label>
                <input
                  type="text"
                  value={formData.tagline}
                  onChange={(e) => setFormData({ ...formData, tagline: e.target.value })}
                  placeholder="e.g. Luxury Gated Residential Enclave with 100ft Boulevard"
                  className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                />
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div className="space-y-1">
                  <label className="text-xs font-semibold text-slate-300">Sector</label>
                  <input
                    type="text"
                    required
                    value={formData.sector}
                    onChange={(e) => setFormData({ ...formData, sector: e.target.value })}
                    placeholder="Sector 70-D"
                    className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                  />
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-semibold text-slate-300">Status</label>
                  <select
                    value={formData.status}
                    onChange={(e) => setFormData({ ...formData, status: e.target.value as ProjectStatus })}
                    className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none cursor-pointer"
                  >
                    <option value="Ready for Possession">Ready for Possession</option>
                    <option value="Booking Open">Booking Open</option>
                    <option value="Under Development">Under Development</option>
                    <option value="Upcoming">Upcoming</option>
                  </select>
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-semibold text-slate-300">Starting Price (PKR)</label>
                  <input
                    type="number"
                    required
                    value={formData.startingPricePKR}
                    onChange={(e) => setFormData({ ...formData, startingPricePKR: Number(e.target.value) })}
                    className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                  />
                </div>
              </div>

              <div className="space-y-1">
                <label className="text-xs font-semibold text-slate-300">Cover Image URL</label>
                <input
                  type="url"
                  required
                  value={formData.coverImage}
                  onChange={(e) => setFormData({ ...formData, coverImage: e.target.value })}
                  placeholder="https://..."
                  className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                />
              </div>

              <div className="space-y-1">
                <label className="text-xs font-semibold text-slate-300">Available Plot Sizes (comma-separated)</label>
                <input
                  type="text"
                  value={formData.plotSizes}
                  onChange={(e) => setFormData({ ...formData, plotSizes: e.target.value })}
                  placeholder="80 Sq. Yds, 120 Sq. Yds, 240 Sq. Yds"
                  className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                />
              </div>

              <div className="space-y-1">
                <label className="text-xs font-semibold text-slate-300">Detailed Description</label>
                <textarea
                  rows={4}
                  required
                  value={formData.description}
                  onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                  placeholder="Describe location advantages, road widths, utilities, and possession schedules..."
                  className="w-full bg-[#0B1220] text-white text-xs rounded-lg p-3 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                />
              </div>

              <div className="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                <button
                  type="button"
                  onClick={() => setIsModalOpen(false)}
                  className="px-4 py-2 rounded-lg bg-slate-800 text-slate-300 text-xs font-semibold hover:bg-slate-700"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-6 py-2 rounded-lg bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C] text-xs font-bold shadow"
                >
                  {editingProjectId ? 'Update Project' : 'Publish Project'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

