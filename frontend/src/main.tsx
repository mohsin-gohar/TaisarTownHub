import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App.tsx';
import './index.css';

createRoot(document.getElementById('root')!).render(<App />);

// G-17: PWA service worker — production builds only (never under HMR dev).
// Registered from the bundle (instead of an inline script) so the deployed
// site can run under a strict Content-Security-Policy without 'unsafe-inline'.
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    const host = location.hostname;
    const isDev = host === 'localhost' || host === '127.0.0.1';
    if (isDev) return;
    navigator.serviceWorker.register('/sw.js').catch(() => {
      /* offline support is best-effort */
    });
  });
}
