import React, { useState } from 'react';
import { useApp } from '../../context/AppContext';
import { getAuditLog, type AuditEntry } from '../../domain/auditLog';
import { Trash2, RotateCcw, Shield, Clock, FileText, Users, UserCheck, Briefcase, Home, Building2, Image } from 'lucide-react';

export const AdminAudit: React.FC = () => {
  const {
    properties, sellers, buyers, dealers, projects, mediaItems,
    restoreProperty, restoreSeller, restoreBuyer, restoreDealer, restoreProject, restoreMediaItem,
    currentUser,
  } = useApp();

  const [tab, setTab] = useState<'audit' | 'trash'>('audit');
  const audit = getAuditLog();

  const trashedProperties = properties.filter((p) => p.deletedAt);
  const trashedSellers = sellers.filter((s) => s.deletedAt);
  const trashedBuyers = buyers.filter((b) => b.deletedAt);
  const trashedDealers = dealers.filter((d) => d.deletedAt);
  const trashedProjects = projects.filter((p) => p.deletedAt);
  const trashedMedia = mediaItems.filter((m) => m.deletedAt);

  const totalTrashed = trashedProperties.length + trashedSellers.length + trashedBuyers.length + trashedDealers.length + trashedProjects.length + trashedMedia.length;

  return (
    <div className="space-y-6 animate-fadeIn">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h2 className="font-serif-luxury text-2xl font-bold text-white">Audit Trail & Recycle Bin</h2>
          <p className="text-xs text-slate-400 mt-1">
            Append-only record of sensitive actions, plus soft-deleted records that can be restored.
          </p>
        </div>
        <div className="flex gap-2">
          <button
            onClick={() => setTab('audit')}
            className={`px-3 py-1.5 rounded-lg text-xs font-semibold cursor-pointer ${tab === 'audit' ? 'bg-[#C5A46D] text-[#101C2C]' : 'bg-slate-800 text-slate-300'}`}
          >
            Audit Log ({audit.length})
          </button>
          <button
            onClick={() => setTab('trash')}
            className={`px-3 py-1.5 rounded-lg text-xs font-semibold cursor-pointer ${tab === 'trash' ? 'bg-[#C5A46D] text-[#101C2C]' : 'bg-slate-800 text-slate-300'}`}
          >
            Recycle Bin ({totalTrashed})
          </button>
        </div>
      </div>

      {tab === 'audit' && (
        <div className="bg-[#101C2C] rounded-2xl border border-slate-800 shadow-xl overflow-hidden">
          <div className="md:hidden divide-y divide-slate-800">
            {audit.length === 0 && (
              <p className="py-6 text-center text-slate-500 text-xs">No audit entries yet.</p>
            )}
            {audit.map((e: AuditEntry) => (
              <article key={e.id} className="p-4 space-y-1">
                <div className="flex items-center justify-between gap-2">
                  <span className="font-mono text-[10px] text-[#C5A46D]">{e.action}</span>
                  <span className="font-mono text-[10px] text-slate-500">{e.at.replace('T', ' ').slice(0, 19)}</span>
                </div>
                <p className="text-xs text-slate-200">{e.actor} <span className="text-slate-500">({e.role})</span></p>
                <p className="text-[11px] text-slate-400">{e.detail || e.target || '—'}</p>
              </article>
            ))}
          </div>
          <div className="hidden md:block overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className="bg-[#0B1220] text-slate-400 uppercase tracking-wider text-[10px] border-b border-slate-800">
                <tr>
                  <th className="py-3 px-4">When</th>
                  <th className="py-3 px-4">Actor</th>
                  <th className="py-3 px-4">Action</th>
                  <th className="py-3 px-4">Target</th>
                  <th className="py-3 px-4">Detail</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/80">
                {audit.length === 0 && (
                  <tr>
                    <td colSpan={5} className="py-6 text-center text-slate-500 text-xs">No audit entries yet.</td>
                  </tr>
                )}
                {audit.map((e: AuditEntry) => (
                  <tr key={e.id} className="hover:bg-slate-800/40">
                    <td className="py-2.5 px-4 font-mono text-[10px] text-slate-400">{e.at.replace('T', ' ').slice(0, 19)}</td>
                    <td className="py-2.5 px-4 text-slate-200">{e.actor} <span className="text-slate-500">({e.role})</span></td>
                    <td className="py-2.5 px-4 font-mono text-[10px] text-[#C5A46D]">{e.action}</td>
                    <td className="py-2.5 px-4 font-mono text-[10px] text-slate-400">{e.target || '—'}</td>
                    <td className="py-2.5 px-4 text-slate-300">{e.detail || '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {tab === 'trash' && (
        <div className="space-y-4">
          {[
            { label: 'Properties', icon: Home, items: trashedProperties.map((p) => ({ id: p.id, name: p.title, deletedAt: p.deletedAt, deletedBy: p.deletedBy, restore: () => restoreProperty(p.id) })) },
            { label: 'Sellers', icon: Users, items: trashedSellers.map((s) => ({ id: s.id, name: s.fullName, deletedAt: s.deletedAt, deletedBy: s.deletedBy, restore: () => restoreSeller(s.id) })) },
            { label: 'Buyers', icon: UserCheck, items: trashedBuyers.map((b) => ({ id: b.id, name: b.fullName, deletedAt: b.deletedAt, deletedBy: b.deletedBy, restore: () => restoreBuyer(b.id) })) },
            { label: 'Dealers', icon: Briefcase, items: trashedDealers.map((d) => ({ id: d.id, name: d.fullName, deletedAt: d.deletedAt, deletedBy: d.deletedBy, restore: () => restoreDealer(d.id) })) },
            { label: 'Projects', icon: Building2, items: trashedProjects.map((p) => ({ id: p.id, name: p.title, deletedAt: p.deletedAt, deletedBy: p.deletedBy, restore: () => restoreProject(p.id) })) },
            { label: 'Media', icon: Image, items: trashedMedia.map((m) => ({ id: m.id, name: m.title, deletedAt: m.deletedAt, deletedBy: m.deletedBy, restore: () => restoreMediaItem(m.id) })) },
          ].map((group) => (
            <div key={group.label} className="bg-[#101C2C] rounded-2xl border border-slate-800 shadow-xl p-5">
              <div className="flex items-center gap-2 mb-3">
                <group.icon className="w-4 h-4 text-[#C5A46D]" />
                <h3 className="font-semibold text-white text-sm">{group.label} ({group.items.length})</h3>
              </div>
              {group.items.length === 0 ? (
                <p className="text-xs text-slate-500 italic">Nothing in the bin.</p>
              ) : (
                <div className="space-y-2">
                  {group.items.map((it) => (
                    <div key={it.id} className="flex items-center justify-between p-2.5 rounded-lg bg-[#0B1220] border border-slate-800 text-xs">
                      <div>
                        <div className="font-semibold text-slate-200">{it.name}</div>
                        <div className="text-[10px] text-slate-500">
                          Deleted {it.deletedAt} by {it.deletedBy || 'unknown'}
                        </div>
                      </div>
                      <button
                        onClick={it.restore}
                        className="px-2.5 py-1.5 rounded-lg bg-[#C5A46D]/20 hover:bg-[#C5A46D] text-[#C5A46D] hover:text-[#101C2C] text-xs font-semibold transition flex items-center gap-1 cursor-pointer"
                      >
                        <RotateCcw className="w-3.5 h-3.5" /> Restore
                      </button>
                    </div>
                  ))}
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
};



