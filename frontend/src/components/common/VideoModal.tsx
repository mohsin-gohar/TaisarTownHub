import React, { useEffect, useState } from 'react';
import { X, Play, Maximize2, AlertCircle, Loader2 } from 'lucide-react';

interface VideoModalProps {
  isOpen: boolean;
  onClose: () => void;
  title: string;
  videoUrl?: string;
}

export const VideoModal: React.FC<VideoModalProps> = ({ isOpen, onClose, title, videoUrl }) => {
  const [status, setStatus] = useState<'loading' | 'ready' | 'error'>('loading');

  useEffect(() => {
    if (!isOpen) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [isOpen, onClose]);

  useEffect(() => {
    if (isOpen && videoUrl) setStatus('loading');
  }, [isOpen, videoUrl]);

  if (!isOpen || !videoUrl) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md animate-fadeIn">
      <div className="relative w-full max-w-4xl bg-[#101C2C] border border-[#C5A46D]/30 rounded-2xl overflow-hidden shadow-2xl">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-slate-800 bg-[#0B1220]">
          <div className="flex items-center gap-2">
            <Play className="w-4 h-4 text-[#C5A46D]" />
            <h3 className="font-serif-luxury font-bold text-white text-base truncate max-w-lg">
              {title}
            </h3>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors cursor-pointer"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Video Player Area */}
        <div className="relative aspect-video bg-black flex items-center justify-center">
          {status === 'loading' && (
            <div className="absolute inset-0 flex items-center justify-center z-10 pointer-events-none">
              <Loader2 className="w-8 h-8 text-[#C5A46D] animate-spin" />
            </div>
          )}
          {status === 'error' ? (
            <div className="flex flex-col items-center gap-3 text-slate-300 px-6 text-center">
              <AlertCircle className="w-10 h-10 text-[#C5A46D]" />
              <p className="text-sm">This video could not be loaded. It may be unavailable or blocked by your network.</p>
              <a
                href={videoUrl}
                target="_blank"
                rel="noopener noreferrer"
                className="text-[#C5A46D] underline text-xs hover:text-[#D4AF37]"
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
        <div className="px-6 py-3 bg-[#0B1220] flex items-center justify-between text-xs text-slate-400">
          <span>High-Definition Architectural Walkthrough & Site Footage</span>
          <span className="text-[#C5A46D] font-medium">Taiser Town Scheme 45 Media Archive</span>
        </div>
      </div>
    </div>
  );
};
