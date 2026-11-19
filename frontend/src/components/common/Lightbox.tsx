import React, { useCallback, useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';
import { ChevronLeft, ChevronRight, X, Play } from 'lucide-react';

interface LightboxProps {
  images: string[];
  videoUrl?: string;
  title: string;
  /** 0-based open index, or null when closed. "video" slot is appended after images. */
  openIndex: number | null;
  onClose: () => void;
  onNavigate?: (index: number) => void;
}

/**
 * Premium full-screen gallery lightbox with keyboard navigation,
 * thumbnail strip, and in-place video playback overlay.
 * Respects prefers-reduced-motion via CSS classes only (no JS animation loops).
 */
export const Lightbox: React.FC<LightboxProps> = ({
  images,
  videoUrl,
  title,
  openIndex,
  onClose,
  onNavigate,
}) => {
  const total = images.length + (videoUrl ? 1 : 0);
  const isOpen = openIndex !== null && total > 0;
  const index = Math.min(Math.max(openIndex ?? 0, 0), Math.max(total - 1, 0));
  const isVideoSlot = index >= images.length;

  const containerRef = useRef<HTMLDivElement | null>(null);

  // Focus management: move focus into the dialog on open, restore it on close.
  useEffect(() => {
    if (!isOpen) return;
    const opener = document.activeElement as HTMLElement | null;
    const timer = window.setTimeout(() => {
      containerRef.current
        ?.querySelector<HTMLElement>('button[aria-label="Close gallery"]')
        ?.focus();
    }, 0);
    return () => {
      window.clearTimeout(timer);
      if (opener && typeof opener.focus === 'function') opener.focus();
    };
  }, [isOpen]);

  const goTo = useCallback(
    (next: number) => {
      if (!onNavigate || total === 0) return;
      onNavigate(((next % total) + total) % total);
    },
    [onNavigate, total]
  );

  // Keyboard navigation + scroll lock
  useEffect(() => {
    if (!isOpen) return;
    const handleKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
      else if (e.key === 'ArrowRight') goTo(index + 1);
      else if (e.key === 'ArrowLeft') goTo(index - 1);
      else if (e.key === 'Home') goTo(0);
      else if (e.key === 'End') goTo(total - 1);
      else if (e.key === 'Tab' && containerRef.current) {
        // Focus trap: keep Tab / Shift+Tab cycling inside the dialog.
        const focusables = containerRef.current.querySelectorAll<HTMLElement>(
          'button, [href], input, select, textarea, video, [tabindex]:not([tabindex="-1"])'
        );
        if (focusables.length === 0) return;
        const first = focusables[0];
        const last = focusables[focusables.length - 1];
        const active = document.activeElement;
        if (!containerRef.current.contains(active)) {
          e.preventDefault();
          first.focus();
        } else if (e.shiftKey && active === first) {
          e.preventDefault();
          last.focus();
        } else if (!e.shiftKey && active === last) {
          e.preventDefault();
          first.focus();
        }
      }
    };
    window.addEventListener('keydown', handleKey);
    const prevOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      window.removeEventListener('keydown', handleKey);
      document.body.style.overflow = prevOverflow;
    };
  }, [isOpen, index, total, goTo, onClose]);

  if (!isOpen) return null;
  if (typeof document === 'undefined') return null;

  return createPortal(
    <div
      ref={containerRef}
      className="fixed inset-0 z-[70] bg-black/95 backdrop-blur-sm flex flex-col animate-fadeIn"
      role="dialog"
      aria-modal="true"
      aria-label={`${title} gallery`}
      onClick={onClose}
    >
      {/* Top bar */}
      <div
        className="flex items-center justify-between px-5 py-3 border-b border-white/10"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="text-xs text-slate-300 font-medium tracking-wide">
          <span className="text-[#E7C878] font-bold">{index + 1}</span>
          <span className="mx-1.5 text-slate-500">/</span>
          <span>{total}</span>
          <span className="hidden sm:inline text-slate-500 ml-3 truncate max-w-[280px] md:max-w-none inline-block align-middle">
            {title}
          </span>
        </div>
        <div className="flex items-center gap-2 text-[10px] text-slate-500 hidden sm:flex">
          <span className="hidden sm:inline">Use ← → keys · Esc to close</span>
        </div>
        <button
          onClick={onClose}
          aria-label="Close gallery"
          className="p-2 rounded-full bg-white/5 hover:bg-[#E7C878] hover:text-[#0B1220] text-white transition-colors cursor-pointer"
        >
          <X className="w-5 h-5" />
        </button>
      </div>

      {/* Stage */}
      <div
        className="flex-1 flex items-center justify-center relative px-14 py-4 min-h-0"
        onClick={(e) => e.stopPropagation()}
      >
        {total > 1 && (
          <button
            onClick={() => goTo(index - 1)}
            aria-label="Previous image"
            className="absolute left-3 z-10 p-3 rounded-full bg-[#0B1220]/90 border border-white/20 text-white hover:bg-[#E7C878] hover:text-[#0B1220] transition-colors cursor-pointer touch-none sm:cursor-pointer"
          >
            <ChevronLeft className="w-6 h-6" />
          </button>
        )}

        {isVideoSlot ? (
          <video
            key={videoUrl}
            src={videoUrl}
            controls
            autoPlay
            playsInline
            className="max-h-full max-w-full rounded-xl shadow-2xl bg-black"
          />
        ) : (
          <img
            key={images[index]}
            src={images[index]}
            alt={`${title} — image ${index + 1}`}
            className="max-h-full max-w-full object-contain rounded-xl shadow-2xl animate-fadeIn"
            decoding="async"
          />
        )}

        {total > 1 && (
          <button
            onClick={() => goTo(index + 1)}
            aria-label="Next image"
            className="absolute right-3 z-10 p-3 rounded-full bg-[#0B1220]/90 border border-white/20 text-white hover:bg-[#E7C878] hover:text-[#0B1220] transition-colors cursor-pointer touch-none sm:cursor-pointer"
          >
            <ChevronRight className="w-6 h-6" />
          </button>
        )}
      </div>

      {/* Thumbnail strip */}
      {total > 1 && (
        <div
          className="flex items-center gap-2 overflow-x-auto px-5 py-3 border-t border-white/10"
          onClick={(e) => e.stopPropagation()}
        >
          {images.map((img, i) => (
            <button
              key={i}
              onClick={() => goTo(i)}
              aria-label={`Open image ${i + 1}`}
              aria-current={i === index}
              className={`relative w-16 h-11 rounded-lg overflow-hidden shrink-0 border-2 transition-all cursor-pointer ${
                i === index ? 'border-[#E7C878] opacity-100' : 'border-transparent opacity-50 hover:opacity-90'
              }`}
            >
              <img src={img} alt="" className="w-full h-full object-cover" loading="lazy" decoding="async" />
            </button>
          ))}
          {videoUrl && (
            <button
              onClick={() => goTo(images.length)}
              aria-label="Play property video"
              aria-current={isVideoSlot}
              className={`relative w-16 h-11 rounded-lg shrink-0 border-2 flex items-center justify-center transition-all cursor-pointer ${
                isVideoSlot ? 'border-[#E7C878] bg-[#E7C878]/20' : 'border-transparent bg-white/10 opacity-60 hover:opacity-90'
              }`}
            >
              <Play className="w-4 h-4 text-[#E7C878]" />
            </button>
          )}
        </div>
      )}
    </div>,
    document.body
  );
};
