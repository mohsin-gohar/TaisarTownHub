import React from 'react';
import { useApp } from '../../context/AppContext';
import { CheckCircle2, AlertCircle, Info, X } from 'lucide-react';

export const ToastContainer: React.FC = () => {
  const { toasts, removeToast } = useApp();

  if (toasts.length === 0) return null;

  return (
    <div
      className="fixed bottom-6 right-4 sm:right-6 z-[60] flex flex-col gap-2.5 max-w-sm w-[calc(100vw-2rem)] sm:w-full pointer-events-none"
      aria-live="polite"
    >
      {toasts.map((toast) => {
        const isSuccess = toast.type === 'success';
        const isError = toast.type === 'error';

        return (
          <div
            key={toast.id}
            className={`pointer-events-auto relative overflow-hidden flex items-start gap-3 p-4 rounded-xl border shadow-2xl backdrop-blur-md animate-slideIn ${
              isSuccess
                ? 'bg-[#0B1220]/95 border-emerald-500/40 text-emerald-200'
                : isError
                ? 'bg-rose-950/95 border-rose-500/40 text-rose-200'
                : 'bg-slate-900/95 border-[#E7C878]/40 text-slate-200'
            }`}
            role="status"
          >
            {isSuccess && <CheckCircle2 className="w-5 h-5 text-emerald-400 shrink-0 mt-0.5" />}
            {isError && <AlertCircle className="w-5 h-5 text-rose-400 shrink-0 mt-0.5" />}
            {!isSuccess && !isError && <Info className="w-5 h-5 text-[#E7C878] shrink-0 mt-0.5" />}

            <div className="flex-1 text-xs sm:text-sm font-medium pt-0.5">
              {toast.message}
            </div>

            <button
              onClick={() => removeToast(toast.id)}
              aria-label="Dismiss notification"
              className="text-slate-400 hover:text-white shrink-0 cursor-pointer"
            >
              <X className="w-4 h-4" />
            </button>

            {/* Auto-dismiss progress bar (matches AppContext's 4.5s timer) */}
            <span
              aria-hidden="true"
              className={`absolute bottom-0 left-0 h-0.5 w-full animate-progressBar origin-right ${
                isSuccess ? 'bg-emerald-400/60' : isError ? 'bg-rose-400/60' : 'bg-[#E7C878]/60'
              }`}
            />
          </div>
        );
      })}
    </div>
  );
};
