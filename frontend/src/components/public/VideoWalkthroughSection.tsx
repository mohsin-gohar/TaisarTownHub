import React, { useState, useEffect, useCallback } from 'react';
import { Play, Heart, MessageSquare, Share2, Clock, MapPin, ArrowRight } from 'lucide-react';
import { api } from '../../services/api';

interface VideoItem {
  id: string;
  title: string;
  description: string;
  videoUrl: string;
  thumbnailUrl: string;
  agencyName: string;
  dealerName: string;
  dealerAvatar: string;
  phase: string;
  sector: string;
  duration: string;
  likes: number;
  views: number;
  tags: string[];
  uploadedAt: string;
}

export const VideoWalkthroughSection: React.FC = () => {
  const [videos, setVideos] = useState<VideoItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchVideos = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await api.getVideos();
      if (Array.isArray(data)) {
        // Filter out placeholder/sample video URLs — only show real content.
        const real = data.filter((v: any) => {
          const url = (v.videoUrl || '').toLowerCase();
          return url && !url.includes('sample-video');
        });
        setVideos(real);
      } else {
        setError('Unable to load video walkthroughs.');
      }
    } catch {
      setError('Unable to load video walkthroughs.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchVideos();
  }, [fetchVideos]);

  if (loading) {
    return (
      <div className="max-w-3xl border-t border-slate-800 pt-6">
        <div className="flex items-center gap-3 py-8 text-slate-400 text-sm">
          <div className="w-3 h-3 rounded-full border-2 border-[#E7C878] border-t-transparent animate-spin"></div>
          <span>Loading video walkthroughs…</span>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="max-w-3xl border-t border-slate-800 pt-6">
        <div className="text-center py-8">
          <p className="text-slate-400 text-sm">{error}</p>
          <button
            onClick={fetchVideos}
            className="mt-3 inline-flex items-center gap-2 text-sm text-[#E7C878] hover:text-[#F6E3B4]"
          >
            Try again <ArrowRight className="w-4 h-4" />
          </button>
        </div>
      </div>
    );
  }

  if (videos.length === 0) {
    return (
      <div className="max-w-3xl border-t border-slate-800 pt-6">
        <div className="text-center py-8">
          <p className="text-slate-400 text-sm">No verified video walkthroughs are available right now.</p>
          <p className="text-slate-500 text-xs mt-2">Video tours appear here once an agency publishes an approved walkthrough.</p>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-3xl border-t border-slate-800 pt-6 space-y-6">
      {videos.map((video) => (
        <article key={video.id} className="group relative overflow-hidden rounded-xl border border-slate-800 bg-[#0a1a26]">
          <div className="relative aspect-video rounded-t-xl overflow-hidden">
            <img
              src={video.thumbnailUrl}
              alt={video.title}
              className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
              loading="lazy"
            />
            <div className="absolute inset-0 bg-black/40 group-hover:bg-black/20 transition-colors flex items-center justify-center">
              <Play className="w-10 h-10 text-white bg-[#E7C878]/20 rounded-full p-1" />
            </div>
            <div className="absolute bottom-2 left-2 bg-black/60 backdrop-blur px-3 py-1 rounded text-[11px] text-white font-medium">
              {video.duration}
            </div>
            <div className="absolute bottom-2 right-2 bg-black/60 backdrop-blur px-3 py-1 rounded text-[11px] text-white font-medium">
              {video.views.toLocaleString()} views
            </div>
          </div>
          <div className="p-4">
            <div className="flex items-center gap-2 mb-2">
              <img
                src={video.dealerAvatar}
                alt=""
                className="w-7 h-7 rounded-full object-cover border border-[#E7C878]/30"
              />
              <div>
                <p className="text-xs text-[#E7C878] font-medium">{video.agencyName}</p>
                <p className="text-xs text-slate-400">{video.dealerName}</p>
              </div>
              <span className="ml-auto text-[10px] text-slate-500 bg-slate-800 px-2 py-0.5 rounded">{video.phase}</span>
            </div>
            <h3 className="font-serif-luxury text-lg font-bold text-white mb-1 group-hover:text-[#E7C878] transition-colors">
              {video.title}
            </h3>
            <p className="text-slate-400 text-xs mb-3 line-clamp-2">{video.description}</p>
            <div className="flex items-center justify-between text-xs text-slate-400">
              <div className="flex items-center gap-1">
                <MapPin className="w-3 h-3" />
                <span>{video.sector}</span>
              </div>
              <div className="flex items-center gap-1">
                <Heart className="w-3 h-3" />
                <span>{video.likes.toLocaleString()}</span>
              </div>
              <div className="flex items-center gap-1">
                <MessageSquare className="w-3 h-3" />
                <span>{video.views > 0 ? 'Comments' : ''}</span>
              </div>
              <div className="flex items-center gap-1 text-[#E7C878]">
                <Clock className="w-3 h-3" />
                <span>{video.uploadedAt}</span>
              </div>
            </div>
          </div>
        </article>
      ))}
    </div>
  );
};
