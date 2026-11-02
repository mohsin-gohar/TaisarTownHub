import React, { useEffect, useRef } from 'react';
import { AlertTriangle, X } from 'lucide-react';
import { useApp } from '../../context/AppContext';

/**
 * Accessible replacement for `window.confirm()`.
 *
 * Renders a modal dialog with a focus trap, Escape-to-cancel, backdrop-click
 * cancel, and explicit Cancel / Confirm buttons. It is driven by
 * `confirmAction(message)` from AppContext, which resolves a promise with the
 * user's decision.
 */
export const ConfirmDialog: React.FC<{
  message: string;
  title?: string;
  confirmLabel?: string;
  cancelLabel?: string;
  onConfirm: () => void;
  onCancel: () => void;
}> = ({ message, title = 'Please confirm', confirmLabel = 'Yes, continue', cancelLabel = 'Cancel', onConfirm, onCancel }) => {
  const panelRef = useRef<HTMLDivElement | null>(null);
  const confirmRef = useRef<HTMLButtonElement | null>(null);

  useEffect(() => {
    confirmRef.current?.focus();
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        e.preventDefault();
        onCancel();
        return;
      }
      if (e.key === 'Tab' && panelRef.current) {
        const focusables = panelRef.current.querySelectorAll<HTMLElement>(
          'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        if (focusables.length === 0) return;
        const first = focusables[0];
        const last = focusables[focusables.length - 1];
        if (e.shiftKey && document.activeElement === first) {
          e.preventDefault();
          last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [onCancel]);

  return (
    <div
      className="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm animate-fadeIn"
      onMouseDown={(e) => {
        if (e.target === e.currentTarget) onCancel();
      }}
    >
      <div
        ref={panelRef}
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="confirm-dialog-title"
        aria-describedby="confirm-dialog-message"
        className="relative w-full max-w-md bg-[#101C2C] border border-[#C5A46D]/40 rounded-2xl p-6 shadow-2xl space-y-4"
      >
        <button
          onClick={onCancel}
          aria-label="Close dialog"
          className="absolute top-4 right-4 text-slate-400 hover:text-white p-1 rounded-lg cursor-pointer"
        >
          <X className="w-5 h-5" />
        </button>

        <div className="flex items-start gap-3">
          <div className="shrink-0 w-10 h-10 rounded-xl bg-amber-500/15 border border-amber-500/40 flex items-center justify-center">
            <AlertTriangle className="w-5 h-5 text-amber-400" />
          </div>
          <div className="space-y-1 pr-6">
            <h3 id="confirm-dialog-title" className="font-serif-luxury text-lg font-bold text-white">
              {title}
            </h3>
            <p id="confirm-dialog-message" className="text-xs text-slate-300 leading-relaxed">
              {message}
            </p>
          </div>
        </div>

        <div className="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2 border-t border-slate-800">
          <button
            onClick={onCancel}
            className="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold transition cursor-pointer"
          >
            {cancelLabel}
          </button>
          <button
            ref={confirmRef}
            onClick={onConfirm}
            className="px-4 py-2.5 rounded-xl bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C] text-xs font-bold transition cursor-pointer shadow-lg shadow-[#C5A46D]/20"
          >
            {confirmLabel}
          </button>
        </div>
      </div>
    </div>
  );
};

/** Mounted once inside AppProvider; renders the active pending confirmation. */
export const ConfirmDialogHost: React.FC = () => {
  const { pendingConfirm, resolveConfirm } = useApp();
  if (!pendingConfirm) return null;
  return (
    <ConfirmDialog
      message={pendingConfirm.message}
      title={pendingConfirm.title}
      confirmLabel={pendingConfirm.confirmLabel}
      onConfirm={() => resolveConfirm(true)}
      onCancel={() => resolveConfirm(false)}
    />
  );
};
