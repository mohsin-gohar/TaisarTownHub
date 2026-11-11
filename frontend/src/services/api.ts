/**
 * TaisarTownHub Backend API Service
 * Connects the React luxury frontend to the Laravel + MySQL API backend.
 */

export const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://127.0.0.1:8080/api';

const TOKEN_KEY = 'tth_token';

/** Currently acting agency (resolved server-side, sent back as X-Agency-Id). */
let currentAgencyId: string | null = null;

export function setApiAgencyId(id: string | null): void {
  currentAgencyId = id;
}

export function getApiAgencyId(): string | null {
  return currentAgencyId;
}

export function getAuthToken(): string | null {
  try {
    return sessionStorage.getItem(TOKEN_KEY);
  } catch {
    return null;
  }
}

export function setAuthToken(token: string | null): void {
  if (token) sessionStorage.setItem(TOKEN_KEY, token);
  else sessionStorage.removeItem(TOKEN_KEY);
}

async function apiFetch(path: string, options: RequestInit = {}): Promise<Response> {
  const headers = new Headers(options.headers);
  // FormData bodies must let the browser set the multipart boundary itself.
  const isFormData = typeof FormData !== 'undefined' && options.body instanceof FormData;
  if (!isFormData && !headers.has('Content-Type') && options.body) {
    headers.set('Content-Type', 'application/json');
  }
  const token = getAuthToken();
  if (token) headers.set('Authorization', `Bearer ${token}`);
  if (currentAgencyId) headers.set('X-Agency-Id', currentAgencyId);
  return fetch(`${API_BASE_URL}${path}`, { ...options, headers });
}

export interface ApiHealthResponse {
  status: string;
  application: string;
  database: string;
  timestamp: string;
}

export const api = {
  async getHealth(): Promise<ApiHealthResponse | null> {
    try {
      const res = await fetch(`${API_BASE_URL}/health`, { method: 'GET' });
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async getDealers(): Promise<any[] | null> {
    try {
      const res = await fetch(`${API_BASE_URL}/dealers`);
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async getProperties(params?: { phase?: string; type?: string; dealerId?: string }): Promise<any[] | null> {
    try {
      const query = new URLSearchParams(params as any).toString();
      const res = await fetch(`${API_BASE_URL}/properties?${query}`);
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async getVideos(phase?: string): Promise<any[] | null> {
    try {
      const url = phase && phase !== 'All' ? `${API_BASE_URL}/videos?phase=${encodeURIComponent(phase)}` : `${API_BASE_URL}/videos`;
      const res = await fetch(url);
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async createAgencyVideo(video: Record<string, unknown>): Promise<any | null> {
    try {
      const res = await apiFetch('/videos', {
        method: 'POST',
        body: JSON.stringify(video),
      });
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async likeVideo(videoId: string): Promise<{ ok: boolean; likes?: number; liked?: boolean }> {
    try {
      const res = await apiFetch(`/videos/${videoId}/like`, { method: 'POST' });
      const body = await res.json().catch(() => null);
      if (!res.ok) return { ok: false };
      return { ok: true, likes: body?.likes, liked: body?.liked };
    } catch {
      return { ok: false };
    }
  },

  async postVideoComment(videoId: string, userName: string, comment: string): Promise<any | null> {
    try {
      const res = await apiFetch(`/videos/${videoId}/comments`, {
        method: 'POST',
        body: JSON.stringify({ comment }),
      });
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async getCatalog(): Promise<Record<string, unknown> | null> {
    try {
      const res = await apiFetch('/catalog');
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async getWorkspace(): Promise<Record<string, unknown> | null> {
    try {
      const res = await apiFetch('/workspace');
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async saveWorkspace(collections: Record<string, unknown>): Promise<boolean> {
    try {
      const res = await apiFetch('/workspace', {
        method: 'PUT',
        body: JSON.stringify({ collections }),
      });
      return res.ok;
    } catch {
      return false;
    }
  },

  async login(email: string, password: string): Promise<{ success: boolean; token?: string; user?: Record<string, unknown>; error?: string }> {
    try {
      const res = await apiFetch('/auth/login', {
        method: 'POST',
        body: JSON.stringify({ email, password }),
      });
      const body = await res.json().catch(() => ({}));
      if (!res.ok) {
        const message = body?.errors?.email?.[0] || body?.message || 'Invalid email or password.';
        return { success: false, error: message };
      }
      return { success: true, token: body.token, user: body.user };
    } catch {
      return { success: false, error: 'Cannot reach the MySQL API. Start the Laravel server on port 8080.' };
    }
  },

  async register(payload: Record<string, unknown>): Promise<{ success: boolean; token?: string; user?: Record<string, unknown>; error?: string }> {
    try {
      const res = await apiFetch('/auth/register', {
        method: 'POST',
        body: JSON.stringify(payload),
      });
      const body = await res.json().catch(() => ({}));
      if (!res.ok) {
        const first = body?.errors ? Object.values(body.errors).flat()[0] : null;
        return { success: false, error: String(first || body?.message || 'Registration failed.') };
      }
      return { success: true, token: body.token, user: body.user };
    } catch {
      return { success: false, error: 'Cannot reach the MySQL API. Start the Laravel server on port 8080.' };
    }
  },

  async logout(): Promise<void> {
    try {
      await apiFetch('/auth/logout', { method: 'POST' });
    } catch {
      /* the local token is cleared either way */
    }
  },

  async subscribe(email: string): Promise<boolean> {
    try {
      const res = await apiFetch('/subscribers', {
        method: 'POST',
        body: JSON.stringify({ email }),
      });
      return res.ok;
    } catch {
      return false;
    }
  },

  async submitInquiry(inquiryData: {
    fullName: string;
    email?: string;
    phone: string;
    subject: string;
    message: string;
    preferredPropertyOrProject?: string;
    budgetRange?: string;
    propertyId?: string;
  }): Promise<any | null> {
    try {
      const res = await apiFetch('/inquiries', {
        method: 'POST',
        body: JSON.stringify(inquiryData),
      });
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  // ------------------------------------------------------------------
  // Agency profile / staff / roles
  // ------------------------------------------------------------------

  async getAgencyProfile(agencyId?: string): Promise<any | null> {
    try {
      const res = await apiFetch(agencyId ? `/agency/profile?agency_id=${encodeURIComponent(agencyId)}` : '/agency/profile');
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async updateAgencyProfile(agencyId: string, data: Record<string, unknown>): Promise<any | null> {
    try {
      const res = await apiFetch(`/agency/profile?agency_id=${encodeURIComponent(agencyId)}`, {
        method: 'PUT',
        body: JSON.stringify(data),
      });
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async getAgencyMembers(agencyId?: string): Promise<any | null> {
    try {
      const res = await apiFetch(agencyId ? `/agency/members?agency_id=${encodeURIComponent(agencyId)}` : '/agency/members');
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async inviteAgencyMember(data: Record<string, unknown>): Promise<{ ok: boolean; status: number; body: any }> {
    try {
      const res = await apiFetch('/agency/members/invite', { method: 'POST', body: JSON.stringify(data) });
      const body = await res.json().catch(() => ({}));
      return { ok: res.ok, status: res.status, body };
    } catch {
      return { ok: false, status: 0, body: { message: 'Cannot reach the API.' } };
    }
  },

  async updateAgencyMember(memberId: string, data: Record<string, unknown>): Promise<any | null> {
    try {
      const res = await apiFetch(`/agency/members/${memberId}`, { method: 'PUT', body: JSON.stringify(data) });
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async removeAgencyMember(memberId: string): Promise<boolean> {
    try {
      const res = await apiFetch(`/agency/members/${memberId}`, { method: 'DELETE' });
      return res.ok;
    } catch {
      return false;
    }
  },

  async getAgencyInvitations(): Promise<any | null> {
    try {
      const res = await apiFetch('/agency/invitations');
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async revokeAgencyInvitation(invitationId: string): Promise<boolean> {
    try {
      const res = await apiFetch(`/agency/invitations/${invitationId}`, { method: 'DELETE' });
      return res.ok;
    } catch {
      return false;
    }
  },

  async getAgencyRoles(agencyId?: string): Promise<any | null> {
    try {
      const res = await apiFetch(agencyId ? `/agency/roles?agency_id=${encodeURIComponent(agencyId)}` : '/agency/roles');
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async createAgencyRole(data: Record<string, unknown>): Promise<{ ok: boolean; status: number; body: any }> {
    try {
      const res = await apiFetch('/agency/roles', { method: 'POST', body: JSON.stringify(data) });
      const body = await res.json().catch(() => ({}));
      return { ok: res.ok, status: res.status, body };
    } catch {
      return { ok: false, status: 0, body: { message: 'Cannot reach the API.' } };
    }
  },

  async updateAgencyRole(roleId: string, data: Record<string, unknown>): Promise<any | null> {
    try {
      const res = await apiFetch(`/agency/roles/${roleId}`, { method: 'PUT', body: JSON.stringify(data) });
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async deleteAgencyRole(roleId: string): Promise<boolean> {
    try {
      const res = await apiFetch(`/agency/roles/${roleId}`, { method: 'DELETE' });
      return res.ok;
    } catch {
      return false;
    }
  },

  // ------------------------------------------------------------------
  // Property lifecycle (agency staff + moderation)
  // ------------------------------------------------------------------

  async getModerationQueue(): Promise<any | null> {
    try {
      const res = await apiFetch('/moderation/queue');
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async submitProperty(data: Record<string, unknown>): Promise<any | null> {
    try {
      const res = await apiFetch('/properties', { method: 'POST', body: JSON.stringify(data) });
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async approveProperty(id: string): Promise<any | null> {
    try {
      const res = await apiFetch(`/properties/${id}/approve`, { method: 'POST' });
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async rejectProperty(id: string, reason?: string): Promise<any | null> {
    try {
      const res = await apiFetch(`/properties/${id}/reject`, {
        method: 'POST',
        body: JSON.stringify({ reason }),
      });
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async featureProperty(id: string): Promise<any | null> {
    try {
      const res = await apiFetch(`/properties/${id}/feature`, { method: 'POST' });
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  // ------------------------------------------------------------------
  // CRM leads
  // ------------------------------------------------------------------

  async getAgencyLeads(agencyId?: string): Promise<any | null> {
    try {
      const res = await apiFetch(agencyId ? `/agency/leads?agency_id=${encodeURIComponent(agencyId)}` : '/agency/leads');
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async exportAgencyLeads(): Promise<string | null> {
    try {
      const res = await apiFetch('/agency/leads/export');
      if (!res.ok) return null;
      return await res.text();
    } catch {
      return null;
    }
  },

  async createAgencyLead(data: Record<string, unknown>): Promise<any | null> {
    try {
      const res = await apiFetch('/agency/leads', { method: 'POST', body: JSON.stringify(data) });
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async updateAgencyLead(id: string, data: Record<string, unknown>): Promise<any | null> {
    try {
      const res = await apiFetch(`/agency/leads/${id}`, { method: 'PUT', body: JSON.stringify(data) });
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async assignAgencyLead(id: string, agentId: string): Promise<boolean> {
    try {
      const res = await apiFetch(`/agency/leads/${id}/assign`, {
        method: 'POST',
        body: JSON.stringify({ agent_id: agentId }),
      });
      return res.ok;
    } catch {
      return false;
    }
  },

  async deleteAgencyLead(id: string): Promise<boolean> {
    try {
      const res = await apiFetch(`/agency/leads/${id}`, { method: 'DELETE' });
      return res.ok;
    } catch {
      return false;
    }
  },

  // ------------------------------------------------------------------
  // Finance (deals, payments, schedules, commissions)
  // ------------------------------------------------------------------

  async getAgencyTransactions(agencyId?: string): Promise<any[] | null> {
    try {
      const res = await apiFetch(agencyId ? `/agency/transactions?agency_id=${encodeURIComponent(agencyId)}` : '/agency/transactions');
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async getAgencyCommissions(agencyId?: string): Promise<any[] | null> {
    try {
      const res = await apiFetch(agencyId ? `/agency/commissions?agency_id=${encodeURIComponent(agencyId)}` : '/agency/commissions');
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async getFinanceSummary(agencyId?: string): Promise<any | null> {
    try {
      const res = await apiFetch(agencyId ? `/agency/transactions/${agencyId}/summary` : '/agency/transactions/summary');
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  // ------------------------------------------------------------------
  // Admin: dashboard, agencies, users, roles, audit, settings, plans
  // ------------------------------------------------------------------

  async getAdminDashboard(): Promise<any | null> {
    try {
      const res = await apiFetch('/admin/dashboard');
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async getAdminAgencies(): Promise<any | null> {
    try {
      const res = await apiFetch('/admin/agencies');
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async verifyAdminAgency(id: string): Promise<boolean> {
    try {
      const res = await apiFetch(`/admin/agencies/${id}/verify`, { method: 'PUT' });
      return res.ok;
    } catch {
      return false;
    }
  },

  async setAdminAgencyStatus(id: string, status: string): Promise<boolean> {
    try {
      const res = await apiFetch(`/admin/agencies/${id}/status`, { method: 'PUT', body: JSON.stringify({ status }) });
      return res.ok;
    } catch {
      return false;
    }
  },

  async assignAdminAgencySubscription(id: string, data: Record<string, unknown>): Promise<boolean> {
    try {
      const res = await apiFetch(`/admin/agencies/${id}/subscription`, { method: 'PUT', body: JSON.stringify(data) });
      return res.ok;
    } catch {
      return false;
    }
  },

  async getAdminPlans(): Promise<any[] | null> {
    try {
      const res = await apiFetch('/admin/plans');
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async createAdminPlan(data: Record<string, unknown>): Promise<any | null> {
    try {
      const res = await apiFetch('/admin/plans', { method: 'POST', body: JSON.stringify(data) });
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async updateAdminPlan(id: string, data: Record<string, unknown>): Promise<any | null> {
    try {
      const res = await apiFetch(`/admin/plans/${id}`, { method: 'PUT', body: JSON.stringify(data) });
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async deleteAdminPlan(id: string): Promise<boolean> {
    try {
      const res = await apiFetch(`/admin/plans/${id}`, { method: 'DELETE' });
      return res.ok;
    } catch {
      return false;
    }
  },

  async getAdminUsers(): Promise<any[] | null> {
    try {
      const res = await apiFetch('/admin/users');
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async createAdminUser(data: Record<string, unknown>): Promise<any | null> {
    try {
      const res = await apiFetch('/admin/users', { method: 'POST', body: JSON.stringify(data) });
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async updateAdminUser(id: string, data: Record<string, unknown>): Promise<any | null> {
    try {
      const res = await apiFetch(`/admin/users/${id}`, { method: 'PUT', body: JSON.stringify(data) });
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async unlockAdminUser(id: string): Promise<boolean> {
    try {
      const res = await apiFetch(`/admin/users/${id}/unlock`, { method: 'POST' });
      return res.ok;
    } catch {
      return false;
    }
  },

  async revokeAdminTokens(userId: string): Promise<boolean> {
    try {
      const res = await apiFetch(`/admin/users/${userId}/revoke-tokens`, { method: 'POST' });
      return res.ok;
    } catch {
      return false;
    }
  },

  async getAdminRoles(): Promise<any[] | null> {
    try {
      const res = await apiFetch('/admin/roles');
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async getAdminAuditLogs(): Promise<any[] | null> {
    try {
      const res = await apiFetch('/admin/audit-logs');
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async getAdminSettings(): Promise<any | null> {
    try {
      const res = await apiFetch('/admin/settings');
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async updateAdminSetting(key: string, value: unknown): Promise<boolean> {
    try {
      const res = await apiFetch('/admin/settings', {
        method: 'PUT',
        body: JSON.stringify({ key, value }),
      });
      return res.ok;
    } catch {
      return false;
    }
  },

  // ------------------------------------------------------------------
  // Subscriptions & plans
  // ------------------------------------------------------------------

  async getPlans(): Promise<any | null> {
    try {
      const res = await apiFetch('/plans');
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async getAgencySubscription(agencyId?: string): Promise<any | null> {
    try {
      const res = await apiFetch(agencyId ? `/agency/subscription?agency_id=${encodeURIComponent(agencyId)}` : '/agency/subscription');
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  // ------------------------------------------------------------------
  // Notifications & favorites & inquiries & locations & misc
  // ------------------------------------------------------------------

  async getNotifications(): Promise<any | null> {
    try {
      const res = await apiFetch('/notifications');
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async getUnreadNotificationCount(): Promise<number> {
    try {
      const res = await apiFetch('/notifications/unread-count');
      if (!res.ok) return 0;
      const body = await res.json().catch(() => ({}));
      // Backend contract uses `unread_count` (the legacy `count` key was never sent).
      return Number(body?.unread_count ?? 0);
    } catch {
      return 0;
    }
  },

  async markNotificationRead(id: string): Promise<boolean> {
    try {
      const res = await apiFetch(`/notifications/${id}/read`, { method: 'POST' });
      return res.ok;
    } catch {
      return false;
    }
  },

  async markAllNotificationsRead(): Promise<boolean> {
    try {
      const res = await apiFetch('/notifications/read-all', { method: 'POST' });
      return res.ok;
    } catch {
      return false;
    }
  },

  async getFavorites(): Promise<any[] | null> {
    try {
      const res = await apiFetch('/favorites');
      if (!res.ok) return null;
      const body = await res.json().catch(() => ({}));
      const items = Array.isArray(body?.favorites) ? body.favorites : Array.isArray(body) ? body : [];
      return items;
    } catch {
      return null;
    }
  },

  async toggleFavorite(propertyId: string): Promise<{ ok: boolean; favorited?: boolean }> {
    try {
      const res = await apiFetch(`/favorites/${propertyId}`, { method: 'POST' });
      const body = await res.json().catch(() => ({}));
      if (!res.ok) return { ok: false };
      return { ok: true, favorited: Boolean(body?.favorited) };
    } catch {
      return { ok: false };
    }
  },

  async removeFavorite(propertyId: string): Promise<boolean> {
    try {
      const res = await apiFetch(`/favorites/${propertyId}`, { method: 'DELETE' });
      return res.ok;
    } catch {
      return false;
    }
  },

  async getInquiries(status?: string): Promise<any[] | null> {
    try {
      const query = status && status !== 'All' ? `?status=${encodeURIComponent(status)}` : '';
      const res = await apiFetch(`/inquiries${query}`);
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async updateInquiryStatus(id: string, status: string): Promise<any | null> {
    try {
      const res = await apiFetch(`/inquiries/${id}/status`, {
        method: 'PATCH',
        body: JSON.stringify({ status }),
      });
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async getLocations(): Promise<any | null> {
    try {
      const res = await apiFetch('/locations');
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async getProjects(): Promise<any[] | null> {
    try {
      const res = await apiFetch('/projects');
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async getDealerFiles(): Promise<any[] | null> {
    try {
      const res = await apiFetch('/dealer-files');
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async getSalesReport(dealerId?: string): Promise<any | null> {
    try {
      const url = dealerId ? `/dealer-files/${dealerId}/sales-report` : '/sales-report';
      const res = await apiFetch(url);
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  // ------------------------------------------------------------------
  // Media library (agency)
  // ------------------------------------------------------------------

  async getMediaItems(): Promise<any | null> {
    try {
      const res = await apiFetch('/agency/media');
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async createMediaItem(data: Record<string, unknown>): Promise<any | null> {
    try {
      const res = await apiFetch('/agency/media', { method: 'POST', body: JSON.stringify(data) });
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async updateMediaItem(id: string, data: Record<string, unknown>): Promise<any | null> {
    try {
      const res = await apiFetch(`/agency/media/${id}`, { method: 'PUT', body: JSON.stringify(data) });
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async deleteMediaItem(id: string): Promise<boolean> {
    try {
      const res = await apiFetch(`/agency/media/${id}`, { method: 'DELETE' });
      return res.ok;
    } catch {
      return false;
    }
  },

  async sellMediaItem(id: string, data: Record<string, unknown>): Promise<any | null> {
    try {
      const res = await apiFetch(`/agency/media/${id}/sell`, { method: 'POST', body: JSON.stringify(data) });
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async getMediaFile(id: string): Promise<any | null> {
    try {
      const res = await apiFetch(`/agency/media/${id}/file`);
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  // ------------------------------------------------------------------
  // Enterprise UI support (Phase 7): identity, private vault, media
  // uploads, location structures. All file transfers use multipart and
  // authenticated blob reads — private bytes are never public URLs.
  // ------------------------------------------------------------------

  async getMe(): Promise<any | null> {
    try {
      const res = await apiFetch('/auth/me');
      if (!res.ok) return null;
      const body = await res.json().catch(() => ({}));
      return body?.user ?? body;
    } catch {
      return null;
    }
  },

  async getAgencyDocuments(params: Record<string, string | number | undefined> = {}): Promise<any | null> {
    try {
      const query = Object.entries(params)
        .filter(([, value]) => value !== undefined && value !== '')
        .map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(String(value))}`)
        .join('&');
      const res = await apiFetch(`/agency/documents${query ? `?${query}` : ''}`);
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async getDocumentFolders(): Promise<any[] | null> {
    try {
      const res = await apiFetch('/agency/document-folders');
      if (!res.ok) return null;
      const body = await res.json().catch(() => ({}));
      return Array.isArray(body?.folders) ? body.folders : null;
    } catch {
      return null;
    }
  },

  async createDocumentFolder(data: Record<string, unknown>): Promise<{ ok: boolean; status: number; body: any }> {
    try {
      const res = await apiFetch('/agency/document-folders', { method: 'POST', body: JSON.stringify(data) });
      const body = await res.json().catch(() => ({}));
      return { ok: res.ok, status: res.status, body };
    } catch {
      return { ok: false, status: 0, body: { message: 'Cannot reach the API.' } };
    }
  },

  async deleteDocumentFolder(id: string): Promise<boolean> {
    try {
      const res = await apiFetch(`/agency/document-folders/${id}`, { method: 'DELETE' });
      return res.ok;
    } catch {
      return false;
    }
  },

  async uploadAgencyDocument(formData: FormData): Promise<{ ok: boolean; status: number; body: any }> {
    try {
      const res = await apiFetch('/agency/documents', { method: 'POST', body: formData });
      const body = await res.json().catch(() => ({}));
      return { ok: res.ok, status: res.status, body };
    } catch {
      return { ok: false, status: 0, body: { message: 'Cannot reach the API.' } };
    }
  },

  async deleteAgencyDocument(id: string): Promise<boolean> {
    try {
      const res = await apiFetch(`/agency/documents/${id}`, { method: 'DELETE' });
      return res.ok;
    } catch {
      return false;
    }
  },

  /** Authenticated blob read; returns the server-provided filename when present. */
  async downloadAgencyDocument(id: string): Promise<{ blob: Blob; filename: string } | null> {
    return this.readAgencyBlob(`/agency/documents/${id}/download`);
  },

  async previewAgencyDocument(id: string): Promise<Blob | null> {
    const result = await this.readAgencyBlob(`/agency/documents/${id}/preview`);
    return result?.blob ?? null;
  },

  async readAgencyBlob(path: string): Promise<{ blob: Blob; filename: string } | null> {
    try {
      const res = await apiFetch(path);
      if (!res.ok) return null;
      const blob = await res.blob();
      const disposition = res.headers.get('content-disposition') || '';
      const match = /filename="?([^";]+)"?/i.exec(disposition);
      return { blob, filename: match?.[1] || 'document' };
    } catch {
      return null;
    }
  },

  async uploadAgencyMedia(formData: FormData): Promise<{ ok: boolean; status: number; body: any }> {
    try {
      const res = await apiFetch('/agency/media', { method: 'POST', body: formData });
      const body = await res.json().catch(() => ({}));
      return { ok: res.ok, status: res.status, body };
    } catch {
      return { ok: false, status: 0, body: { message: 'Cannot reach the API.' } };
    }
  },

  async fetchAgencyMediaBlob(id: string): Promise<Blob | null> {
    const result = await this.readAgencyBlob(`/agency/media/${id}/file`);
    return result?.blob ?? null;
  },

  async createLocationSector(data: Record<string, unknown>): Promise<{ ok: boolean; status: number; body: any }> {
    try {
      const res = await apiFetch('/locations/sectors', { method: 'POST', body: JSON.stringify(data) });
      const body = await res.json().catch(() => ({}));
      return { ok: res.ok, status: res.status, body };
    } catch {
      return { ok: false, status: 0, body: { message: 'Cannot reach the API.' } };
    }
  },

  async createLocationBlock(data: Record<string, unknown>): Promise<{ ok: boolean; status: number; body: any }> {
    try {
      const res = await apiFetch('/locations/blocks', { method: 'POST', body: JSON.stringify(data) });
      const body = await res.json().catch(() => ({}));
      return { ok: res.ok, status: res.status, body };
    } catch {
      return { ok: false, status: 0, body: { message: 'Cannot reach the API.' } };
    }
  },

  async createLocationCategory(data: Record<string, unknown>): Promise<{ ok: boolean; status: number; body: any }> {
    try {
      const res = await apiFetch('/locations/categories', { method: 'POST', body: JSON.stringify(data) });
      const body = await res.json().catch(() => ({}));
      return { ok: res.ok, status: res.status, body };
    } catch {
      return { ok: false, status: 0, body: { message: 'Cannot reach the API.' } };
    }
  }
};
