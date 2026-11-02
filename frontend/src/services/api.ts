/**
 * TaisarTownHub Backend API Service
 * Connects the React luxury frontend to the Laravel + MySQL API backend.
 */

export const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://127.0.0.1:8080/api';

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
