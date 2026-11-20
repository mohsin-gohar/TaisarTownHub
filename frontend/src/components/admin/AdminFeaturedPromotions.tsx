import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ArrowDown,
  ArrowUp,
  Award,
  CalendarClock,
  CreditCard,
  Plus,
  RefreshCw,
  Settings2,
  ShieldCheck,
  Sparkles,
  XCircle,
} from 'lucide-react';
import { api } from '../../services/api';
import { useApp } from '../../context/AppContext';
import {
  EnterpriseBadge,
  EnterpriseButton,
  EnterpriseCard,
  EnterpriseEmpty,
  EnterpriseNote,
  EnterprisePage,
  EnterpriseSpinner,
  EnterpriseStat,
  enterpriseInputClass,
  formatDate,
  formatPKR,
  titleCase,
} from './AdminEnterpriseUi';

interface Placement {
  id: number;
  agency_id: string;
  status: string;
  position: number;
  starts_at?: string | null;
  ends_at?: string | null;
  price?: string | number;
  payment_status: string;
  payment_method?: string | null;
  notes?: string | null;
  agency?: { id: string; name: string; city?: string | null } | null;
  package?: { id: number; name: string } | null;
}

interface PromotionPackage {
  id: number;
  key: string;
  name: string;
  price: string | number;
  duration_days: number;
  is_active: boolean;
}

interface Settings {
  max_slots: number;
  default_duration_days: number;
}

interface EligibleAgency {
  id: string;
  name: string;
  city?: string | null;
}

const toLocalInput = (value?: string | null): string => {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  const local = new Date(date.getTime() - date.getTimezoneOffset() * 60_000);
  return local.toISOString().slice(0, 16);
};

const paymentTone = (status: string): 'green' | 'gold' | 'red' | 'slate' => {
  if (status === 'paid') return 'green';
  if (status === 'pending') return 'gold';
  if (status === 'refunded') return 'red';
  return 'slate';
};

const statusTone = (status: string): 'green' | 'gold' | 'red' | 'slate' | 'blue' => {
  if (status === 'active') return 'green';
  if (status === 'pending') return 'gold';
  if (status === 'paused') return 'blue';
  if (status === 'expired' || status === 'cancelled') return 'red';
  return 'slate';
};

export const AdminFeaturedPromotions: React.FC = () => {
  const { confirmAction } = useApp();
  const [placements, setPlacements] = useState<Placement[]>([]);
  const [packages, setPackages] = useState<PromotionPackage[]>([]);
  const [settings, setSettings] = useState<Settings>({ max_slots: 10, default_duration_days: 30 });
  const [stats, setStats] = useState<any>({});
  const [eligible, setEligible] = useState<EligibleAgency[]>([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState('');
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const [form, setForm] = useState({
    agency_id: '',
    package_id: '',
    duration_days: '',
    position: '',
    starts_at: '',
    price: '',
    payment_status: 'waived',
    notes: '',
  });

  const [settingsDraft, setSettingsDraft] = useState<Settings>({ max_slots: 10, default_duration_days: 30 });
  const [packageForm, setPackageForm] = useState({
    key: '', name: '', duration_days: '30', price: '0', description: '',
  });
  const [packageEdit, setPackageEdit] = useState<Record<string, string>>({});

  const [editing, setEditing] = useState<Placement | null>(null);
  const [editDraft, setEditDraft] = useState<Record<string, string>>({});

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    const body = await api.getAdminFeaturedAgencies();
    if (!body || body.success === false) {
      setError(body?.message || 'Could not load featured placements. Super-admin access is required.');
      setPlacements([]);
    } else {
      setPlacements(Array.isArray(body.placements) ? body.placements : []);
      setPackages(Array.isArray(body.packages) ? body.packages : []);
      setEligible(Array.isArray(body.eligible_agencies) ? body.eligible_agencies : []);
      setStats(body.stats ?? {});
      const nextSettings = {
        max_slots: Number(body.settings?.max_slots ?? 10),
        default_duration_days: Number(body.settings?.default_duration_days ?? 30),
      };
      setSettings(nextSettings);
      setSettingsDraft(nextSettings);
    }
    setLoading(false);
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const livePlacements = useMemo(
    () => placements
      .filter((placement) => placement.status === 'active')
      .sort((a, b) => Number(a.position) - Number(b.position) || Number(a.id) - Number(b.id)),
    [placements],
  );

  const handleCreate = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!form.agency_id) {
      setError('Choose an eligible agency first.');
      return;
    }
    setBusy('create');
    setError('');
    setNotice('');
    const price = Number(form.price || 0);
    const result = await api.createAdminFeaturedAgency({
      agency_id: form.agency_id,
      package_id: form.package_id ? Number(form.package_id) : undefined,
      duration_days: form.duration_days ? Number(form.duration_days) : undefined,
      position: form.position ? Number(form.position) : undefined,
      starts_at: form.starts_at || undefined,
      price,
      payment_status: form.payment_status,
      notes: form.notes || undefined,
      status: 'active',
    });
    setBusy('');
    if (!result.ok) {
      setError(result.body?.message || 'The featured placement could not be created.');
      return;
    }
    setNotice(`${result.body?.placement?.agency?.name || 'Agency'} is now on the featured roster.`);
    setForm({
      agency_id: '', package_id: '', duration_days: '', position: '',
      starts_at: '', price: '', payment_status: 'waived', notes: '',
    });
    await load();
  };

  const handlePackagePick = (value: string) => {
    const selected = packages.find((pkg) => String(pkg.id) === value);
    setForm((previous) => ({
      ...previous,
      package_id: value,
      duration_days: selected ? String(selected.duration_days) : previous.duration_days,
      price: selected ? String(selected.price) : previous.price,
      payment_status: selected && Number(selected.price) > 0 ? 'pending' : 'waived',
    }));
  };

  const quickUpdate = async (placement: Placement, data: Record<string, unknown>, message: string) => {
    setBusy(`placement-${placement.id}`);
    setError('');
    setNotice('');
    const result = await api.updateAdminFeaturedAgency(placement.id, data);
    setBusy('');
    if (!result.ok) {
      setError(result.body?.message || 'The placement could not be updated.');
      return;
    }
    setNotice(message);
    await load();
  };

  const handleCancel = async (placement: Placement) => {
    const confirmed = await confirmAction(
      `Cancel the featured placement for "${placement.agency?.name || 'this agency'}"? Its listings and files stay untouched; only the homepage advertisement ends.`,
      { title: 'Cancel featured placement', confirmLabel: 'Cancel placement' },
    );
    if (!confirmed) return;
    setBusy(`placement-${placement.id}`);
    setError('');
    setNotice('');
    const result = await api.cancelAdminFeaturedAgency(placement.id, 'Cancelled by platform administrator');
    setBusy('');
    if (!result.ok) {
      setError(result.body?.message || 'The placement could not be cancelled.');
      return;
    }
    setNotice('Placement cancelled. Agency data was not modified.');
    await load();
  };

  const move = async (index: number, direction: -1 | 1) => {
    const target = index + direction;
    if (target < 0 || target >= livePlacements.length) return;
    const reordered = [...livePlacements];
    const [moved] = reordered.splice(index, 1);
    reordered.splice(target, 0, moved);
    setBusy('reorder');
    setError('');
    setNotice('');
    const result = await api.reorderAdminFeaturedAgencies(reordered.map((placement) => placement.id));
    setBusy('');
    if (!result.ok) {
      setError(result.body?.message || 'The featured order could not be saved.');
      return;
    }
    setNotice('Homepage featured order updated.');
    await load();
  };

  const openEditor = (placement: Placement) => {
    setEditing(placement);
    setEditDraft({
      status: placement.status,
      position: String(placement.position),
      starts_at: toLocalInput(placement.starts_at),
      ends_at: toLocalInput(placement.ends_at),
      price: String(placement.price ?? 0),
      payment_status: placement.payment_status,
      payment_method: placement.payment_method || '',
      notes: placement.notes || '',
    });
  };

  const saveEditor = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!editing) return;
    setBusy(`placement-${editing.id}`);
    setError('');
    setNotice('');
    const result = await api.updateAdminFeaturedAgency(editing.id, {
      status: editDraft.status,
      position: editDraft.position ? Number(editDraft.position) : undefined,
      starts_at: editDraft.starts_at || null,
      ends_at: editDraft.ends_at || null,
      price: Number(editDraft.price || 0),
      payment_status: editDraft.payment_status,
      payment_method: editDraft.payment_method || undefined,
      notes: editDraft.notes || undefined,
    });
    setBusy('');
    if (!result.ok) {
      setError(result.body?.message || 'The placement could not be saved.');
      return;
    }
    setNotice('Placement updated.');
    setEditing(null);
    await load();
  };

  const saveSettings = async (event: React.FormEvent) => {
    event.preventDefault();
    setBusy('settings');
    setError('');
    setNotice('');
    const result = await api.updateAdminPromotionSettings({
      max_slots: settingsDraft.max_slots,
      default_duration_days: settingsDraft.default_duration_days,
    });
    setBusy('');
    if (!result.ok) {
      setError(result.body?.message || 'Featured settings could not be saved.');
      return;
    }
    setNotice('Featured-agency settings saved.');
    await load();
  };

  const createPackage = async (event: React.FormEvent) => {
    event.preventDefault();
    setBusy('package-create');
    setError('');
    setNotice('');
    const result = await api.createAdminPromotionPackage({
      key: packageForm.key,
      name: packageForm.name,
      description: packageForm.description || undefined,
      duration_days: Number(packageForm.duration_days || 30),
      price: Number(packageForm.price || 0),
      is_active: true,
    });
    setBusy('');
    if (!result.ok) {
      setError(result.body?.message || 'Package could not be created.');
      return;
    }
    setNotice('Promotion package created.');
    setPackageForm({ key: '', name: '', duration_days: '30', price: '0', description: '' });
    await load();
  };

  const savePackage = async (pkg: PromotionPackage, data: Record<string, unknown>) => {
    setBusy(`package-${pkg.id}`);
    setError('');
    setNotice('');
    const result = await api.updateAdminPromotionPackage(pkg.id, data);
    setBusy('');
    if (!result.ok) {
      setError(result.body?.message || 'Package could not be updated.');
      return;
    }
    setNotice(`Package "${pkg.name}" updated.`);
    setPackageEdit({});
    await load();
  };

  if (loading && placements.length === 0) {
    return <EnterpriseSpinner label="Loading featured-agency control room…" />;
  }

  return (
    <EnterprisePage
      title="Featured Agencies"
      subtitle="Paid or complimentary homepage placements. Only approved, verified and active agencies are eligible."
      actions={
        <EnterpriseButton variant="secondary" onClick={() => void load()}>
          <RefreshCw className={`w-3.5 h-3.5 ${loading ? 'animate-spin' : ''}`} /> Refresh
        </EnterpriseButton>
      }
    >
      {error && <EnterpriseNote tone="error">{error}</EnterpriseNote>}
      {notice && <EnterpriseNote tone="success">{notice}</EnterpriseNote>}

      <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
        <EnterpriseStat label="Live now" value={String(stats.live ?? 0)} hint={`${stats.available_slots ?? 0} slot(s) free`} />
        <EnterpriseStat label="Max slots" value={String(stats.max_slots ?? settings.max_slots)} hint="Configurable below" />
        <EnterpriseStat label="Scheduled" value={String(stats.scheduled ?? 0)} hint="Future start date" />
        <EnterpriseStat label="Expiring (7d)" value={String(stats.expiring_7d ?? 0)} hint="Renewal follow-up" />
        <EnterpriseStat label="Pending payment" value={String(stats.pending_payments ?? 0)} hint="Super-admin reconciliation" />
        <EnterpriseStat label="Recorded revenue" value={formatPKR(stats.revenue_pkr ?? 0)} hint="Paid placements only" />
      </div>

      <div className="grid grid-cols-1 xl:grid-cols-12 gap-6">
        <div className="xl:col-span-8 space-y-6">
          <EnterpriseCard
            title="Homepage roster"
            subtitle={`${livePlacements.length} live placement(s) · drag-free ordered priority (top slot first)`}
          >
            {placements.length === 0 ? (
              <EnterpriseEmpty title="No placements yet" hint="Choose an eligible agency on the right to create the first homepage placement." />
            ) : (
              <div className="space-y-2">
                {placements.map((placement) => {
                  const isLive = placement.status === 'active';
                  const liveIndex = livePlacements.findIndex((row) => row.id === placement.id);
                  return (
                    <div key={placement.id} className="rounded-xl border border-slate-800 bg-[#07141E] p-3">
                      <div className="flex flex-wrap items-start justify-between gap-2">
                        <div className="min-w-0">
                          <div className="flex items-center gap-2 flex-wrap">
                            <span className="text-[10px] font-bold text-[#E7C878]">#{placement.position}</span>
                            <span className="text-sm font-bold text-white truncate">{placement.agency?.name || placement.agency_id}</span>
                            <EnterpriseBadge tone={statusTone(placement.status)}>{titleCase(placement.status)}</EnterpriseBadge>
                            <EnterpriseBadge tone={paymentTone(placement.payment_status)}>{titleCase(placement.payment_status)}</EnterpriseBadge>
                            {placement.package && <EnterpriseBadge tone="slate">{placement.package.name}</EnterpriseBadge>}
                          </div>
                          <div className="mt-1 text-[10px] text-slate-500 flex flex-wrap gap-x-3 gap-y-1">
                            <span>Starts {placement.starts_at ? formatDate(placement.starts_at) : 'immediately'}</span>
                            <span>Ends {placement.ends_at ? formatDate(placement.ends_at) : 'no end date'}</span>
                            <span>{formatPKR(placement.price ?? 0)}</span>
                          </div>
                          {placement.notes && <div className="mt-1 text-[10px] text-slate-500 line-clamp-2">{placement.notes}</div>}
                        </div>
                        <div className="flex items-center gap-1.5 flex-wrap">
                          {isLive && (
                            <>
                              <EnterpriseButton
                                variant="secondary"
                                title="Move up"
                                disabled={busy === 'reorder' || liveIndex <= 0}
                                onClick={() => void move(liveIndex, -1)}
                              >
                                <ArrowUp className="w-3.5 h-3.5" />
                              </EnterpriseButton>
                              <EnterpriseButton
                                variant="secondary"
                                title="Move down"
                                disabled={busy === 'reorder' || liveIndex === livePlacements.length - 1}
                                onClick={() => void move(liveIndex, 1)}
                              >
                                <ArrowDown className="w-3.5 h-3.5" />
                              </EnterpriseButton>
                              <EnterpriseButton
                                variant="secondary"
                                title="Temporarily pause"
                                disabled={busy === `placement-${placement.id}`}
                                onClick={() => void quickUpdate(placement, { status: 'paused' }, 'Placement paused.')}
                              >
                                Pause
                              </EnterpriseButton>
                            </>
                          )}
                          {placement.status === 'paused' && (
                            <EnterpriseButton
                              variant="secondary"
                              disabled={busy === `placement-${placement.id}`}
                              onClick={() => void quickUpdate(placement, { status: 'active' }, 'Placement resumed.')}
                            >
                              Resume
                            </EnterpriseButton>
                          )}
                          {placement.payment_status !== 'paid' && (
                            <EnterpriseButton
                              variant="secondary"
                              title="Record a manual payment"
                              disabled={busy === `placement-${placement.id}`}
                              onClick={() => void quickUpdate(placement, {
                                payment_status: 'paid',
                                payment_method: 'manual',
                              }, 'Payment recorded as paid (manual).')}
                            >
                              <CreditCard className="w-3.5 h-3.5" /> Mark paid
                            </EnterpriseButton>
                          )}
                          <EnterpriseButton variant="secondary" onClick={() => openEditor(placement)}>
                            Edit
                          </EnterpriseButton>
                          {!['cancelled', 'expired'].includes(placement.status) && (
                            <EnterpriseButton
                              variant="danger"
                              disabled={busy === `placement-${placement.id}`}
                              onClick={() => void handleCancel(placement)}
                              title="Cancel placement"
                            >
                              <XCircle className="w-3.5 h-3.5" />
                            </EnterpriseButton>
                          )}
                        </div>
                      </div>
                    </div>
                  );
                })}
              </div>
            )}
          </EnterpriseCard>

          {editing && (
            <EnterpriseCard title={`Edit placement · ${editing.agency?.name || editing.agency_id}`} subtitle="Status, dates, position and payment state">
              <form onSubmit={saveEditor} className="grid sm:grid-cols-2 gap-3">
                <label className="text-[10px] text-slate-400">
                  Status
                  <select
                    className={`${enterpriseInputClass} mt-1`}
                    value={editDraft.status}
                    onChange={(e) => setEditDraft((d) => ({ ...d, status: e.target.value }))}
                  >
                    {['active', 'pending', 'paused', 'expired', 'cancelled'].map((value) => (
                      <option key={value} value={value}>{titleCase(value)}</option>
                    ))}
                  </select>
                </label>
                <label className="text-[10px] text-slate-400">
                  Position
                  <input
                    type="number"
                    min={1}
                    className={`${enterpriseInputClass} mt-1`}
                    value={editDraft.position}
                    onChange={(e) => setEditDraft((d) => ({ ...d, position: e.target.value }))}
                  />
                </label>
                <label className="text-[10px] text-slate-400">
                  Starts at
                  <input
                    type="datetime-local"
                    className={`${enterpriseInputClass} mt-1`}
                    value={editDraft.starts_at}
                    onChange={(e) => setEditDraft((d) => ({ ...d, starts_at: e.target.value }))}
                  />
                </label>
                <label className="text-[10px] text-slate-400">
                  Ends at
                  <input
                    type="datetime-local"
                    className={`${enterpriseInputClass} mt-1`}
                    value={editDraft.ends_at}
                    onChange={(e) => setEditDraft((d) => ({ ...d, ends_at: e.target.value }))}
                  />
                </label>
                <label className="text-[10px] text-slate-400">
                  Price (PKR)
                  <input
                    type="number"
                    min={0}
                    className={`${enterpriseInputClass} mt-1`}
                    value={editDraft.price}
                    onChange={(e) => setEditDraft((d) => ({ ...d, price: e.target.value }))}
                  />
                </label>
                <label className="text-[10px] text-slate-400">
                  Payment status
                  <select
                    className={`${enterpriseInputClass} mt-1`}
                    value={editDraft.payment_status}
                    onChange={(e) => setEditDraft((d) => ({ ...d, payment_status: e.target.value }))}
                  >
                    {['waived', 'pending', 'paid', 'refunded'].map((value) => (
                      <option key={value} value={value}>{titleCase(value)}</option>
                    ))}
                  </select>
                </label>
                <label className="text-[10px] text-slate-400 sm:col-span-2">
                  Notes
                  <textarea
                    className={`${enterpriseInputClass} mt-1 min-h-[64px]`}
                    value={editDraft.notes}
                    onChange={(e) => setEditDraft((d) => ({ ...d, notes: e.target.value }))}
                  />
                </label>
                <div className="sm:col-span-2 flex justify-end gap-2">
                  <EnterpriseButton type="button" variant="secondary" onClick={() => setEditing(null)}>Close</EnterpriseButton>
                  <EnterpriseButton type="submit" disabled={busy === `placement-${editing.id}`}>
                    Save placement
                  </EnterpriseButton>
                </div>
              </form>
            </EnterpriseCard>
          )}

          <EnterpriseCard title="Promotion packages" subtitle="Durations and prices are database-driven; free launch packages are allowed">
            <div className="space-y-2 mb-4">
              {packages.map((pkg) => (
                <div key={pkg.id} className="rounded-lg border border-slate-800 bg-[#07141E] p-2.5">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex items-center gap-2 flex-wrap">
                      <span className="text-xs font-bold text-white">{pkg.name}</span>
                      <EnterpriseBadge tone={pkg.is_active ? 'green' : 'slate'}>{pkg.is_active ? 'Active' : 'Hidden'}</EnterpriseBadge>
                      <span className="text-[10px] text-slate-500">{pkg.duration_days} days · {formatPKR(pkg.price)}</span>
                    </div>
                    <div className="flex items-center gap-2">
                      {packageEdit[String(pkg.id)] === 'open' ? (
                        <>
                          <input
                            type="number"
                            min={0}
                            className={`${enterpriseInputClass} !w-24`}
                            value={packageEdit[`price-${pkg.id}`] ?? String(pkg.price)}
                            onChange={(e) => setPackageEdit((d) => ({ ...d, [`price-${pkg.id}`]: e.target.value }))}
                          />
                          <input
                            type="number"
                            min={1}
                            className={`${enterpriseInputClass} !w-20`}
                            value={packageEdit[`days-${pkg.id}`] ?? String(pkg.duration_days)}
                            onChange={(e) => setPackageEdit((d) => ({ ...d, [`days-${pkg.id}`]: e.target.value }))}
                          />
                          <EnterpriseButton
                            variant="secondary"
                            disabled={busy === `package-${pkg.id}`}
                            onClick={() => void savePackage(pkg, {
                              price: Number(packageEdit[`price-${pkg.id}`] ?? pkg.price),
                              duration_days: Number(packageEdit[`days-${pkg.id}`] ?? pkg.duration_days),
                            })}
                          >
                            Save
                          </EnterpriseButton>
                        </>
                      ) : (
                        <EnterpriseButton variant="secondary" onClick={() => setPackageEdit((d) => ({ ...d, [String(pkg.id)]: 'open' }))}>
                          Edit price
                        </EnterpriseButton>
                      )}
                      <EnterpriseButton
                        variant="secondary"
                        disabled={busy === `package-${pkg.id}`}
                        onClick={() => void savePackage(pkg, { is_active: !pkg.is_active })}
                      >
                        {pkg.is_active ? 'Hide' : 'Activate'}
                      </EnterpriseButton>
                    </div>
                  </div>
                </div>
              ))}
            </div>
            <form onSubmit={createPackage} className="grid sm:grid-cols-5 gap-2">
              <input
                className={enterpriseInputClass}
                placeholder="key"
                value={packageForm.key}
                onChange={(e) => setPackageForm((f) => ({ ...f, key: e.target.value }))}
                required
              />
              <input
                className={enterpriseInputClass}
                placeholder="Name"
                value={packageForm.name}
                onChange={(e) => setPackageForm((f) => ({ ...f, name: e.target.value }))}
                required
              />
              <input
                type="number"
                min={1}
                className={enterpriseInputClass}
                placeholder="Days"
                value={packageForm.duration_days}
                onChange={(e) => setPackageForm((f) => ({ ...f, duration_days: e.target.value }))}
                required
              />
              <input
                type="number"
                min={0}
                className={enterpriseInputClass}
                placeholder="Price PKR"
                value={packageForm.price}
                onChange={(e) => setPackageForm((f) => ({ ...f, price: e.target.value }))}
              />
              <EnterpriseButton type="submit" disabled={busy === 'package-create'}>
                <Plus className="w-3.5 h-3.5" /> Add package
              </EnterpriseButton>
            </form>
          </EnterpriseCard>
        </div>

        <div className="xl:col-span-4 space-y-6">
          <EnterpriseCard title="Add featured placement" subtitle="Eligibility is re-checked server-side">
            {eligible.length === 0 ? (
              <EnterpriseNote tone="info">
                No eligible agency is currently free for a new placement. Agencies must be approved, verified, active and without another current placement.
              </EnterpriseNote>
            ) : (
              <form onSubmit={handleCreate} className="space-y-2.5">
                <label className="block text-[10px] text-slate-400">
                  Agency
                  <select
                    className={`${enterpriseInputClass} mt-1`}
                    value={form.agency_id}
                    onChange={(e) => setForm((f) => ({ ...f, agency_id: e.target.value }))}
                    required
                  >
                    <option value="">Select an eligible agency…</option>
                    {eligible.map((agency) => (
                      <option key={agency.id} value={agency.id}>{agency.name}{agency.city ? ` · ${agency.city}` : ''}</option>
                    ))}
                  </select>
                </label>
                <label className="block text-[10px] text-slate-400">
                  Package
                  <select
                    className={`${enterpriseInputClass} mt-1`}
                    value={form.package_id}
                    onChange={(e) => handlePackagePick(e.target.value)}
                  >
                    <option value="">Custom duration</option>
                    {packages.filter((pkg) => pkg.is_active).map((pkg) => (
                      <option key={pkg.id} value={pkg.id}>{pkg.name} · {pkg.duration_days}d · {formatPKR(pkg.price)}</option>
                    ))}
                  </select>
                </label>
                <div className="grid grid-cols-2 gap-2">
                  <label className="block text-[10px] text-slate-400">
                    Duration (days)
                    <input
                      type="number"
                      min={1}
                      max={3650}
                      className={`${enterpriseInputClass} mt-1`}
                      value={form.duration_days}
                      onChange={(e) => setForm((f) => ({ ...f, duration_days: e.target.value }))}
                      placeholder={String(settings.default_duration_days)}
                    />
                  </label>
                  <label className="block text-[10px] text-slate-400">
                    Position (optional)
                    <input
                      type="number"
                      min={1}
                      max={settings.max_slots}
                      className={`${enterpriseInputClass} mt-1`}
                      value={form.position}
                      onChange={(e) => setForm((f) => ({ ...f, position: e.target.value }))}
                      placeholder="Auto"
                    />
                  </label>
                </div>
                <label className="block text-[10px] text-slate-400">
                  Starts at (optional)
                  <input
                    type="datetime-local"
                    className={`${enterpriseInputClass} mt-1`}
                    value={form.starts_at}
                    onChange={(e) => setForm((f) => ({ ...f, starts_at: e.target.value }))}
                  />
                </label>
                <div className="grid grid-cols-2 gap-2">
                  <label className="block text-[10px] text-slate-400">
                    Price (PKR)
                    <input
                      type="number"
                      min={0}
                      className={`${enterpriseInputClass} mt-1`}
                      value={form.price}
                      onChange={(e) => setForm((f) => ({ ...f, price: e.target.value }))}
                      placeholder="0 = complimentary"
                    />
                  </label>
                  <label className="block text-[10px] text-slate-400">
                    Payment
                    <select
                      className={`${enterpriseInputClass} mt-1`}
                      value={form.payment_status}
                      onChange={(e) => setForm((f) => ({ ...f, payment_status: e.target.value }))}
                    >
                      {['waived', 'pending', 'paid'].map((value) => (
                        <option key={value} value={value}>{titleCase(value)}</option>
                      ))}
                    </select>
                  </label>
                </div>
                <label className="block text-[10px] text-slate-400">
                  Notes
                  <textarea
                    className={`${enterpriseInputClass} mt-1 min-h-[64px]`}
                    value={form.notes}
                    onChange={(e) => setForm((f) => ({ ...f, notes: e.target.value }))}
                    placeholder="Payment reference, promotion rationale, approval notes…"
                  />
                </label>
                <EnterpriseButton type="submit" disabled={busy === 'create'} className="w-full">
                  <Sparkles className="w-3.5 h-3.5" /> Create placement
                </EnterpriseButton>
                <p className="text-[10px] text-slate-500">
                  The homepage labels a placement “Sponsored” only when a real payment is recorded;
                  complimentary placements show “Featured”.
                </p>
              </form>
            )}
          </EnterpriseCard>

          <EnterpriseCard title="Featured settings" subtitle="Slot cap applies to live homepage placements">
            <form onSubmit={saveSettings} className="space-y-2.5">
              <label className="block text-[10px] text-slate-400">
                Maximum live slots
                <input
                  type="number"
                  min={1}
                  max={100}
                  className={`${enterpriseInputClass} mt-1`}
                  value={settingsDraft.max_slots}
                  onChange={(e) => setSettingsDraft((d) => ({ ...d, max_slots: Number(e.target.value) }))}
                />
              </label>
              <div className="flex gap-1.5">
                {[10, 20, 30].map((preset) => (
                  <EnterpriseButton
                    key={preset}
                    type="button"
                    variant="secondary"
                    onClick={() => setSettingsDraft((d) => ({ ...d, max_slots: preset }))}
                  >
                    {preset}
                  </EnterpriseButton>
                ))}
              </div>
              <label className="block text-[10px] text-slate-400">
                Default duration (days)
                <input
                  type="number"
                  min={1}
                  max={3650}
                  className={`${enterpriseInputClass} mt-1`}
                  value={settingsDraft.default_duration_days}
                  onChange={(e) => setSettingsDraft((d) => ({ ...d, default_duration_days: Number(e.target.value) }))}
                />
              </label>
              <EnterpriseButton type="submit" disabled={busy === 'settings'} className="w-full">
                <Settings2 className="w-3.5 h-3.5" /> Save settings
              </EnterpriseButton>
            </form>
            <div className="mt-4 space-y-2 text-[10px] text-slate-500">
              <p className="flex items-start gap-2"><ShieldCheck className="w-3.5 h-3.5 text-[#E7C878] shrink-0" /> Only approved + verified + active agencies can be placed.</p>
              <p className="flex items-start gap-2"><CalendarClock className="w-3.5 h-3.5 text-[#E7C878] shrink-0" /> Expired placements leave the homepage automatically; agency files and listings stay intact.</p>
              <p className="flex items-start gap-2"><Award className="w-3.5 h-3.5 text-[#E7C878] shrink-0" /> Every create, update, cancel and reorder is written to the audit trail.</p>
            </div>
          </EnterpriseCard>
        </div>
      </div>
    </EnterprisePage>
  );
};
