import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App.tsx';
import './index.css';
import { checkReloadLimit } from './utils/reloadGuard';

const guard = checkReloadLimit();

if (guard.blocked) {
  // Reload limit tripped: render a calm, on-brand notice with a live
  // countdown instead of hammering the server with another render cycle.
  const root = createRoot(document.getElementById('root')!);
  const Overlay: React.FC<{ retryAfterSec: number }> = ({ retryAfterSec }) => {
    const [secondsLeft, setSecondsLeft] = React.useState(retryAfterSec);
    React.useEffect(() => {
      const timer = window.setInterval(() => {
        setSecondsLeft((s) => (s <= 1 ? 0 : s - 1));
      }, 1000);
      return () => window.clearInterval(timer);
    }, []);
    React.useEffect(() => {
      if (secondsLeft === 0) {
        // Window has slid past the limit — allow the app through.
        window.location.reload();
      }
    }, [secondsLeft]);
    return (
      <div className="min-h-screen bg-[#0B1220] text-slate-200 flex items-center justify-center p-6">
        <div
          role="alertdialog"
          aria-modal="true"
          aria-labelledby="reload-guard-title"
          className="max-w-md w-full bg-[#101C2C] border border-[#C5A46D]/40 rounded-2xl p-8 shadow-2xl space-y-4 text-center"
        >
          <div className="text-3xl">⏳</div>
          <h1 id="reload-guard-title" className="font-serif-luxury text-xl font-bold text-white">
            Too many reloads
          </h1>
          <p className="text-xs text-slate-400 leading-relaxed">
            The page was refreshed more than 5 times within a minute. To keep the demo
            environment fast for everyone, please wait a moment before reloading again.
          </p>
          <p className="text-2xl font-bold text-[#C5A46D]" aria-live="polite">
            {secondsLeft > 0 ? `${secondsLeft}s` : 'Reloading…'}
          </p>
          <p className="text-[10px] text-slate-500">
            The window resets automatically - no action needed.
          </p>
        </div>
      </div>
    );
  };
  root.render(<Overlay retryAfterSec={guard.retryAfterSec} />);
} else {
  createRoot(document.getElementById('root')!).render(<App />);
}