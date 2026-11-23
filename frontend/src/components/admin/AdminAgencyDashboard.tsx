import React, { useCallback, useEffect, useState } from 'react';
import {
  BadgeCheck,
  Building2,
  FileText,
  FolderLock,
  Inbox,
  MessageCircle,
  RefreshCw,
  Users,
} from 'lucide-react';
import { api } from '../../services/api';
import { normalizeWhatsAppNumber } from '../../utils/whatsapp';
import {
  EnterpriseBadge,
  EnterpriseButton,
  EnterpriseCard,
  EnterpriseEmpty,
  EnterpriseNote,
  EnterprisePage,
  EnterpriseSpinner,
  EnterpriseStat,
  formatDate,
  formatPKR,
  titleCase,
} from './AdminEnterpriseUi';

interface AgencyDashboardProps {
  onNavigate: (tab: string) => void;
}

const UsageBar: React.FC<{ label: string; used: number; limit: number | null }> = ({ label, used, limit }) => {
  const unlimited = limit === null || limit <= 0;
  const pct = unlimited ? 0 : Math.min(100, Math.round((used / limit) * 100));

  return (
    <div className="space-y-1.5">
      <div className="flex justify-between text-[11px] text-slate-300">
        <span>{label}</span>
        <span className="font-semibold text-slate-400">
          {used} / {unlimited ? '∞' : limit}
        </span>
      </div>
      <div className="w-full h-2 rounded-full bg-slate-800 overflow-hidden">
        <div
          className={`h-full ${pct >= 90 ? 'bg-rose-500' : pct >= 70 ? 'bg-amber-400' : 'bg-[#E7C878]'}`}
          style={{ width: `${unlimited ? 12 : Math.max(pct, 2)}%` }}
        />
      </div>
    </div>
  );
};

export const AdminAgencyDashboard: React.FC<AgencyDashboardProps> = ({ onNavigate }) => {
  const [agency, setAgency] = useState<any | null>(null);
  const [subscription, setSubscription] = useState<any | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [contact, setContact] = useState({ phone: '', whatsapp: '' });
  const [savingContact, setSavingContact] = useState(false);
  const [contactMessage, setContactMessage] = useState('');

  useEffect(() => {
    if (agency) {
      setContact({
        phone: agency.phone || '',
        whatsapp: agency.whatsapp || '',
      });
    }
  }, [agency?.id]);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    const [profileBody, subscriptionBody] = await Promise.all([
      api.getAgencyProfile(),
      api.getAgencySubscription(),
    ]);

    const profile = profileBody?.agency ?? profileBody;
    if (!profile || profile?.success === false) {
      setError('No agency context is available for this account. Sign in with an agency membership.');
      setAgency(null);
    } else {
      setAgency(profile);
    }
    setSubscription(subscriptionBody && subscriptionBody.success !== false ? subscriptionBody : null);
    setLoading(false);
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const savePublicContact = async () => {
    if (!agency || savingContact) return;
    const normalized = normalizeWhatsAppNumber(contact.whatsapp);
    if (contact.whatsapp.trim() && !normalized) {
      setContactMessage('Enter a valid WhatsApp number (local 03xx or international format).');
      return;
    }
    setSavingContact(true);
    setContactMessage('');
    const result = await api.updateAgencyProfile(String(agency.id), {
      phone: contact.phone || null,
      whatsapp: normalized || null,
    });
    setSavingContact(false);
    if (!result || result.success === false) {
      setContactMessage(result?.message || 'Could not save public contact details.');
      return;
    }
    setContactMessage('Public contact details saved.');
    await load();
  };

  const stats = agency?.stats ?? {};
  const limits = subscription?.limits ?? agency?.limits ?? {};
  const usage = subscription?.usage ?? {};
  const plan = subscription?.subscription?.plan ?? agency?.subscription?.plan ?? null;
  const status = subscription?.subscription?.status ?? agency?.subscription?.status ?? 'none';

  return (
    <EnterprisePage
      title="Agency Dashboard"
      subtitle="Your agency's live footprint across listings, staff, documents and leads."
      actions={
        <EnterpriseButton onClick={() => void load()} variant="secondary">
          <RefreshCw className={`w-3.5 h-3.5 ${loading ? 'animate-spin' : ''}`} /> Refresh
        </EnterpriseButton>
      }
    >
      {error && <EnterpriseNote tone="error">{error}</EnterpriseNote>}
      {loading && !agency ? (
        <EnterpriseSpinner label="Loading agency profile…" />
      ) : agency ? (
        <>
          <EnterpriseCard>
            <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
              <div className="flex items-start gap-4 min-w-0">
                <div className="w-14 h-14 rounded-2xl bg-[#E7C878]/15 border border-[#E7C878]/30 flex items-center justify-center shrink-0">
                  <Building2 className="w-7 h-7 text-[#E7C878]" />
                </div>
                <div className="min-w-0 space-y-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <h3 className="font-serif-luxury text-xl font-bold text-white truncate">{agency.name}</h3>
                    <EnterpriseBadge tone={agency.verification_status === 'verified' ? 'green' : 'gold'}>
                      <BadgeCheck className="w-3 h-3 mr-1" />
                      {titleCase(agency.verification_status || 'pending')}
                    </EnterpriseBadge>
                    <EnterpriseBadge tone={agency.status === 'active' ? 'green' : 'red'}>
                      {titleCase(agency.status || 'unknown')}
                    </EnterpriseBadge>
                  </div>
                  <p className="text-xs text-slate-400">
                    {[agency.city, agency.office_address].filter(Boolean).join(' · ') || 'No office address on file'}
                  </p>
                  <p className="text-[11px] text-slate-500">
                    {agency.email || 'No email'} · {agency.phone || 'No phone'}
                    {agency.registration_number ? ` · Reg #${agency.registration_number}` : ''}
                  </p>
                </div>
              </div>
              <div className="flex flex-wrap gap-2">
                <EnterpriseButton onClick={() => onNavigate('staff')} variant="secondary">
                  <Users className="w-3.5 h-3.5" /> Staff
                </EnterpriseButton>
                <EnterpriseButton onClick={() => onNavigate('documents')} variant="secondary">
                  <FolderLock className="w-3.5 h-3.5" /> Vault
                </EnterpriseButton>
                <EnterpriseButton onClick={() => onNavigate('subscription')} variant="secondary">
                  <FileText className="w-3.5 h-3.5" /> Subscription
                </EnterpriseButton>
              </div>
            </div>
          </EnterpriseCard>

          <EnterpriseCard
            title="Public contact & WhatsApp"
            subtitle="Shown to buyers on your public agency profile and sale listings"
          >
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <label className="space-y-1.5">
                <span className="text-xs font-semibold text-slate-300">Public phone</span>
                <input
                  value={contact.phone}
                  onChange={(e) => setContact({ ...contact, phone: e.target.value })}
                  placeholder="+92 300 1234567"
                  className="w-full rounded-lg border border-slate-700 bg-[#07141E] px-3 py-2 text-sm text-white placeholder:text-slate-600 focus:border-[#E7C878] focus:outline-none"
                />
              </label>
              <label className="space-y-1.5">
                <span className="text-xs font-semibold text-slate-300">Public WhatsApp number</span>
                <input
                  value={contact.whatsapp}
                  onChange={(e) => setContact({ ...contact, whatsapp: e.target.value })}
                  placeholder="0300 1234567 or +92 300 1234567"
                  className="w-full rounded-lg border border-slate-700 bg-[#07141E] px-3 py-2 text-sm text-white placeholder:text-slate-600 focus:border-[#E7C878] focus:outline-none"
                />
                <span className="text-[11px] text-slate-500">Saved in international format. Leave empty to hide the WhatsApp button.</span>
              </label>
            </div>
            <div className="mt-4 flex flex-wrap items-center gap-3">
              <EnterpriseButton onClick={() => void savePublicContact()} disabled={savingContact} variant="secondary">
                <MessageCircle className="w-3.5 h-3.5" /> {savingContact ? 'Saving…' : 'Save public contact'}
              </EnterpriseButton>
              {contactMessage && (
                <span className={`text-xs ${contactMessage.startsWith('Public contact details saved') ? 'text-emerald-400' : 'text-rose-400'}`}>
                  {contactMessage}
                </span>
              )}
            </div>
          </EnterpriseCard>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <EnterpriseStat label="Active Members" value={stats.members ?? 0} hint={`Plan allowance: ${limits.staff ?? '∞'}`} tone="gold" />
            <EnterpriseStat label="Live Listings" value={stats.listings_public ?? 0} hint={`${stats.listings_total ?? 0} total · ${stats.listings_pending ?? 0} pending`} />
            <EnterpriseStat label="Vault Documents" value={stats.documents ?? 0} hint="Private, permission-checked storage" />
            <EnterpriseStat label="CRM Leads" value={stats.leads ?? 0} hint="All-time captured inquiries" tone="green" />
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <EnterpriseCard
              title="Subscription & Usage"
              subtitle="Server-enforced plan limits for this agency"
              className="lg:col-span-7"
            >
              <div className="flex flex-wrap items-center gap-2 mb-4">
                <EnterpriseBadge tone={status === 'active' ? 'green' : status === 'trialing' ? 'blue' : 'red'}>
                  {titleCase(status)}
                </EnterpriseBadge>
                {plan ? (
                  <span className="text-sm font-bold text-white">
                    {plan.name}
                    <span className="text-[11px] font-normal text-slate-400 ml-2">
                      {plan.price !== undefined ? `${formatPKR(plan.price)} / ${plan.interval || 'month'}` : ''}
                    </span>
                  </span>
                ) : (
                  <span className="text-xs text-slate-400">No plan assigned — default limits apply.</span>
                )}
              </div>

              <div className="grid sm:grid-cols-3 gap-4">
                <UsageBar label="Staff seats" used={Number(usage.members ?? stats.members ?? 0)} limit={limits.staff ?? null} />
                <UsageBar label="Listings" used={Number(usage.listings ?? stats.listings_total ?? 0)} limit={limits.listings ?? null} />
                <UsageBar label="Leads" used={Number(usage.leads ?? stats.leads ?? 0)} limit={limits.leads ?? null} />
              </div>

              <div className="mt-5 flex flex-wrap items-center justify-between gap-3 text-[11px] text-slate-400">
                <span>
                  Storage used: <strong className="text-slate-200">{Number(usage.storage_bytes ?? 0).toLocaleString()} bytes</strong>
                </span>
                {subscription?.subscription?.ends_at && (
                  <span>Renews/ends: <strong className="text-slate-200">{formatDate(subscription.subscription.ends_at)}</strong></span>
                )}
              </div>
            </EnterpriseCard>

            <EnterpriseCard title="Quick Actions" subtitle="Move straight to the operational desks" className="lg:col-span-5">
              <div className="space-y-2">
                {[
                  { tab: 'moderation', label: 'Moderation Desk', hint: 'Review pending listings', icon: BadgeCheck },
                  { tab: 'crm', label: 'CRM Pipeline', hint: 'Work assigned leads', icon: Inbox },
                  { tab: 'staff', label: 'Staff & Roles', hint: 'Invite and manage your team', icon: Users },
                  { tab: 'documents', label: 'Document Vault', hint: 'Upload and retrieve private files', icon: FolderLock },
                ].map((action) => {
                  const Icon = action.icon;
                  return (
                    <button
                      key={action.tab}
                      onClick={() => onNavigate(action.tab)}
                      className="w-full flex items-center justify-between gap-3 px-3 py-2.5 rounded-xl bg-[#07141E] border border-slate-800 hover:border-[#E7C878]/40 text-left cursor-pointer transition-colors"
                    >
                      <span className="flex items-center gap-2.5 min-w-0">
                        <Icon className="w-4 h-4 text-[#E7C878] shrink-0" />
                        <span className="min-w-0">
                          <span className="block text-xs font-semibold text-white">{action.label}</span>
                          <span className="block text-[10px] text-slate-500">{action.hint}</span>
                        </span>
                      </span>
                      <span className="text-[#E7C878] text-xs">→</span>
                    </button>
                  );
                })}
              </div>
            </EnterpriseCard>
          </div>
        </>
      ) : (
        <EnterpriseEmpty title="Agency context unavailable" hint="This screen is available to users with an active agency membership." />
      )}
    </EnterprisePage>
  );
};
