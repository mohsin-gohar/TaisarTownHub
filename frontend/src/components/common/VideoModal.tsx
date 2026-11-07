import React, { useEffect } from 'react';
import { X, Play, Maximize2 } from 'lucide-react';

interface VideoModalProps {
  isOpen: boolean;
  onClose: () => void;
  title: string;
  videoUrl?: string;
}

export const VideoModal: React.FC<VideoModalProps> = ({ isOpen, onClose, title, videoUrl }) => {
  useEffect(() => {
    if (!isOpen) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [isOpen, onClose]);

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
          <video
            src={videoUrl}
            controls
            autoPlay
            className="w-full h-full object-contain"
          >
            Your browser does not support HTML5 video streaming.
          </video>
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
