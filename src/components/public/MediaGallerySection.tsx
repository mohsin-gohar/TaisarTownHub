import React, { useState } from 'react';
import { useApp } from '../../context/AppContext';
import {
  Image,
  Video,
  Play,
  FileText,
  Filter,
  Eye,
  Maximize2,
  FolderOpen
} from 'lucide-react';
import { VideoModal } from '../common/VideoModal';
import { MediaItem } from '../../types';

export const MediaGallerySection: React.FC = () => {
  const { mediaItems } = useApp();
  const [selectedFolder, setSelectedFolder] = useState<string>('All');
  const [activeVideo, setActiveVideo] = useState<{ url: string; title: string } | null>(null);
  const [activeImagePreview, setActiveImagePreview] = useState<string | null>(null);

  const folders = ['All', 'Projects', 'Properties', 'Videos', 'Documents', 'Banners'];

  const filteredMedia = mediaItems.filter((item) => {
    if (selectedFolder === 'All') return true;
    if (selectedFolder === 'Videos') return item.type === 'video' || item.folder === 'Videos';
    if (selectedFolder === 'Documents') return item.type === 'pdf' || item.folder === 'Documents';
    return item.folder === selectedFolder;
  });

  return (
    <section className="py-20 px-4 sm:px-6 lg:px-8 bg-[#101C2C]/30 border-t border-[#C5A46D]/15">
      <div className="max-w-7xl mx-auto space-y-12">
        {/* Section Header */}
        <div className="text-center max-w-3xl mx-auto space-y-3">
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-[#C5A46D]/15 text-[#C5A46D] border border-[#C5A46D]/30">
            <Image className="w-3.5 h-3.5" /> Media & Visual Archive
          </span>
          <h2 className="font-serif-luxury text-3xl sm:text-4xl font-bold text-white">
            Photo, Video & Construction Gallery
          </h2>
          <p className="text-slate-400 text-sm">
            High-resolution site captures, aerial drone surveys, sector maps, and architectural renders across Taiser Town Scheme 45.
          </p>

          {/* Folder Filter Tabs */}
          <div className="pt-4 flex flex-wrap items-center justify-center gap-2">
            {folders.map((folder) => {
              const isActive = selectedFolder === folder;
              return (
                <button
                  key={folder}
                  onClick={() => setSelectedFolder(folder)}
                  className={`px-4 py-1.5 rounded-full text-xs font-semibold transition-all cursor-pointer ${
                    isActive
                      ? 'bg-[#C5A46D] text-[#101C2C] shadow-md shadow-[#C5A46D]/20'
                      : 'bg-slate-800 text-slate-300 hover:bg-slate-700'
                  }`}
                >
                  {folder}
                </button>
              );
            })}
          </div>
        </div>

        {/* Gallery Grid */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
          {filteredMedia.map((media) => {
            const isVideo = media.type === 'video';
            const isPdf = media.type === 'pdf';

            return (
              <div
                key={media.id}
                className="group relative bg-[#0B1220] rounded-xl overflow-hidden border border-slate-800 hover:border-[#C5A46D]/50 shadow-lg transition-all duration-300 hover:-translate-y-1"
              >
                {/* Media Surface */}
                <div className="relative aspect-4/3 bg-slate-900 overflow-hidden flex items-center justify-center">
                  {isVideo ? (
                    <div
                      onClick={() => setActiveVideo({ url: media.url, title: media.title })}
                      className="w-full h-full relative cursor-pointer"
                    >
                      <img
                        src="https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=600&q=80"
                        alt={media.title}
                        className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                      />
                      <div className="absolute inset-0 bg-black/40 flex items-center justify-center">
                        <div className="w-12 h-12 rounded-full bg-[#C5A46D] text-[#101C2C] flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform">
                          <Play className="w-5 h-5 fill-current ml-0.5" />
                        </div>
                      </div>
                    </div>
                  ) : isPdf ? (
                    <div className="flex flex-col items-center justify-center p-6 text-center space-y-2">
                      <FileText className="w-12 h-12 text-[#C5A46D]" />
                      <span className="text-xs font-semibold text-white line-clamp-2">
                        {media.title}
                      </span>
                      <span className="text-[10px] text-slate-400">PDF Document</span>
                    </div>
                  ) : (
                    <img
                      src={media.url}
                      alt={media.title}
                      onClick={() => setActiveImagePreview(media.url)}
                      className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500 cursor-pointer"
                    />
                  )}

                  {/* Folder Tag */}
                  <span className="absolute top-2.5 left-2.5 px-2 py-0.5 rounded text-[10px] font-medium bg-black/70 text-slate-300 backdrop-blur-md">
                    {media.folder}
                  </span>
                </div>

                {/* Footer Info */}
                <div className="p-3.5 space-y-1 bg-[#0B1220]">
                  <h4 className="font-medium text-xs text-white truncate group-hover:text-[#C5A46D] transition-colors">
                    {media.title}
                  </h4>
                  <div className="flex items-center justify-between text-[10px] text-slate-400">
                    <span>{Math.round(media.fileSizeKB / 1024 * 10) / 10} MB</span>
                    <span>{media.uploadedAt}</span>
                  </div>
                </div>
              </div>
            );
          })}
        </div>

        {/* Video Playback Modal */}
        {activeVideo && (
          <VideoModal
            isOpen={!!activeVideo}
            onClose={() => setActiveVideo(null)}
            title={activeVideo.title}
            videoUrl={activeVideo.url}
          />
        )}

        {/* Image Fullscreen Preview Modal */}
        {activeImagePreview && (
          <div
            onClick={() => setActiveImagePreview(null)}
            className="fixed inset-0 z-50 bg-black/90 backdrop-blur-md flex items-center justify-center p-4 animate-fadeIn cursor-pointer"
          >
            <div className="relative max-w-4xl max-h-[90vh]">
              <img
                src={activeImagePreview}
                alt="Enlarged preview"
                className="max-h-[85vh] w-auto rounded-xl shadow-2xl object-contain border border-[#C5A46D]/30"
              />
              <span className="text-center text-xs text-slate-400 block mt-3">
                Click anywhere to close preview
              </span>
            </div>
          </div>
        )}
      </div>
    </section>
  );
};

