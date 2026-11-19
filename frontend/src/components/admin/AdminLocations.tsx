import React, { useCallback, useEffect, useState } from 'react';
import { Building2, FolderTree, MapPin, Plus, RefreshCw, Tag } from 'lucide-react';
import { api } from '../../services/api';
import {
  EnterpriseBadge,
  EnterpriseButton,
  EnterpriseCard,
  EnterpriseEmpty,
  EnterpriseNote,
  EnterprisePage,
  EnterpriseSpinner,
  enterpriseInputClass,
  titleCase,
} from './AdminEnterpriseUi';

interface LocationsProps {
  canManage: boolean;
}

export const AdminLocations: React.FC<LocationsProps> = ({ canManage }) => {
  const [sectors, setSectors] = useState<any[]>([]);
  const [categories, setCategories] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState('');
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const [sectorForm, setSectorForm] = useState({ name: '', phase: '', description: '' });
  const [blockForm, setBlockForm] = useState({ sector_id: '', name: '' });
  const [categoryForm, setCategoryForm] = useState({ key: '', name: '', group: 'residential', icon: '' });

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    const body = await api.getLocations();
    if (!body || body.success === false) {
      setError(body?.message || 'Could not load location structures.');
      setSectors([]);
      setCategories([]);
    } else {
      setSectors(Array.isArray(body.sectors) ? body.sectors : []);
      setCategories(Array.isArray(body.categories) ? body.categories : []);
      setBlockForm((prev) => ({
        ...prev,
        sector_id: prev.sector_id || String(body.sectors?.[0]?.id ?? ''),
      }));
    }
    setLoading(false);
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const handleSector = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!sectorForm.name.trim()) return;
    setBusy('sector');
    setError('');
    setNotice('');
    const result = await api.createLocationSector({
      name: sectorForm.name.trim(),
      phase: sectorForm.phase.trim() || undefined,
      description: sectorForm.description.trim() || undefined,
    });
    setBusy('');
    if (!result.ok) {
      setError(result.body?.message || 'Sector could not be created.');
      return;
    }
    setSectorForm({ name: '', phase: '', description: '' });
    setNotice('Sector created.');
    await load();
  };

  const handleBlock = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!blockForm.sector_id || !blockForm.name.trim()) return;
    setBusy('block');
    setError('');
    setNotice('');
    const result = await api.createLocationBlock({
      sector_id: blockForm.sector_id,
      name: blockForm.name.trim(),
    });
    setBusy('');
    if (!result.ok) {
      setError(result.body?.message || 'Block could not be created.');
      return;
    }
    setBlockForm((prev) => ({ ...prev, name: '' }));
    setNotice('Block created.');
    await load();
  };

  const handleCategory = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!categoryForm.key.trim() || !categoryForm.name.trim()) return;
    setBusy('category');
    setError('');
    setNotice('');
    const result = await api.createLocationCategory({
      key: categoryForm.key.trim().toLowerCase(),
      name: categoryForm.name.trim(),
      group: categoryForm.group,
      icon: categoryForm.icon.trim() || undefined,
    });
    setBusy('');
    if (!result.ok) {
      setError(result.body?.message || 'Category could not be created.');
      return;
    }
    setCategoryForm({ key: '', name: '', group: 'residential', icon: '' });
    setNotice('Property category created.');
    await load();
  };

  return (
    <EnterprisePage
      title="Locations & Categories"
      subtitle="Taisar Town sectors, blocks and property categories used across listings and search."
      actions={
        <EnterpriseButton variant="secondary" onClick={() => void load()}>
          <RefreshCw className={`w-3.5 h-3.5 ${loading ? 'animate-spin' : ''}`} /> Refresh
        </EnterpriseButton>
      }
    >
      {error && <EnterpriseNote tone="error">{error}</EnterpriseNote>}
      {notice && <EnterpriseNote tone="success">{notice}</EnterpriseNote>}
      {!canManage && (
        <EnterpriseNote tone="info">
          Location structures are read-only for your role. `properties.approve` (platform) is required to add sectors,
          blocks or categories.
        </EnterpriseNote>
      )}

      {loading ? (
        <EnterpriseSpinner label="Loading Taisar Town structures…" />
      ) : (
        <div className="grid grid-cols-1 xl:grid-cols-12 gap-6">
          <div className="xl:col-span-7 space-y-6">
            <EnterpriseCard title="Sectors & Blocks" subtitle={`${sectors.length} active sector(s)`}>
              {sectors.length === 0 ? (
                <EnterpriseEmpty title="No sectors configured" hint="Create the first Taisar Town sector." />
              ) : (
                <div className="space-y-3">
                  {sectors.map((sector) => (
                    <div key={sector.id} className="p-3 rounded-xl bg-[#07141E] border border-slate-800">
                      <div className="flex flex-wrap items-center justify-between gap-2">
                        <div className="flex items-center gap-2">
                          <MapPin className="w-4 h-4 text-[#E7C878]" />
                          <span className="text-xs font-bold text-white">{sector.name}</span>
                          {sector.phase && <EnterpriseBadge tone="gold">{sector.phase}</EnterpriseBadge>}
                          <span className="text-[10px] text-slate-500">{sector.slug}</span>
                        </div>
                        <span className="text-[10px] text-slate-400">{sector.listing_count ?? 0} public listing(s)</span>
                      </div>
                      {sector.description && (
                        <p className="text-[10px] text-slate-500 mt-1">{sector.description}</p>
                      )}
                      <div className="flex flex-wrap gap-1.5 mt-2">
                        {(sector.blocks ?? []).length === 0 ? (
                          <span className="text-[10px] text-slate-500">No blocks yet</span>
                        ) : (
                          sector.blocks.map((block: any) => (
                            <span key={block.id} className="px-1.5 py-0.5 rounded bg-slate-800 text-[9px] text-slate-300">
                              {block.name}
                            </span>
                          ))
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </EnterpriseCard>

            <EnterpriseCard title="Property Categories" subtitle={`${categories.length} active category/categories`}>
              {categories.length === 0 ? (
                <EnterpriseEmpty title="No categories configured" />
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-xs">
                    <thead className="bg-[#07141E] text-slate-400 uppercase tracking-wider text-[10px]">
                      <tr>
                        <th className="py-2.5 px-3 rounded-l-lg">Category</th>
                        <th className="py-2.5 px-3">Key</th>
                        <th className="py-2.5 px-3">Group</th>
                        <th className="py-2.5 px-3 rounded-r-lg text-right">Listings</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-800/80">
                      {categories.map((category) => (
                        <tr key={category.id} className="hover:bg-slate-800/30">
                          <td className="py-2.5 px-3 font-semibold text-white">
                            <span className="inline-flex items-center gap-1.5">
                              <Tag className="w-3 h-3 text-[#E7C878]" /> {category.name}
                            </span>
                          </td>
                          <td className="py-2.5 px-3 text-slate-400">{category.key}</td>
                          <td className="py-2.5 px-3 text-slate-300">{titleCase(category.group)}</td>
                          <td className="py-2.5 px-3 text-right text-slate-400">{category.listing_count ?? 0}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </EnterpriseCard>
          </div>

          <div className="xl:col-span-5 space-y-6">
            {canManage && (
              <>
                <EnterpriseCard title="Add Sector" subtitle="Creates a slug automatically and audits the change">
                  <form onSubmit={handleSector} className="space-y-2.5">
                    <input
                      className={enterpriseInputClass}
                      placeholder="Sector name *"
                      value={sectorForm.name}
                      onChange={(e) => setSectorForm((prev) => ({ ...prev, name: e.target.value }))}
                      required
                    />
                    <input
                      className={enterpriseInputClass}
                      placeholder="Phase (e.g. Phase 1)"
                      value={sectorForm.phase}
                      onChange={(e) => setSectorForm((prev) => ({ ...prev, phase: e.target.value }))}
                    />
                    <textarea
                      className={`${enterpriseInputClass} min-h-[60px]`}
                      placeholder="Description"
                      value={sectorForm.description}
                      onChange={(e) => setSectorForm((prev) => ({ ...prev, description: e.target.value }))}
                    />
                    <EnterpriseButton type="submit" disabled={busy === 'sector'} className="w-full">
                      <Plus className="w-3.5 h-3.5" /> Create sector
                    </EnterpriseButton>
                  </form>
                </EnterpriseCard>

                <EnterpriseCard title="Add Block" subtitle="Blocks belong to an existing sector">
                  <form onSubmit={handleBlock} className="space-y-2.5">
                    <select
                      className={enterpriseInputClass}
                      value={blockForm.sector_id}
                      onChange={(e) => setBlockForm((prev) => ({ ...prev, sector_id: e.target.value }))}
                      required
                    >
                      <option value="">Select a sector…</option>
                      {sectors.map((sector) => (
                        <option key={sector.id} value={sector.id}>{sector.name}</option>
                      ))}
                    </select>
                    <input
                      className={enterpriseInputClass}
                      placeholder="Block name (e.g. Block A) *"
                      value={blockForm.name}
                      onChange={(e) => setBlockForm((prev) => ({ ...prev, name: e.target.value }))}
                      required
                    />
                    <EnterpriseButton type="submit" disabled={busy === 'block'} className="w-full">
                      <FolderTree className="w-3.5 h-3.5" /> Create block
                    </EnterpriseButton>
                  </form>
                </EnterpriseCard>

                <EnterpriseCard title="Add Property Category" subtitle="Keys are unique and used by listing forms">
                  <form onSubmit={handleCategory} className="space-y-2.5">
                    <input
                      className={enterpriseInputClass}
                      placeholder="Key (e.g. residential_plot) *"
                      value={categoryForm.key}
                      onChange={(e) => setCategoryForm((prev) => ({ ...prev, key: e.target.value.replace(/[^a-z0-9_]/g, '') }))}
                      required
                    />
                    <input
                      className={enterpriseInputClass}
                      placeholder="Display name *"
                      value={categoryForm.name}
                      onChange={(e) => setCategoryForm((prev) => ({ ...prev, name: e.target.value }))}
                      required
                    />
                    <select
                      className={enterpriseInputClass}
                      value={categoryForm.group}
                      onChange={(e) => setCategoryForm((prev) => ({ ...prev, group: e.target.value }))}
                    >
                      <option value="residential">Residential</option>
                      <option value="commercial">Commercial</option>
                      <option value="rent">Rent</option>
                    </select>
                    <input
                      className={enterpriseInputClass}
                      placeholder="Icon name (optional)"
                      value={categoryForm.icon}
                      onChange={(e) => setCategoryForm((prev) => ({ ...prev, icon: e.target.value }))}
                    />
                    <EnterpriseButton type="submit" disabled={busy === 'category'} className="w-full">
                      <Building2 className="w-3.5 h-3.5" /> Create category
                    </EnterpriseButton>
                  </form>
                </EnterpriseCard>
              </>
            )}
          </div>
        </div>
      )}
    </EnterprisePage>
  );
};
