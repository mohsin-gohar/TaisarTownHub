import React, { useCallback, useEffect } from 'react';
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

  return (
    <div
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
          <span className="text-[#C5A46D] font-bold">{index + 1}</span>
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
          className="p-2 rounded-full bg-white/5 hover:bg-[#C5A46D] hover:text-[#101C2C] text-white transition-colors cursor-pointer"
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
            className="absolute left-3 z-10 p-2.5 rounded-full bg-[#101C2C]/80 border border-white/10 text-white hover:bg-[#C5A46D] hover:text-[#101C2C] transition-colors cursor-pointer"
          >
            <ChevronLeft className="w-5 h-5" />
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
            className="absolute right-3 z-10 p-2.5 rounded-full bg-[#101C2C]/80 border border-white/10 text-white hover:bg-[#C5A46D] hover:text-[#101C2C] transition-colors cursor-pointer"
          >
            <ChevronRight className="w-5 h-5" />
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
                i === index ? 'border-[#C5A46D] opacity-100' : 'border-transparent opacity-50 hover:opacity-90'
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
                isVideoSlot ? 'border-[#C5A46D] bg-[#C5A46D]/20' : 'border-transparent bg-white/10 opacity-60 hover:opacity-90'
              }`}
            >
              <Play className="w-4 h-4 text-[#C5A46D]" />
            </button>
          )}
        </div>
      )}
    </div>
  );
};
