import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { X, Play, Maximize2, AlertCircle, Loader2 } from 'lucide-react';

interface VideoModalProps {
  isOpen: boolean;
  onClose: () => void;
  title: string;
  videoUrl?: string;
}

export const VideoModal: React.FC<VideoModalProps> = ({ isOpen, onClose, title, videoUrl }) => {
  const [status, setStatus] = useState<'loading' | 'ready' | 'error'>('loading');

  // Escape key handler
  useEffect(() => {
    if (!isOpen) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [isOpen, onClose]);

  // Lock body scroll while modal is active
  useEffect(() => {
    if (!isOpen) return;
    const prevOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      document.body.style.overflow = prevOverflow;
    };
  }, [isOpen]);

  useEffect(() => {
    if (isOpen && videoUrl) setStatus('loading');
  }, [isOpen, videoUrl]);

  if (!isOpen || !videoUrl) return null;
  if (typeof document === 'undefined') return null;

  return createPortal(
    <div
      className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/85 backdrop-blur-md animate-fadeIn"
      onMouseDown={(e) => {
        if (e.target === e.currentTarget) onClose();
      }}
      role="dialog"
      aria-modal="true"
      aria-label={title}
    >
      <div className="relative w-full max-w-4xl bg-[#0B1220] border border-[#E7C878]/30 rounded-2xl overflow-hidden shadow-2xl animate-scaleUp">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-slate-800 bg-[#07141E]">
          <div className="flex items-center gap-2">
            <Play className="w-4 h-4 text-[#E7C878]" />
            <h3 className="font-serif-luxury font-bold text-white text-base truncate max-w-lg">
              {title}
            </h3>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors cursor-pointer"
            aria-label="Close video player"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Video Player Area */}
        <div className="relative aspect-video bg-black flex items-center justify-center">
          {status === 'loading' && (
            <div className="absolute inset-0 flex items-center justify-center z-10 pointer-events-none">
              <Loader2 className="w-8 h-8 text-[#E7C878] animate-spin" />
            </div>
          )}
          {status === 'error' ? (
            <div className="flex flex-col items-center gap-3 text-slate-300 px-6 text-center">
              <AlertCircle className="w-10 h-10 text-[#E7C878]" />
              <p className="text-sm">This video could not be loaded. It may be unavailable or blocked by your network.</p>
              <a
                href={videoUrl}
                target="_blank" rel="noopener noreferrer"
                className="text-[#E7C878] underline text-xs hover:text-[#F3C464]"
              >
                Open video in a new tab
              </a>
            </div>
          ) : (
            <video
              key={videoUrl}
              src={videoUrl}
              controls
              autoPlay
              muted
              playsInline
              preload="metadata"
              className="w-full h-full object-contain"
              onLoadedData={() => setStatus('ready')}
              onCanPlay={() => setStatus('ready')}
              onError={() => setStatus('error')}
            >
              Your browser does not support HTML5 video streaming.
            </video>
          )}
        </div>

        {/* Footer info */}
        <div className="px-6 py-3 bg-[#07141E] flex items-center justify-between text-xs text-slate-400">
          <span>High-Definition Architectural Walkthrough & Site Footage</span>
          <span className="text-[#E7C878] font-medium">Taiser Town Scheme 45 Media Archive</span>
        </div>
      </div>
    </div>,
    document.body
  );
};




