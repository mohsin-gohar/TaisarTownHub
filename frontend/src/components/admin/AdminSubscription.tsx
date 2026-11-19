import React, { useCallback, useEffect, useState } from 'react';
import { Check, CreditCard, RefreshCw, Sparkles } from 'lucide-react';
import { api } from '../../services/api';
import {
  EnterpriseBadge,
  EnterpriseButton,
  EnterpriseCard,
  EnterpriseEmpty,
  EnterpriseNote,
  EnterprisePage,
  EnterpriseSpinner,
  EnterpriseStat,
  formatBytes,
  formatDate,
  formatPKR,
  titleCase,
} from './AdminEnterpriseUi';

const LIMIT_KEYS = ['listings', 'staff', 'storage_mb', 'images', 'videos', 'featured_credits', 'listing_days'] as const;

const UsageMeter: React.FC<{ label: string; used: number; limit: number | null; remaining: number | null; suffix?: string }> = ({
  label, used, limit, remaining, suffix = '',
}) => {
  const unlimited = limit === null || limit <= 0;
  const pct = unlimited ? 0 : Math.min(100, Math.round((used / limit) * 100));
  return (
    <div className="space-y-1.5">
      <div className="flex justify-between text-[11px] text-slate-300">
        <span>{label}</span>
        <span className="text-slate-400">
          {used}{suffix} / {unlimited ? '∞' : `${limit}${suffix}`}
        </span>
      </div>
      <div className="w-full h-2 rounded-full bg-slate-800 overflow-hidden">
        <div
          className={`h-full ${pct >= 90 ? 'bg-rose-500' : pct >= 70 ? 'bg-amber-400' : 'bg-[#E7C878]'}`}
          style={{ width: `${unlimited ? 12 : Math.max(pct, 2)}%` }}
        />
      </div>
      <div className="text-[10px] text-slate-500">
        {remaining === null ? 'Unlimited remaining' : `${remaining}${suffix} remaining`}
      </div>
    </div>
  );
};

export const AdminSubscription: React.FC = () => {
  const [data, setData] = useState<any | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    const body = await api.getAgencySubscription();
    if (!body || body.success === false) {
      setError(body?.message || 'Could not load the subscription. An agency membership is required.');
      setData(null);
    } else {
      setData(body);
    }
    setLoading(false);
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const subscription = data?.subscription ?? null;
  const plan = subscription?.plan ?? null;
  const limits = data?.limits ?? {};
  const usage = data?.usage ?? {};
  const remaining = data?.remaining ?? {};
  const plans = Array.isArray(data?.available_plans) ? data.available_plans : [];
  const status = subscription?.status ?? 'none';

  const usedFor = (key: string): number => {
    if (key === 'staff') return Number(usage.members ?? 0);
    if (key === 'storage_mb') return Math.round(Number(usage.storage_bytes ?? 0) / (1024 * 1024));
    return Number(usage[key] ?? 0);
  };

  return (
    <EnterprisePage
      title="Subscription & Usage"
      subtitle="Plan limits are enforced by the API on every upload, invite and listing creation."
      actions={
        <EnterpriseButton variant="secondary" onClick={() => void load()}>
          <RefreshCw className={`w-3.5 h-3.5 ${loading ? 'animate-spin' : ''}`} /> Refresh
        </EnterpriseButton>
      }
    >
      {error && <EnterpriseNote tone="error">{error}</EnterpriseNote>}

      {loading && !data ? (
        <EnterpriseSpinner label="Loading subscription…" />
      ) : data ? (
        <>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <EnterpriseStat
              label="Plan"
              value={plan?.name || 'Default'}
              hint={plan ? `${formatPKR(plan.price)} / ${plan.interval || 'month'}` : 'No paid plan assigned'}
              tone="gold"
            />
            <EnterpriseStat label="Status" value={titleCase(status)} hint={subscription?.ends_at ? `Ends ${formatDate(subscription.ends_at)}` : 'No end date'} />
            <EnterpriseStat label="Staff Used" value={`${usage.members ?? 0} / ${limits.staff ?? '∞'}`} hint="Active agency memberships" />
            <EnterpriseStat label="Storage Used" value={formatBytes(usage.storage_bytes)} hint={`Limit: ${limits.storage_mb ?? '∞'} MB`} />
          </div>

          <div className="grid grid-cols-1 xl:grid-cols-12 gap-6">
            <EnterpriseCard
              title="Plan Consumption"
              subtitle="Usage is computed live from the database — never cached client-side"
              className="xl:col-span-7"
              actions={
                <EnterpriseBadge tone={status === 'active' ? 'green' : status === 'trialing' ? 'blue' : 'red'}>
                  {titleCase(status)}
                </EnterpriseBadge>
              }
            >
              <div className="grid sm:grid-cols-2 gap-5">
                <UsageMeter
                  label="Staff seats"
                  used={usedFor('staff')}
                  limit={limits.staff ?? null}
                  remaining={remaining.staff ?? null}
                />
                <UsageMeter
                  label="Listings"
                  used={usedFor('listings')}
                  limit={limits.listings ?? null}
                  remaining={remaining.listings ?? null}
                />
                <UsageMeter
                  label="CRM leads"
                  used={usedFor('leads')}
                  limit={limits.leads ?? null}
                  remaining={remaining.leads ?? null}
                />
                <UsageMeter
                  label="Storage"
                  used={usedFor('storage_mb')}
                  limit={limits.storage_mb ?? null}
                  remaining={limits.storage_mb != null && usage.storage_bytes != null
                    ? Math.max(0, Number(limits.storage_mb) - usedFor('storage_mb'))
                    : null}
                  suffix=" MB"
                />
              </div>

              <div className="grid sm:grid-cols-2 gap-2 mt-5 pt-4 border-t border-slate-800 text-[11px] text-slate-400">
                {LIMIT_KEYS.filter((key) => key !== 'listings' && key !== 'staff' && key !== 'storage_mb').map((key) => (
                  <div key={key} className="flex justify-between gap-2">
                    <span>{titleCase(key)}</span>
                    <span className="font-semibold text-slate-200">{limits[key] ?? '—'}</span>
                  </div>
                ))}
              </div>

              <div className="mt-4 text-[10px] text-slate-500 space-y-1">
                {subscription?.trial_ends_at && <p>Trial ends: {formatDate(subscription.trial_ends_at)}</p>}
                {subscription?.starts_at && <p>Started: {formatDate(subscription.starts_at)}</p>}
                {!plan && (
                  <p>
                    Default limits apply until the platform team assigns a plan. Contact platform administration —
                    plan changes are audited and cannot be self-served from the agency workspace.
                  </p>
                )}
              </div>
            </EnterpriseCard>

            <EnterpriseCard
              title="Available Plans"
              subtitle="The public catalogue — assignment is performed by platform administration"
              className="xl:col-span-5"
            >
              {plans.length === 0 ? (
                <EnterpriseEmpty title="No plans published" hint="The platform has not activated a plan catalogue yet." />
              ) : (
                <div className="space-y-2.5 max-h-[420px] overflow-y-auto pr-1">
                  {plans.map((candidate: any) => {
                    const current = plan?.key && candidate.key === plan.key;
                    return (
                      <div
                        key={candidate.id ?? candidate.key}
                        className={`p-3 rounded-xl border ${
                          current ? 'border-[#E7C878]/50 bg-[#E7C878]/10' : 'border-slate-800 bg-[#07141E]'
                        }`}
                      >
                        <div className="flex items-center justify-between gap-2">
                          <div className="flex items-center gap-1.5">
                            <Sparkles className="w-3.5 h-3.5 text-[#E7C878]" />
                            <span className="text-xs font-bold text-white">{candidate.name}</span>
                          </div>
                          {current && <EnterpriseBadge tone="gold">Current</EnterpriseBadge>}
                        </div>
                        <div className="text-[11px] text-slate-300 mt-1">
                          {formatPKR(candidate.price)} <span className="text-slate-500">/ {candidate.interval || 'month'}</span>
                        </div>
                        {candidate.description && (
                          <p className="text-[10px] text-slate-500 mt-1">{candidate.description}</p>
                        )}
                        {candidate.limits && (
                          <div className="flex flex-wrap gap-1 mt-2">
                            {Object.entries(candidate.limits).slice(0, 6).map(([key, value]) => (
                              <span key={key} className="px-1.5 py-0.5 rounded bg-slate-800 text-[9px] text-slate-300">
                                {titleCase(key)}: {String(value)}
                              </span>
                            ))}
                          </div>
                        )}
                        {Array.isArray(candidate.features) && candidate.features.length > 0 && (
                          <ul className="mt-2 space-y-0.5">
                            {candidate.features.slice(0, 5).map((feature: string) => (
                              <li key={feature} className="flex items-center gap-1.5 text-[10px] text-slate-400">
                                <Check className="w-3 h-3 text-emerald-400 shrink-0" /> {titleCase(feature)}
                              </li>
                            ))}
                          </ul>
                        )}
                      </div>
                    );
                  })}
                </div>
              )}

              <div className="mt-4 flex items-start gap-2 text-[10px] text-slate-500">
                <CreditCard className="w-4 h-4 text-[#E7C878] shrink-0" />
                <span>
                  No payment details are collected in the app. Subscriptions are assigned by a platform
                  administrator with the `admin.manage_subscriptions` permission.
                </span>
              </div>
            </EnterpriseCard>
          </div>
        </>
      ) : (
        <EnterpriseEmpty title="Subscription unavailable" hint="Sign in with an agency membership to view plan usage." />
      )}
    </EnterprisePage>
  );
};
