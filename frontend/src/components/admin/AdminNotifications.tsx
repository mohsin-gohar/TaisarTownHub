import React, { useCallback, useEffect, useState } from 'react';
import { Bell, CheckCheck, Inbox, RefreshCw } from 'lucide-react';
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
  formatDate,
  titleCase,
} from './AdminEnterpriseUi';

export const AdminNotifications: React.FC = () => {
  const [notifications, setNotifications] = useState<any[]>([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [pagination, setPagination] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState('');
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [unreadOnly, setUnreadOnly] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    const body = await api.getNotifications();
    if (!body || body.success === false) {
      setError(body?.message || 'Could not load notifications.');
      setNotifications([]);
      setUnreadCount(0);
      setPagination(null);
    } else {
      const rows = Array.isArray(body.notifications) ? body.notifications : [];
      setNotifications(unreadOnly ? rows.filter((n: any) => !n.read_at) : rows);
      setUnreadCount(Number(body.unread_count ?? 0));
      setPagination(body.pagination ?? null);
    }
    setLoading(false);
  }, [unreadOnly]);

  useEffect(() => {
    void load();
  }, [load]);

  const handleMarkRead = async (notification: any) => {
    if (notification.read_at) return;
    setBusy(`read-${notification.id}`);
    setError('');
    const ok = await api.markNotificationRead(String(notification.id));
    setBusy('');
    if (!ok) {
      setError('Notification could not be marked as read.');
      return;
    }
    await load();
  };

  const handleMarkAll = async () => {
    setBusy('all');
    setError('');
    setNotice('');
    const ok = await api.markAllNotificationsRead();
    setBusy('');
    if (!ok) {
      setError('Notifications could not be marked as read.');
      return;
    }
    setNotice('All notifications marked as read.');
    await load();
  };

  const describe = (notification: any) => {
    const data = notification.data && typeof notification.data === 'object' ? notification.data : {};
    return {
      title: data.title || data.subject || titleCase(notification.type?.split('\\').pop() || 'Notification'),
      message: data.message || data.body || data.description || '',
      category: data.category || data.type || 'system',
      actionUrl: data.url || data.action_url || '',
    };
  };

  return (
    <EnterprisePage
      title="Notifications"
      subtitle="Account-scoped alerts for moderation, assignments, invitations and subscription events."
      actions={
        <>
          <EnterpriseButton variant="secondary" onClick={() => void handleMarkAll()} disabled={busy === 'all' || unreadCount === 0}>
            <CheckCheck className="w-3.5 h-3.5" /> Mark all read
          </EnterpriseButton>
          <EnterpriseButton variant="secondary" onClick={() => void load()}>
            <RefreshCw className={`w-3.5 h-3.5 ${loading ? 'animate-spin' : ''}`} /> Refresh
          </EnterpriseButton>
        </>
      }
    >
      {error && <EnterpriseNote tone="error">{error}</EnterpriseNote>}
      {notice && <EnterpriseNote tone="success">{notice}</EnterpriseNote>}

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <EnterpriseStat label="Unread" value={unreadCount} tone={unreadCount > 0 ? 'gold' : 'white'} hint="Alerts awaiting your attention" />
        <EnterpriseStat label="Visible" value={notifications.length} hint="This page of your notification feed" />
        <EnterpriseStat label="Total" value={pagination?.total ?? notifications.length} hint="All notifications on this account" />
      </div>

      <EnterpriseCard
        title="Notification Feed"
        subtitle="Private to your account — never shared across agency members"
        actions={
          <label className="flex items-center gap-2 text-[11px] text-slate-400 cursor-pointer">
            <input type="checkbox" checked={unreadOnly} onChange={(e) => setUnreadOnly(e.target.checked)} />
            Unread only
          </label>
        }
      >
        {loading ? (
          <EnterpriseSpinner label="Loading notifications…" />
        ) : notifications.length === 0 ? (
          <EnterpriseEmpty
            title={unreadOnly ? 'No unread notifications' : 'No notifications yet'}
            hint="Moderation decisions, lead assignments and invitations will appear here."
          />
        ) : (
          <div className="space-y-2">
            {notifications.map((notification) => {
              const info = describe(notification);
              const unread = !notification.read_at;
              return (
                <div
                  key={notification.id}
                  className={`flex items-start gap-3 p-3 rounded-xl border transition-colors ${
                    unread ? 'bg-[#07141E] border-[#E7C878]/30' : 'bg-[#07141E]/60 border-slate-800'
                  }`}
                >
                  <div className={`w-8 h-8 rounded-lg flex items-center justify-center shrink-0 ${
                    unread ? 'bg-[#E7C878]/15 text-[#E7C878]' : 'bg-slate-800 text-slate-500'
                  }`}>
                    {unread ? <Bell className="w-4 h-4" /> : <Inbox className="w-4 h-4" />}
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className={`text-xs ${unread ? 'font-bold text-white' : 'font-semibold text-slate-300'}`}>
                        {info.title}
                      </span>
                      <EnterpriseBadge tone={unread ? 'gold' : 'slate'}>{titleCase(info.category)}</EnterpriseBadge>
                    </div>
                    {info.message && <p className="text-[11px] text-slate-400 mt-0.5">{info.message}</p>}
                    <span className="block text-[10px] text-slate-500 mt-1">{formatDate(notification.created_at)}</span>
                  </div>
                  <div className="shrink-0 flex flex-col items-end gap-1.5">
                    {unread && (
                      <EnterpriseButton
                        variant="secondary"
                        disabled={busy === `read-${notification.id}`}
                        onClick={() => void handleMarkRead(notification)}
                      >
                        Mark read
                      </EnterpriseButton>
                    )}
                    {info.actionUrl && (
                      <a
                        href={info.actionUrl}
                        className="text-[10px] font-semibold text-[#E7C878] hover:underline"
                      >
                        Open
                      </a>
                    )}
                  </div>
                </div>
              );
            })}
          </div>
        )}

        {pagination && pagination.last_page > 1 && (
          <p className="text-[11px] text-slate-500 mt-4">
            Showing {notifications.length} of {pagination.total} notifications · page {pagination.current_page} of {pagination.last_page}
          </p>
        )}
      </EnterpriseCard>
    </EnterprisePage>
  );
};
