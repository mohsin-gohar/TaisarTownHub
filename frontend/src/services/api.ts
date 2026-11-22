/**
 * TaisarTownHub Backend API Service
 * Connects the React luxury frontend to the Laravel + MySQL API backend.
 */
import {
  initialCMSConfig,
  initialMedia,
  initialPlotMarkers,
  initialProjects,
  initialProperties,
  initialTestimonials,
} from '../data/publicSeed';
import { initialAgencyVideos } from '../data/agencyVideosSeed';
import type { Property } from '../types';
import { mapApiProperty } from '../utils/apiProperty';

// Production builds should set VITE_API_URL. If it is missing, fall back to the
// same-origin `/api` reverse-proxy layout (the default in Dockerfile.prod) and
// log the misconfiguration instead of throwing: a module-evaluation throw here
// aborts the whole SPA before React mounts and leaves a blank page.
const configuredApiUrl = import.meta.env.VITE_API_URL;
if (!configuredApiUrl && import.meta.env.PROD) {
  console.error('VITE_API_URL is not set for this production build; using the bundled public catalog.');
}
export const API_BASE_URL = configuredApiUrl || (import.meta.env.PROD ? '/api' : 'http://127.0.0.1:8080/api');

/**
 * Public-safe catalog used by static deployments that have no API origin.
 *
 * Vercel serves the frontend alone. Without a catalogue the marketing pages
 * would mount but show empty agency/property/video grids. Everything here is
 * already shipped in the public bundle (publicSeed/agencyVideosSeed); agency
 * cards are derived from the public video partners so no dealer/ERP records
 * (CNIC, private commissions, inquiries) enter the public chunk.
 */
function buildStaticPublicCatalog(): Record<string, unknown> {
  const agencies = new Map<string, { id: string; name: string; dealer: string; avatar: string }>();
  for (const video of initialAgencyVideos) {
    if (!agencies.has(video.agencyId)) {
      agencies.set(video.agencyId, {
        id: video.agencyId,
        name: video.agencyName,
        dealer: video.dealerName,
        avatar: video.dealerAvatar,
      });
    }
  }

  const dealers = Array.from(agencies.values()).map((agency) => {
    const contact = initialProperties.find((p) => p.contactPerson === agency.dealer);
    const assigned = initialProperties.filter((p) => p.contactPerson === agency.dealer).length;
    return {
      id: agency.id,
      fullName: agency.dealer,
      // Do not emit the ERP-private `cnicMasked` field in the public catalog,
      // even as an empty placeholder — the bundle guard treats it as private.
      phone: contact?.contactPhone || '',
      whatsApp: contact?.contactWhatsApp,
      agencyOrBranch: agency.name,
      agencyAffiliation: agency.name,
      role: 'Verified Property Consultant',
      commissionType: 'Percentage' as const,
      status: 'Active' as const,
      avatar: agency.avatar,
      totalEarnedPKR: 0,
      totalCommissionPaidPKR: 0,
      pendingCommissionPKR: 0,
      assignedPropertiesCount: assigned,
      dealsClosed: 0,
      completedDealsCount: 0,
    };
  });

  return {
    projects: initialProjects,
    properties: initialProperties,
    dealers,
    videos: initialAgencyVideos,
    plots: initialPlotMarkers,
    media: initialMedia,
    testimonials: initialTestimonials,
    cms: initialCMSConfig,
  };
}

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

/**
 * Pending second-factor challenge (Phase 6).
 *
 * The sign-in screen asks the API first; when the account has MFA enabled the
 * API answers with a short-lived challenge instead of a token. The challenge
 * is parked here so the screen can render the code prompt without the shared
 * AppContext having to know about MFA.
 */
let pendingMfaChallenge: { challenge: string; expiresIn: number } | null = null;

export function setPendingMfaChallenge(value: { challenge: string; expiresIn: number } | null): void {
  pendingMfaChallenge = value;
}

export function getPendingMfaChallenge(): { challenge: string; expiresIn: number } | null {
  return pendingMfaChallenge;
}

export function clearPendingMfaChallenge(): void {
  pendingMfaChallenge = null;
}

async function apiFetch(path: string, options: RequestInit = {}): Promise<Response> {
  const headers = new Headers(options.headers);
  headers.set('Accept', 'application/json');
  // FormData bodies must let the browser set the multipart boundary itself.
  const isFormData = typeof FormData !== 'undefined' && options.body instanceof FormData;
  if (!isFormData && !headers.has('Content-Type') && options.body) {
    headers.set('Content-Type', 'application/json');
  }
  const token = getAuthToken();
  if (token) headers.set('Authorization', `Bearer ${token}`);
  if (currentAgencyId) headers.set('X-Agency-Id', currentAgencyId);
  const response = await fetch(`${API_BASE_URL}${path}`, { ...options, headers });
  if (token && token === getAuthToken() && (response.status === 401 || response.status === 403)) {
    const body = await response.clone().json().catch(() => null);
    if (response.status === 401 || body?.workspace_access?.allowed === false) {
      window.dispatchEvent(new CustomEvent('tth:access-denied', {
        detail: { expired: response.status === 401, access: body?.workspace_access },
      }));
    }
  }
  return response;
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

  async getProperties(params?: Record<string, string | number | boolean | undefined>): Promise<any[] | null> {
    try {
      const query = new URLSearchParams();
      if (params) {
        for (const [key, value] of Object.entries(params)) {
          if (value !== undefined && value !== '' && value !== false) query.set(key, String(value));
        }
      }
      const suffix = query.toString() ? `?${query.toString()}` : '';
      // Use apiFetch so authenticated agency contexts (X-Agency-Id, scope=agency) work.
      const res = await apiFetch(`/properties${suffix}`);
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async getPublicProperties(): Promise<Property[] | null> {
    // Static deployments have no API origin; the bundled seed is already
    // sale-only and is the documented offline fallback.
    if (!configuredApiUrl) return initialProperties.map((property) => ({ ...property }));

    try {
      const query = new URLSearchParams({ purpose: 'sale', per_page: '500' });
      const res = await fetch(`${API_BASE_URL}/properties?${query.toString()}`, {
        headers: { Accept: 'application/json' },
      });
      if (!res.ok) return null;

      const body = await res.json();
      const rows: unknown[] = Array.isArray(body)
        ? body
        : Array.isArray(body?.properties)
          ? body.properties
          : [];

      return rows
        .filter((row): row is Record<string, unknown> => Boolean(row) && typeof row === 'object')
        .filter((row) => row.listing_purpose === undefined
          || row.listing_purpose === null
          || row.listing_purpose === 'sale')
        .map(mapApiProperty)
        .filter((property) => property.id !== '');
    } catch {
      return null;
    }
  },

  async getPublicAgencies(params: Record<string, string | number | boolean | undefined> = {}): Promise<any | null> {
    try {
      const query = new URLSearchParams();
      for (const [key, value] of Object.entries(params)) {
        if (value !== undefined && value !== '' && value !== false) query.set(key, String(value));
      }
      const suffix = query.toString() ? `?${query.toString()}` : '';
      const res = await fetch(`${API_BASE_URL}/agencies${suffix}`);
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  /** Homepage featured-agency roster (falls back to verified agencies). */
  async getFeaturedAgencies(params: Record<string, string | number | undefined> = {}): Promise<any | null> {
    try {
      const query = new URLSearchParams();
      for (const [key, value] of Object.entries(params)) {
        if (value !== undefined && value !== '') query.set(key, String(value));
      }
      const suffix = query.toString() ? `?${query.toString()}` : '';
      const res = await fetch(`${API_BASE_URL}/agencies/featured${suffix}`);
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async getPublicAgency(idOrSlug: string, params: Record<string, string | number | undefined> = {}): Promise<any | null> {
    try {
      const query = new URLSearchParams();
      for (const [key, value] of Object.entries(params)) {
        if (value !== undefined && value !== '') query.set(key, String(value));
      }
      const suffix = query.toString() ? `?${query.toString()}` : '';
      const res = await fetch(`${API_BASE_URL}/agencies/${encodeURIComponent(idOrSlug)}${suffix}`);
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async setPropertyMarketplaceVisibility(id: string, visibility: 'public' | 'private'): Promise<{ ok: boolean; status: number; body: any }> {
    try {
      const res = await apiFetch(`/properties/${encodeURIComponent(id)}/visibility`, {
        method: 'POST',
        body: JSON.stringify({ visibility }),
      });
      const body = await res.json().catch(() => ({}));
      return { ok: res.ok, status: res.status, body };
    } catch {
      return { ok: false, status: 0, body: { message: 'Cannot reach the API.' } };
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
    if (!configuredApiUrl) return buildStaticPublicCatalog();
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

  async login(email: string, password: string): Promise<{ success: boolean; token?: string; user?: Record<string, unknown>; error?: string; mfa_required?: boolean; challenge?: string; expires_in?: number }> {
    try {
      const res = await apiFetch('/auth/login', {
        method: 'POST',
        body: JSON.stringify({ email, password }),
      });
      const body = await res.json().catch(() => ({}));
      if (!res.ok) {
        // 429 = the login throttle kicked in (5 attempts/minute per IP).
        const message =
          res.status === 429
            ? 'Too many sign-in attempts. Please wait a minute and try again.'
            : body?.errors?.email?.[0] || body?.message || 'Invalid email or password.';
        return { success: false, error: message };
      }
      // Second factor required: no token is issued until the code is verified.
      // The challenge is parked here so the sign-in screen can ask for it.
      if (body?.mfa_required) {
        setPendingMfaChallenge({
          challenge: String(body.challenge || ''),
          expiresIn: Number(body.expires_in || 300),
        });
        return {
          success: false,
          error: 'Two-factor verification is required to finish signing in.',
          mfa_required: true,
          challenge: String(body.challenge || ''),
          expires_in: Number(body.expires_in || 300),
        };
      }
      clearPendingMfaChallenge();
      return { success: true, token: body.token, user: body.user };
    } catch {
      return { success: false, error: 'Cannot reach the MySQL API. Start the Laravel server on port 8080.' };
    }
  },

  async getGoogleRedirectUrl(nonce: string): Promise<{ success: boolean; url?: string; nonce_hash?: string; error?: string }> {
    try {
      const res = await fetch(`${API_BASE_URL}/auth/google/redirect?nonce=${encodeURIComponent(nonce)}`);
      const body = await res.json().catch(() => ({}));
      if (!res.ok) return { success: false, error: body?.message || 'Google Sign-In is not available.' };
      return { success: true, url: body.url, nonce_hash: body.nonce_hash };
    } catch {
      return { success: false, error: 'Cannot reach the API.' };
    }
  },

  async exchangeGoogleCode(code: string): Promise<{ success: boolean; token?: string; error?: string }> {
    try {
      const res = await apiFetch('/auth/google/exchange', {
        method: 'POST',
        body: JSON.stringify({ code }),
      });
      const body = await res.json().catch(() => ({}));
      if (!res.ok) return { success: false, error: body?.message || 'Google sign-in failed.' };
      return { success: true, token: body.token };
    } catch {
      return { success: false, error: 'Cannot reach the API.' };
    }
  },

  /**
   * Sign-in that understands the second factor (Phase 6).
   *
   * The backend never issues a token before a second factor is satisfied, so
   * this returns a discriminated result: `success`, `mfa_required` (with the
   * short-lived challenge) or `error`. On success the Sanctum token is stored
   * exactly like a normal sign-in.
   */
  async loginFlow(
    email: string,
    password: string,
  ): Promise<
    | { status: 'success'; token: string; user: Record<string, unknown> }
    | { status: 'mfa_required'; challenge: string; expiresIn: number }
    | { status: 'error'; message: string }
  > {
    try {
      const res = await apiFetch('/auth/login', {
        method: 'POST',
        body: JSON.stringify({ email, password }),
      });
      const body = await res.json().catch(() => ({}));
      if (!res.ok) {
        const message =
          res.status === 429
            ? 'Too many sign-in attempts. Please wait a minute and try again.'
            : body?.errors?.email?.[0] || body?.message || 'Invalid email or password.';
        return { status: 'error', message };
      }
      if (body?.mfa_required) {
        return {
          status: 'mfa_required',
          challenge: String(body.challenge || ''),
          expiresIn: Number(body.expires_in || 300),
        };
      }
      if (!body?.token) {
        return { status: 'error', message: 'The API did not return a session token.' };
      }
      setAuthToken(body.token);
      return { status: 'success', token: String(body.token), user: body.user ?? {} };
    } catch {
      return { status: 'error', message: 'Cannot reach the API. Please check your connection and try again.' };
    }
  },

  /** Completes a pending sign-in with an authenticator or recovery code. */
  async verifyMfa(
    challenge: string,
    code: string,
  ): Promise<{ success: boolean; user?: Record<string, unknown>; error?: string }> {
    try {
      const res = await apiFetch('/auth/mfa/verify', {
        method: 'POST',
        body: JSON.stringify({ challenge, code }),
      });
      const body = await res.json().catch(() => ({}));
      if (!res.ok) {
        const message = body?.message || 'That verification code is not valid.';
        return { success: false, error: message };
      }
      if (body?.token) setAuthToken(String(body.token));
      return { success: true, user: body.user ?? {} };
    } catch {
      return { success: false, error: 'Cannot reach the API. Please try again.' };
    }
  },

  /** Whether the signed-in account has a second factor enrolled. */
  async mfaStatus(): Promise<{ enabled: boolean; recoveryCodesRemaining: number; enabledAt?: string | null }> {
    try {
      const res = await apiFetch('/auth/mfa/status');
      const body = await res.json().catch(() => ({}));
      if (!res.ok) return { enabled: false, recoveryCodesRemaining: 0 };
      return {
        enabled: Boolean(body.enabled),
        recoveryCodesRemaining: Number(body.recovery_codes_remaining || 0),
        enabledAt: body.enabled_at ?? null,
      };
    } catch {
      return { enabled: false, recoveryCodesRemaining: 0 };
    }
  },

  /** Starts enrolment: returns the secret, the otpauth URI and recovery codes. */
  async mfaSetup(): Promise<{
    success: boolean;
    secret?: string;
    otpauthUri?: string;
    recoveryCodes?: string[];
    error?: string;
  }> {
    try {
      const res = await apiFetch('/auth/mfa/setup', { method: 'POST' });
      const body = await res.json().catch(() => ({}));
      if (!res.ok) {
        return { success: false, error: body?.message || 'Two-factor enrolment could not be started.' };
      }
      return {
        success: true,
        secret: body.secret,
        otpauthUri: body.otpauth_uri,
        recoveryCodes: Array.isArray(body.recovery_codes) ? body.recovery_codes : [],
      };
    } catch {
      return { success: false, error: 'Cannot reach the API. Please try again.' };
    }
  },

  /** Activates the second factor after the user confirms a live code. */
  async mfaEnable(code: string): Promise<{ success: boolean; error?: string }> {
    try {
      const res = await apiFetch('/auth/mfa/enable', {
        method: 'POST',
        body: JSON.stringify({ code }),
      });
      const body = await res.json().catch(() => ({}));
      if (!res.ok) return { success: false, error: body?.message || 'That code is not valid.' };
      return { success: true };
    } catch {
      return { success: false, error: 'Cannot reach the API. Please try again.' };
    }
  },

  /** Removes the second factor (password + valid code required server-side). */
  async mfaDisable(password: string, code: string): Promise<{ success: boolean; error?: string }> {
    try {
      const res = await apiFetch('/auth/mfa/disable', {
        method: 'POST',
        body: JSON.stringify({ password, code }),
      });
      const body = await res.json().catch(() => ({}));
      if (!res.ok) return { success: false, error: body?.message || 'Two-factor sign-in could not be removed.' };
      return { success: true };
    } catch {
      return { success: false, error: 'Cannot reach the API. Please try again.' };
    }
  },

  /** The account owner's own sign-in trail. */
  async loginHistory(): Promise<
    Array<{
      id: number;
      ip_address: string | null;
      user_agent: string | null;
      success: boolean;
      mfa_used: boolean;
      suspicious: boolean;
      reason: string | null;
      created_at: string | null;
    }>
  > {
    try {
      const res = await apiFetch('/auth/login-history');
      const body = await res.json().catch(() => ({}));
      if (!res.ok) return [];
      return Array.isArray(body?.history) ? body.history : [];
    } catch {
      return [];
    }
  },

  async register(payload: Record<string, unknown>): Promise<{ success: boolean; token?: string; user?: Record<string, unknown>; error?: string }> {
    try {
      const res = await apiFetch('/agency/register', {
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

  async getAdminAgencies(params: { page?: number; search?: string; status?: string } = {}): Promise<any | null> {
    try {
      const query = new URLSearchParams();
      for (const [key, value] of Object.entries(params)) {
        if (value !== undefined && value !== '') query.set(key, String(value));
      }
      const res = await apiFetch(`/admin/agencies?${query}`);
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async verifyAdminAgency(id: string, verificationStatus: 'verified' | 'rejected' | 'unverified' = 'verified', notes?: string): Promise<boolean> {
    try {
      const res = await apiFetch(`/admin/agencies/${encodeURIComponent(id)}/verify`, {
        method: 'PUT',
        body: JSON.stringify({ verification_status: verificationStatus, notes }),
      });
      return res.ok;
    } catch {
      return false;
    }
  },

  async setAdminAgencyStatus(id: string, status: string, reason?: string): Promise<boolean> {
    try {
      const res = await apiFetch(`/admin/agencies/${encodeURIComponent(id)}/status`, { method: 'PUT', body: JSON.stringify({ status, reason }) });
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

  // ------------------------------------------------------------------
  // Featured-agency placements (platform admin)
  // ------------------------------------------------------------------

  async getAdminFeaturedAgencies(params: Record<string, string | number | undefined> = {}): Promise<any | null> {
    try {
      const query = new URLSearchParams();
      for (const [key, value] of Object.entries(params)) {
        if (value !== undefined && value !== '') query.set(key, String(value));
      }
      const suffix = query.toString() ? `?${query.toString()}` : '';
      const res = await apiFetch(`/admin/featured-agencies${suffix}`);
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async createAdminFeaturedAgency(data: Record<string, unknown>): Promise<{ ok: boolean; status: number; body: any }> {
    try {
      const res = await apiFetch('/admin/featured-agencies', { method: 'POST', body: JSON.stringify(data) });
      const body = await res.json().catch(() => ({}));
      return { ok: res.ok, status: res.status, body };
    } catch {
      return { ok: false, status: 0, body: { message: 'Cannot reach the API.' } };
    }
  },

  async updateAdminFeaturedAgency(id: string | number, data: Record<string, unknown>): Promise<{ ok: boolean; status: number; body: any }> {
    try {
      const res = await apiFetch(`/admin/featured-agencies/${encodeURIComponent(String(id))}`, {
        method: 'PUT',
        body: JSON.stringify(data),
      });
      const body = await res.json().catch(() => ({}));
      return { ok: res.ok, status: res.status, body };
    } catch {
      return { ok: false, status: 0, body: { message: 'Cannot reach the API.' } };
    }
  },

  async cancelAdminFeaturedAgency(id: string | number, reason?: string): Promise<{ ok: boolean; status: number; body: any }> {
    try {
      const res = await apiFetch(`/admin/featured-agencies/${encodeURIComponent(String(id))}/cancel`, {
        method: 'POST',
        body: JSON.stringify({ reason }),
      });
      const body = await res.json().catch(() => ({}));
      return { ok: res.ok, status: res.status, body };
    } catch {
      return { ok: false, status: 0, body: { message: 'Cannot reach the API.' } };
    }
  },

  async reorderAdminFeaturedAgencies(ids: Array<string | number>): Promise<{ ok: boolean; status: number; body: any }> {
    try {
      const res = await apiFetch('/admin/featured-agencies/order', {
        method: 'PUT',
        body: JSON.stringify({ ids }),
      });
      const body = await res.json().catch(() => ({}));
      return { ok: res.ok, status: res.status, body };
    } catch {
      return { ok: false, status: 0, body: { message: 'Cannot reach the API.' } };
    }
  },

  async updateAdminPromotionSettings(data: Record<string, unknown>): Promise<{ ok: boolean; status: number; body: any }> {
    try {
      const res = await apiFetch('/admin/featured-agencies/settings', { method: 'PUT', body: JSON.stringify(data) });
      const body = await res.json().catch(() => ({}));
      return { ok: res.ok, status: res.status, body };
    } catch {
      return { ok: false, status: 0, body: { message: 'Cannot reach the API.' } };
    }
  },

  async createAdminPromotionPackage(data: Record<string, unknown>): Promise<{ ok: boolean; status: number; body: any }> {
    try {
      const res = await apiFetch('/admin/featured-agencies/packages', { method: 'POST', body: JSON.stringify(data) });
      const body = await res.json().catch(() => ({}));
      return { ok: res.ok, status: res.status, body };
    } catch {
      return { ok: false, status: 0, body: { message: 'Cannot reach the API.' } };
    }
  },

  async updateAdminPromotionPackage(id: number, data: Record<string, unknown>): Promise<{ ok: boolean; status: number; body: any }> {
    try {
      const res = await apiFetch(`/admin/featured-agencies/packages/${id}`, { method: 'PUT', body: JSON.stringify(data) });
      const body = await res.json().catch(() => ({}));
      return { ok: res.ok, status: res.status, body };
    } catch {
      return { ok: false, status: 0, body: { message: 'Cannot reach the API.' } };
    }
  },

  // ------------------------------------------------------------------
  // Agency storage oversight (platform admin)
  // ------------------------------------------------------------------

  async getAdminAgencyOversight(params: Record<string, string | number | undefined> = {}): Promise<any | null> {
    try {
      const query = new URLSearchParams();
      for (const [key, value] of Object.entries(params)) {
        if (value !== undefined && value !== '') query.set(key, String(value));
      }
      const suffix = query.toString() ? `?${query.toString()}` : '';
      const res = await apiFetch(`/admin/agencies-oversight${suffix}`);
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async getAdminAgencyOversightDetail(id: string): Promise<any | null> {
    try {
      const res = await apiFetch(`/admin/agencies-oversight/${encodeURIComponent(id)}`);
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
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

  // ------------------------------------------------------------------
  // Saved searches + email alerts (per-account)
  // ------------------------------------------------------------------

  async getSavedSearches(): Promise<any[] | null> {
    try {
      const res = await apiFetch('/saved-searches');
      if (!res.ok) return null;
      const body = await res.json().catch(() => ({}));
      return Array.isArray(body?.saved_searches) ? body.saved_searches : [];
    } catch {
      return null;
    }
  },

  async createSavedSearch(payload: {
    name: string;
    filters: Record<string, unknown>;
    frequency?: 'instant' | 'daily' | 'weekly';
    alerts_enabled?: boolean;
  }): Promise<{ ok: boolean; savedSearch?: any; error?: string }> {
    try {
      const res = await apiFetch('/saved-searches', {
        method: 'POST',
        body: JSON.stringify(payload),
      });
      const body = await res.json().catch(() => ({}));
      if (!res.ok) {
        const first = body?.errors ? Object.values(body.errors)[0] : null;
        const message = Array.isArray(first) ? first[0] : first;
        return { ok: false, error: String(message || body?.message || 'Could not save this search.') };
      }
      return { ok: true, savedSearch: body?.saved_search };
    } catch {
      return { ok: false, error: 'Could not save this search.' };
    }
  },

  async updateSavedSearch(
    id: string,
    payload: Partial<{ name: string; filters: Record<string, unknown>; frequency: string; alerts_enabled: boolean }>
  ): Promise<{ ok: boolean; savedSearch?: any; error?: string }> {
    try {
      const res = await apiFetch(`/saved-searches/${encodeURIComponent(id)}`, {
        method: 'PATCH',
        body: JSON.stringify(payload),
      });
      const body = await res.json().catch(() => ({}));
      if (!res.ok) return { ok: false, error: String(body?.message || 'Could not update this search.') };
      return { ok: true, savedSearch: body?.saved_search };
    } catch {
      return { ok: false, error: 'Could not update this search.' };
    }
  },

  async deleteSavedSearch(id: string): Promise<boolean> {
    try {
      const res = await apiFetch(`/saved-searches/${encodeURIComponent(id)}`, { method: 'DELETE' });
      return res.ok;
    } catch {
      return false;
    }
  },

  // ------------------------------------------------------------------
  // Support desk (self-service + platform staff)
  // ------------------------------------------------------------------

  async getSupportTickets(params: { status?: string; category?: string; search?: string } = {}): Promise<any[] | null> {
    try {
      const query = new URLSearchParams();
      if (params.status && params.status !== 'all') query.set('status', params.status);
      if (params.category && params.category !== 'all') query.set('category', params.category);
      if (params.search) query.set('search', params.search);
      const suffix = query.toString() ? `?${query.toString()}` : '';
      const res = await apiFetch(`/support/tickets${suffix}`);
      if (!res.ok) return null;
      const body = await res.json().catch(() => ({}));
      return Array.isArray(body?.tickets) ? body.tickets : [];
    } catch {
      return null;
    }
  },

  async getSupportTicket(id: string): Promise<{ ticket: any; messages: any[] } | null> {
    try {
      const res = await apiFetch(`/support/tickets/${encodeURIComponent(id)}`);
      if (!res.ok) return null;
      const body = await res.json().catch(() => ({}));
      return {
        ticket: body?.ticket ?? null,
        messages: Array.isArray(body?.messages) ? body.messages : [],
      };
    } catch {
      return null;
    }
  },

  async createSupportTicket(payload: {
    subject: string;
    body: string;
    category?: string;
    priority?: string;
  }): Promise<{ ok: boolean; ticket?: any; error?: string }> {
    try {
      const res = await apiFetch('/support/tickets', {
        method: 'POST',
        body: JSON.stringify(payload),
      });
      const body = await res.json().catch(() => ({}));
      if (!res.ok) {
        const first = body?.errors ? Object.values(body.errors)[0] : null;
        const message = Array.isArray(first) ? first[0] : first;
        return { ok: false, error: String(message || body?.message || 'Could not create the ticket.') };
      }
      return { ok: true, ticket: body?.ticket };
    } catch {
      return { ok: false, error: 'Could not create the ticket.' };
    }
  },

  async addSupportTicketMessage(id: string, body: string): Promise<{ ok: boolean; message?: any; error?: string }> {
    try {
      const res = await apiFetch(`/support/tickets/${encodeURIComponent(id)}/messages`, {
        method: 'POST',
        body: JSON.stringify({ body }),
      });
      const payload = await res.json().catch(() => ({}));
      if (!res.ok) return { ok: false, error: String(payload?.message || 'Could not add the message.') };
      return { ok: true, message: payload?.message_entry };
    } catch {
      return { ok: false, error: 'Could not add the message.' };
    }
  },

  async updateSupportTicketStatus(id: string, status: string): Promise<{ ok: boolean; ticket?: any; error?: string }> {
    try {
      const res = await apiFetch(`/support/tickets/${encodeURIComponent(id)}/status`, {
        method: 'POST',
        body: JSON.stringify({ status }),
      });
      const body = await res.json().catch(() => ({}));
      if (!res.ok) return { ok: false, error: String(body?.message || 'Could not update the ticket.') };
      return { ok: true, ticket: body?.ticket };
    } catch {
      return { ok: false, error: 'Could not update the ticket.' };
    }
  },

  async getAdminSupportTickets(
    params: { status?: string; category?: string; priority?: string; search?: string } = {}
  ): Promise<any[] | null> {
    try {
      const query = new URLSearchParams();
      Object.entries(params).forEach(([key, value]) => {
        if (value && value !== 'all') query.set(key, value);
      });
      const suffix = query.toString() ? `?${query.toString()}` : '';
      const res = await apiFetch(`/admin/support-tickets${suffix}`);
      if (!res.ok) return null;
      const body = await res.json().catch(() => ({}));
      return Array.isArray(body?.tickets) ? body.tickets : [];
    } catch {
      return null;
    }
  },

  async getSupportAssignableStaff(): Promise<any[] | null> {
    try {
      const res = await apiFetch('/admin/support-tickets/assignable-staff');
      if (!res.ok) return null;
      const body = await res.json().catch(() => ({}));
      return Array.isArray(body?.staff) ? body.staff : [];
    } catch {
      return null;
    }
  },

  async assignSupportTicket(id: string, assignedTo: number): Promise<{ ok: boolean; ticket?: any; error?: string }> {
    try {
      const res = await apiFetch(`/admin/support-tickets/${encodeURIComponent(id)}/assign`, {
        method: 'POST',
        body: JSON.stringify({ assigned_to: assignedTo }),
      });
      const body = await res.json().catch(() => ({}));
      if (!res.ok) return { ok: false, error: String(body?.message || 'Could not assign the ticket.') };
      return { ok: true, ticket: body?.ticket };
    } catch {
      return { ok: false, error: 'Could not assign the ticket.' };
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

  async updateInquiryNotes(id: string, notes: string): Promise<any | null> {
    try {
      const res = await apiFetch(`/inquiries/${id}/notes`, {
        method: 'PATCH',
        body: JSON.stringify({ notes }),
      });
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async deleteInquiry(id: string): Promise<any | null> {
    try {
      const res = await apiFetch(`/inquiries/${id}`, {
        method: 'DELETE',
      });
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async getMaps(category?: string, sector?: string, search?: string): Promise<any | null> {
    try {
      const params = new URLSearchParams();
      if (category && category !== 'All') params.set('category', category);
      if (sector && sector !== 'All') params.set('sector', sector);
      if (search) params.set('search', search);
      const qs = params.toString();
      const res = await fetch(`${API_BASE_URL}/maps${qs ? '?' + qs : ''}`);
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async getMap(slug: string): Promise<any | null> {
    try {
      const res = await fetch(`${API_BASE_URL}/maps/${encodeURIComponent(slug)}`);
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async getAdminMaps(): Promise<any | null> {
    try {
      const res = await apiFetch('/admin/maps');
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async createMap(formData: FormData): Promise<any | null> {
    try {
      const res = await apiFetch('/admin/maps', {
        method: 'POST',
        body: formData,
      });
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async bulkCreateMaps(formData: FormData): Promise<any | null> {
    try {
      const res = await apiFetch('/admin/maps/bulk', {
        method: 'POST',
        body: formData,
      });
      const body = await res.json().catch(() => null);
      if (!res.ok) return body ?? null;
      return body;
    } catch {
      return null;
    }
  },

  async updateMap(id: string, data: Record<string, unknown>): Promise<any | null> {
    try {
      const res = await apiFetch(`/admin/maps/${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
      });
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async replaceMapImage(id: string, file: File): Promise<any | null> {
    try {
      const fd = new FormData();
      fd.append('image', file);
      const res = await apiFetch(`/admin/maps/${id}/replace-image`, {
        method: 'POST',
        body: fd,
      });
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async toggleMap(id: string): Promise<any | null> {
    try {
      const res = await apiFetch(`/admin/maps/${id}/toggle`, { method: 'POST' });
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async featureMap(id: string): Promise<any | null> {
    try {
      const res = await apiFetch(`/admin/maps/${id}/feature`, { method: 'POST' });
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async deleteMap(id: string): Promise<any | null> {
    try {
      const res = await apiFetch(`/admin/maps/${id}`, { method: 'DELETE' });
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  async reorderMaps(order: string[]): Promise<any | null> {
    try {
      const res = await apiFetch('/admin/maps/reorder', {
        method: 'PUT',
        body: JSON.stringify({ order }),
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

  /** Vault scan lifecycle: held documents + rejected uploads (manage_permissions). */
  async getAgencyDocumentQuarantine(): Promise<any | null> {
    try {
      const res = await apiFetch('/agency/documents-quarantine');
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  },

  /** Rerun the malware scan for a held document; clean verdicts promote it. */
  async rescanAgencyDocument(id: string): Promise<{ ok: boolean; status: number; body: any }> {
    try {
      const res = await apiFetch(`/agency/documents/${id}/rescan`, { method: 'POST' });
      const body = await res.json().catch(() => ({}));
      return { ok: res.ok, status: res.status, body };
    } catch {
      return { ok: false, status: 0, body: { message: 'Cannot reach the API.' } };
    }
  },

  /** Server-enforced storage usage (documents + versions + media bytes). */
  async getAgencyDocumentStorageUsage(): Promise<any | null> {
    try {
      const res = await apiFetch('/agency/documents/storage-usage');
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
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


