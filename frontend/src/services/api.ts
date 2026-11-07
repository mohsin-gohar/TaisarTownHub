/**
 * TaisarTownHub Backend API Service
 * Connects the React luxury frontend to the Laravel + MySQL API backend.
 */

export const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://127.0.0.1:8080/api';

const TOKEN_KEY = 'tth_token';

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
  if (!headers.has('Content-Type') && options.body) {
    headers.set('Content-Type', 'application/json');
  }
  const token = getAuthToken();
  if (token) headers.set('Authorization', `Bearer ${token}`);
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

  async likeVideo(videoId: string): Promise<boolean> {
    try {
      const res = await fetch(`${API_BASE_URL}/videos/${videoId}/like`, { method: 'POST' });
      return res.ok;
    } catch {
      return false;
    }
  },

  async postVideoComment(videoId: string, userName: string, comment: string): Promise<any | null> {
    try {
      const res = await fetch(`${API_BASE_URL}/videos/${videoId}/comments`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ userName, comment })
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
  }): Promise<any | null> {
    try {
      const res = await fetch(`${API_BASE_URL}/inquiries`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(inquiryData)
      });
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  }
};
