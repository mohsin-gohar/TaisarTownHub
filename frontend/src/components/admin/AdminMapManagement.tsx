import React, { useCallback, useEffect, useRef, useState } from 'react';
import {
  Map as MapIcon,
  Plus,
  Search,
  RefreshCw,
  Pencil,
  Trash2,
  Star,
  StarOff,
  Power,
  Image as ImageIcon,
  X,
  GripVertical,
  Eye,
  AlertTriangle,
} from 'lucide-react';
import { api } from '../../services/api';
import {
  EnterprisePage,
  EnterpriseCard,
  EnterpriseButton,
  EnterpriseSpinner,
  EnterpriseEmpty,
  EnterpriseNote,
  enterpriseInputClass,
} from './AdminEnterpriseUi';

interface MapItem {
  id: string;
  title: string;
  slug: string;
  sector: string | null;
  phase: string | null;
  category: string;
  description: string | null;
  image_path: string;
  image_url?: string;
  image_width: number | null;
  image_height: number | null;
  image_size_bytes: number | null;
  display_order: number;
  is_active: boolean;
  is_featured: boolean;
  created_by?: { name: string } | null;
  updated_by?: { name: string } | null;
  created_at?: string;
}

const CATEGORIES: Record<string, string> = {
  overall_map: 'Overall Map',
  sector_map: 'Sector Map',
  phase_map: 'Phase Map',
  guide_map: 'Guide Map',
};

const emptyForm = {
  title: '',
  sector: '',
  phase: '',
  category: 'sector_map',
  description: '',
  display_order: 0,
  is_active: true,
  is_featured: false,
};

export const AdminMapManagement: React.FC = () => {
  const [maps, setMaps] = useState<MapItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState('');
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [search, setSearch] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('All');

  // Modal state
  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<MapItem | null>(null);
  const [form, setForm] = useState({ ...emptyForm });
  const [imageFile, setImageFile] = useState<File | null>(null);
  const [imagePreview, setImagePreview] = useState<string | null>(null);
  const [replacingImage, setReplacingImage] = useState<MapItem | null>(null);
  const [replaceFile, setReplaceFile] = useState<File | null>(null);
  const [deleting, setDeleting] = useState<MapItem | null>(null);

  const fileInputRef = useRef<HTMLInputElement>(null);
  const replaceInputRef = useRef<HTMLInputElement>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    const body = await api.getAdminMaps();
    if (!body || body.success === false) {
      setError(body?.message || 'Could not load maps.');
      setMaps([]);
    } else {
      setMaps(Array.isArray(body.maps) ? body.maps : []);
    }
    setLoading(false);
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const openCreate = () => {
    setEditing(null);
    setForm({ ...emptyForm });
    setImageFile(null);
    setImagePreview(null);
    setModalOpen(true);
  };

  const openEdit = (map: MapItem) => {
    setEditing(map);
    setForm({
      title: map.title,
      sector: map.sector || '',
      phase: map.phase || '',
      category: map.category,
      description: map.description || '',
      display_order: map.display_order,
      is_active: map.is_active,
      is_featured: map.is_featured,
    });
    setImageFile(null);
    setImagePreview(null);
    setModalOpen(true);
  };

  const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0] || null;
    if (!file) return;
    if (!file.type.startsWith('image/')) {
      setError('Only image files are allowed.');
      return;
    }
    if (file.size > 10 * 1024 * 1024) {
      setError('Image must be 10MB or smaller.');
      return;
    }
    setImageFile(file);
    setImagePreview(URL.createObjectURL(file));
    setError('');
  };

  const handleReplaceChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0] || null;
    if (!file) return;
    if (!file.type.startsWith('image/')) {
      setError('Only image files are allowed.');
      return;
    }
    if (file.size > 10 * 1024 * 1024) {
      setError('Image must be 10MB or smaller.');
      return;
    }
    setReplaceFile(file);
    setError('');
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!form.title.trim()) {
      setError('Title is required.');
      return;
    }
    setBusy('save');
    setError('');
    setNotice('');

    if (editing) {
      const result = await api.updateMap(editing.id, {
        title: form.title.trim(),
        sector: form.sector.trim() || null,
        phase: form.phase.trim() || null,
        category: form.category,
        description: form.description.trim() || null,
        display_order: Number(form.display_order) || 0,
        is_active: form.is_active,
        is_featured: form.is_featured,
      });
      if (!result) {
        setError('Map could not be updated.');
        setBusy('');
        return;
      }
      setNotice('Map updated.');
    } else {
      if (!imageFile) {
        setError('An image is required to create a map.');
        setBusy('');
        return;
      }
      const fd = new FormData();
      fd.append('image', imageFile);
      fd.append('title', form.title.trim());
      fd.append('sector', form.sector.trim());
      fd.append('phase', form.phase.trim());
      fd.append('category', form.category);
      fd.append('description', form.description.trim());
      fd.append('display_order', String(Number(form.display_order) || 0));
      fd.append('is_active', String(form.is_active));
      fd.append('is_featured', String(form.is_featured));
      const result = await api.createMap(fd);
      if (!result) {
        setError('Map could not be created.');
        setBusy('');
        return;
      }
      setNotice('Map created.');
    }

    setBusy('');
    setModalOpen(false);
    await load();
  };

  const handleReplaceImage = async () => {
    if (!replacingImage || !replaceFile) return;
    setBusy('replace');
    setError('');
    setNotice('');
    const result = await api.replaceMapImage(replacingImage.id, replaceFile);
    setBusy('');
    if (!result) {
      setError('Image could not be replaced.');
      return;
    }
    setNotice('Image replaced.');
    setReplacingImage(null);
    setReplaceFile(null);
    await load();
  };

  const handleToggle = async (map: MapItem) => {
    setBusy(map.id);
    setError('');
    setNotice('');
    const result = await api.toggleMap(map.id);
    setBusy('');
    if (!result) {
      setError('Map could not be toggled.');
      return;
    }
    setNotice(`Map ${map.is_active ? 'disabled' : 'enabled'}.`);
    await load();
  };

  const handleFeature = async (map: MapItem) => {
    setBusy(map.id);
    setError('');
    setNotice('');
    const result = await api.featureMap(map.id);
    setBusy('');
    if (!result) {
      setError('Map could not be featured.');
      return;
    }
    setNotice('Map set as featured.');
    await load();
  };

  const handleDelete = async () => {
    if (!deleting) return;
    setBusy('delete');
    setError('');
    setNotice('');
    const result = await api.deleteMap(deleting.id);
    setBusy('');
    if (!result) {
      setError('Map could not be deleted.');
      return;
    }
    setNotice('Map deleted.');
    setDeleting(null);
    await load();
  };

  // Drag-and-drop reorder
  const dragItem = useRef<number | null>(null);
  const dragOverItem = useRef<number | null>(null);
  const [dragIndex, setDragIndex] = useState<number | null>(null);

  const handleDragStart = (index: number) => {
    dragItem.current = index;
    setDragIndex(index);
  };

  const handleDragEnter = (index: number) => {
    dragOverItem.current = index;
  };

  const handleDragEnd = async () => {
    if (dragItem.current === null || dragOverItem.current === null || dragItem.current === dragOverItem.current) {
      setDragIndex(null);
      return;
    }
    const reordered = [...maps];
    const [moved] = reordered.splice(dragItem.current, 1);
    reordered.splice(dragOverItem.current, 0, moved);
    setMaps(reordered);
    setDragIndex(null);
    setBusy('reorder');
    const result = await api.reorderMaps(reordered.map((m) => m.id));
    setBusy('');
    if (!result) {
      setError('Order could not be saved.');
      await load();
      return;
    }
    setNotice('Map order saved.');
  };

  const filteredMaps = maps.filter((m) => {
    const matchesSearch = !search || m.title.toLowerCase().includes(search.toLowerCase()) || (m.sector || '').toLowerCase().includes(search.toLowerCase());
    const matchesCategory = categoryFilter === 'All' || m.category === categoryFilter;
    return matchesSearch && matchesCategory;
  });

  return (
    <EnterprisePage
      title="Map Management"
      subtitle="Upload, organize, and publish Taiser Town maps. Changes appear on the public website immediately."
      actions={
        <EnterpriseButton onClick={openCreate} disabled={busy !== ''}>
          <Plus className="w-4 h-4" /> Upload Map
        </EnterpriseButton>
      }
    >
      {notice && <EnterpriseNote tone="success">{notice}</EnterpriseNote>}
      {error && <EnterpriseNote tone="error">{error}</EnterpriseNote>}

      {/* Filters */}
      <div className="flex flex-col sm:flex-row gap-3 mb-6">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
          <input
            type="text"
            placeholder="Search by title or sector..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className={`${enterpriseInputClass} pl-10`}
          />
        </div>
        <select
          value={categoryFilter}
          onChange={(e) => setCategoryFilter(e.target.value)}
          className={enterpriseInputClass}
        >
          <option value="All">All Categories</option>
          {Object.entries(CATEGORIES).map(([key, label]) => (
            <option key={key} value={key}>{label}</option>
          ))}
        </select>
        <button onClick={load} className="p-2.5 rounded-xl border border-slate-700 text-slate-400 hover:text-white" aria-label="Refresh">
          <RefreshCw className="w-4 h-4" />
        </button>
      </div>

      {loading ? (
        <EnterpriseSpinner label="Loading maps…" />
      ) : filteredMaps.length === 0 ? (
        <EnterpriseEmpty title="No maps found" hint="Upload a map to get started." />
      ) : (
        <div className="space-y-3">
          {filteredMaps.map((map, index) => (
            <div
              key={map.id}
              draggable
              onDragStart={() => handleDragStart(index)}
              onDragEnter={() => handleDragEnter(index)}
              onDragEnd={handleDragEnd}
              onDragOver={(e) => e.preventDefault()}
              className={`flex items-center gap-4 p-4 rounded-xl border bg-[#0a1a26] transition-colors ${
                dragIndex === index ? 'border-[#E7C878]/50 opacity-60' : 'border-slate-800'
              } ${!map.is_active ? 'opacity-50' : ''}`}
            >
              <div className="cursor-grab active:cursor-grabbing text-slate-500 hover:text-slate-300">
                <GripVertical className="w-5 h-5" />
              </div>
              <div className="w-20 h-14 rounded-lg overflow-hidden bg-slate-800 flex-shrink-0">
                <img
                  src={map.image_url || `/storage/${map.image_path}`}
                  alt={map.title}
                  className="w-full h-full object-cover"
                  onError={(e) => {
                    (e.target as HTMLImageElement).src = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="80" height="56"><rect fill="%231e293b" width="80" height="56"/><text fill="%2364748b" x="50%" y="50%" text-anchor="middle" font-size="8">No image</text></svg>';
                  }}
                />
              </div>
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 flex-wrap">
                  <h3 className="text-white font-semibold text-sm truncate">{map.title}</h3>
                  {map.is_featured && (
                    <span className="text-[10px] bg-[#E7C878] text-[#07141E] font-bold px-1.5 py-0.5 rounded">FEATURED</span>
                  )}
                  {!map.is_active && (
                    <span className="text-[10px] bg-slate-700 text-slate-300 px-1.5 py-0.5 rounded">DISABLED</span>
                  )}
                </div>
                <div className="flex items-center gap-2 mt-1 flex-wrap text-[11px] text-slate-400">
                  <span className="bg-slate-800 px-1.5 py-0.5 rounded">{CATEGORIES[map.category] || map.category}</span>
                  {map.sector && <span>{map.sector}</span>}
                  {map.phase && <span>· {map.phase}</span>}
                  {map.image_width && map.image_height && <span>· {map.image_width}×{map.image_height}</span>}
                  <span>· #{map.display_order}</span>
                </div>
              </div>
              <div className="flex items-center gap-1 flex-shrink-0">
                <button
                  onClick={() => openEdit(map)}
                  className="p-2 rounded-lg text-slate-400 hover:text-white hover:bg-white/10"
                  aria-label="Edit map"
                  title="Edit"
                >
                  <Pencil className="w-4 h-4" />
                </button>
                <button
                  onClick={() => { setReplacingImage(map); setReplaceFile(null); }}
                  className="p-2 rounded-lg text-slate-400 hover:text-white hover:bg-white/10"
                  aria-label="Replace image"
                  title="Replace image"
                >
                  <ImageIcon className="w-4 h-4" />
                </button>
                <button
                  onClick={() => handleToggle(map)}
                  disabled={busy === map.id}
                  className={`p-2 rounded-lg hover:bg-white/10 ${map.is_active ? 'text-green-400' : 'text-slate-400'}`}
                  aria-label={map.is_active ? 'Disable map' : 'Enable map'}
                  title={map.is_active ? 'Disable' : 'Enable'}
                >
                  <Power className="w-4 h-4" />
                </button>
                <button
                  onClick={() => handleFeature(map)}
                  disabled={busy === map.id}
                  className={`p-2 rounded-lg hover:bg-white/10 ${map.is_featured ? 'text-[#E7C878]' : 'text-slate-400'}`}
                  aria-label="Set as featured"
                  title="Feature"
                >
                  {map.is_featured ? <Star className="w-4 h-4" /> : <StarOff className="w-4 h-4" />}
                </button>
                <button
                  onClick={() => setDeleting(map)}
                  className="p-2 rounded-lg text-red-400 hover:bg-red-500/10"
                  aria-label="Delete map"
                  title="Delete"
                >
                  <Trash2 className="w-4 h-4" />
                </button>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Create/Edit Modal */}
      {modalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80" onClick={() => setModalOpen(false)}>
          <div className="w-full max-w-lg bg-[#0a1a26] border border-slate-800 rounded-2xl p-6 max-h-[90vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
            <div className="flex items-center justify-between mb-6">
              <h2 className="font-serif-luxury text-xl font-bold text-white">
                {editing ? 'Edit Map' : 'Upload New Map'}
              </h2>
              <button onClick={() => setModalOpen(false)} className="text-slate-400 hover:text-white" aria-label="Close">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <label className="block text-xs text-slate-400 mb-1.5">Title *</label>
                <input
                  type="text"
                  value={form.title}
                  onChange={(e) => setForm({ ...form, title: e.target.value })}
                  className={enterpriseInputClass}
                  placeholder="e.g. Sector 70-D Map"
                  required
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs text-slate-400 mb-1.5">Sector</label>
                  <input
                    type="text"
                    value={form.sector}
                    onChange={(e) => setForm({ ...form, sector: e.target.value })}
                    className={enterpriseInputClass}
                    placeholder="e.g. Sector 70-D"
                  />
                </div>
                <div>
                  <label className="block text-xs text-slate-400 mb-1.5">Phase</label>
                  <input
                    type="text"
                    value={form.phase}
                    onChange={(e) => setForm({ ...form, phase: e.target.value })}
                    className={enterpriseInputClass}
                    placeholder="e.g. Phase 1"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs text-slate-400 mb-1.5">Category</label>
                  <select
                    value={form.category}
                    onChange={(e) => setForm({ ...form, category: e.target.value })}
                    className={enterpriseInputClass}
                  >
                    {Object.entries(CATEGORIES).map(([key, label]) => (
                      <option key={key} value={key}>{label}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-xs text-slate-400 mb-1.5">Display Order</label>
                  <input
                    type="number"
                    min={0}
                    value={form.display_order}
                    onChange={(e) => setForm({ ...form, display_order: parseInt(e.target.value) || 0 })}
                    className={enterpriseInputClass}
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs text-slate-400 mb-1.5">Description</label>
                <textarea
                  value={form.description}
                  onChange={(e) => setForm({ ...form, description: e.target.value })}
                  className={`${enterpriseInputClass} min-h-[80px]`}
                  placeholder="Describe what this map shows..."
                />
              </div>

              {!editing && (
                <div>
                  <label className="block text-xs text-slate-400 mb-1.5">Map Image * (JPEG, PNG, WebP — max 10MB)</label>
                  <input
                    ref={fileInputRef}
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    onChange={handleImageChange}
                    className="hidden"
                  />
                  {imagePreview ? (
                    <div className="relative rounded-xl overflow-hidden border border-slate-700">
                      <img src={imagePreview} alt="Preview" className="w-full h-40 object-cover" />
                      <button
                        type="button"
                        onClick={() => { setImageFile(null); setImagePreview(null); }}
                        className="absolute top-2 right-2 p-1.5 rounded-lg bg-black/60 text-white"
                        aria-label="Remove image"
                      >
                        <X className="w-4 h-4" />
                      </button>
                    </div>
                  ) : (
                    <button
                      type="button"
                      onClick={() => fileInputRef.current?.click()}
                      className="w-full py-8 rounded-xl border-2 border-dashed border-slate-700 text-slate-400 hover:border-[#E7C878]/50 hover:text-[#E7C878] transition-colors text-sm"
                    >
                      <ImageIcon className="w-6 h-6 mx-auto mb-2" />
                      Click to select image
                    </button>
                  )}
                </div>
              )}

              <div className="flex items-center gap-4">
                <label className="flex items-center gap-2 text-sm text-slate-300 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={form.is_active}
                    onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
                    className="rounded border-slate-600"
                  />
                  Active
                </label>
                <label className="flex items-center gap-2 text-sm text-slate-300 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={form.is_featured}
                    onChange={(e) => setForm({ ...form, is_featured: e.target.checked })}
                    className="rounded border-slate-600"
                  />
                  Featured
                </label>
              </div>

              <div className="flex gap-3 pt-2">
                <EnterpriseButton type="button" onClick={() => setModalOpen(false)} variant="secondary" disabled={busy !== ''}>
                  Cancel
                </EnterpriseButton>
                <EnterpriseButton type="submit" disabled={busy !== ''}>
                  {busy === 'save' ? 'Saving…' : editing ? 'Save Changes' : 'Upload Map'}
                </EnterpriseButton>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Replace Image Modal */}
      {replacingImage && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80" onClick={() => setReplacingImage(null)}>
          <div className="w-full max-w-md bg-[#0a1a26] border border-slate-800 rounded-2xl p-6" onClick={(e) => e.stopPropagation()}>
            <div className="flex items-center justify-between mb-4">
              <h2 className="font-serif-luxury text-lg font-bold text-white">Replace Image</h2>
              <button onClick={() => setReplacingImage(null)} className="text-slate-400 hover:text-white" aria-label="Close">
                <X className="w-5 h-5" />
              </button>
            </div>
            <p className="text-slate-400 text-sm mb-4">
              Replace the image for <span className="text-white font-medium">{replacingImage.title}</span>. The old image file will be deleted.
            </p>
            <input
              ref={replaceInputRef}
              type="file"
              accept="image/jpeg,image/png,image/webp"
              onChange={handleReplaceChange}
              className="hidden"
            />
            <button
              onClick={() => replaceInputRef.current?.click()}
              className="w-full py-8 rounded-xl border-2 border-dashed border-slate-700 text-slate-400 hover:border-[#E7C878]/50 hover:text-[#E7C878] transition-colors text-sm"
            >
              <ImageIcon className="w-6 h-6 mx-auto mb-2" />
              {replaceFile ? replaceFile.name : 'Click to select new image'}
            </button>
            <div className="flex gap-3 mt-4">
              <EnterpriseButton variant="secondary" onClick={() => setReplacingImage(null)} disabled={busy !== ''}>
                Cancel
              </EnterpriseButton>
              <EnterpriseButton onClick={handleReplaceImage} disabled={!replaceFile || busy !== ''}>
                {busy === 'replace' ? 'Replacing…' : 'Replace Image'}
              </EnterpriseButton>
            </div>
          </div>
        </div>
      )}

      {/* Delete Confirmation */}
      {deleting && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80" onClick={() => setDeleting(null)}>
          <div className="w-full max-w-md bg-[#0a1a26] border border-slate-800 rounded-2xl p-6" onClick={(e) => e.stopPropagation()}>
            <div className="flex items-center gap-3 mb-4">
              <AlertTriangle className="w-6 h-6 text-red-400" />
              <h2 className="font-serif-luxury text-lg font-bold text-white">Delete Map</h2>
            </div>
            <p className="text-slate-400 text-sm mb-2">
              Are you sure you want to delete <span className="text-white font-medium">{deleting.title}</span>?
            </p>
            <p className="text-slate-500 text-xs mb-6">
              This permanently removes the map record and its image file. This action cannot be undone.
            </p>
            <div className="flex gap-3">
              <EnterpriseButton variant="secondary" onClick={() => setDeleting(null)} disabled={busy !== ''}>
                Cancel
              </EnterpriseButton>
              <EnterpriseButton onClick={handleDelete} disabled={busy !== ''} variant="danger">
                {busy === 'delete' ? 'Deleting…' : 'Delete Map'}
              </EnterpriseButton>
            </div>
          </div>
        </div>
      )}

      {/* Preview hint */}
      <div className="mt-6 flex items-center gap-2 text-xs text-slate-500">
        <Eye className="w-3.5 h-3.5" />
        <span>Maps appear on the public <span className="text-[#E7C878]">/maps</span> page immediately after saving.</span>
      </div>
    </EnterprisePage>
  );
};
