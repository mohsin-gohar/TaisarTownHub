import React, { useState, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { useApp } from '../../context/AppContext';
import {
  Video,
  Play,
  Heart,
  MessageSquare,
  Share2,
  PlusCircle,
  Building2,
  CheckCircle2,
  X,
  Send,
  Eye,
  Clock,
  Sparkles,
  MapPin,
  Compass
} from 'lucide-react';
import { hasPermission } from '../../utils/logic';
import { AgencyVideo } from '../../types';
import { VideoModal } from '../common/VideoModal';

export const MediaGallerySection: React.FC = () => {
  const { agencyVideos, likeAgencyVideo, addAgencyVideoComment, addAgencyVideo, dealers, showToast, currentUser } = useApp();
  const canPublishTour = hasPermission(currentUser.role, 'upload_media') || hasPermission(currentUser.role, 'manage_media');
  const [selectedPhase, setSelectedPhase] = useState<string>('All');
  const [activeVideo, setActiveVideo] = useState<{ url: string; title: string } | null>(null);
  const [activeCommentsVideoId, setActiveCommentsVideoId] = useState<string | null>(null);
  const [commentAuthor, setCommentAuthor] = useState('');
  const [commentText, setCommentText] = useState('');

  // Upload Video Tour Modal state
  const [isAddModalOpen, setIsAddModalOpen] = useState(false);

  useEffect(() => {
    if (!isAddModalOpen) return;
    const prevOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setIsAddModalOpen(false);
    };
    window.addEventListener('keydown', onKey);
    return () => {
      document.body.style.overflow = prevOverflow;
      window.removeEventListener('keydown', onKey);
    };
  }, [isAddModalOpen]);
  const [newVideo, setNewVideo] = useState({
    title: '',
    description: '',
    videoUrl: '/sample-video.mp4',
    thumbnailUrl: 'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=1000&q=80',
    agencyId: dealers[0]?.id || 'deal-1',
    agencyName: dealers[0]?.agencyOrBranch || dealers[0]?.agencyAffiliation || 'TaisarTownHub Premier Advisory',
    dealerName: dealers[0]?.fullName || 'Saleem Siddiqui',
    dealerAvatar: dealers[0]?.avatar || 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=200&q=80',
    phase: 'Phase 1' as 'Phase 1' | 'Phase 2' | 'Phase 3',
    sector: 'Sector 70-D',
    duration: '3:30'
  });

  const phases = ['All', 'Phase 1', 'Phase 2', 'Phase 3'];

  // Fake-footage policy: a tour without a real video is hidden from the
  // gallery — sample/placeholder URLs must never render as if they were a
  // real on-ground tour. Real videos go in frontend/public/ (served at the
  // site root) or as absolute URLs; posters/thumbnails likewise.
  const isRealVideo = (v: AgencyVideo) => {
    const url = (v.videoUrl || '').toLowerCase();
    return url.startsWith('http') || (url.startsWith('/') && !url.includes('sample-video'));
  };

  const filteredVideos = agencyVideos.filter((v) => {
    if (!isRealVideo(v)) return false;
    if (selectedPhase === 'All') return true;
    return v.phase === selectedPhase;
  });

  const handlePostComment = (videoId: string, e: React.FormEvent) => {
    e.preventDefault();
    if (!commentAuthor.trim() || !commentText.trim()) {
      showToast('error', 'Please enter your name and comment.');
      return;
    }
    addAgencyVideoComment(videoId, commentAuthor, commentText);
    setCommentText('');
  };

  const handleShareVideo = (video: AgencyVideo) => {
    if (navigator.clipboard) {
      navigator.clipboard.writeText(window.location.href);
      showToast('success', `Direct link for "${video.title}" copied to clipboard!`);
    } else {
      showToast('info', 'Link ready to share!');
    }
  };

  const handleCreateVideo = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newVideo.title.trim() || !newVideo.description.trim()) {
      showToast('error', 'Please fill in the title and description.');
      return;
    }

    const selectedDealer = dealers.find((d) => d.id === newVideo.agencyId) || dealers[0];

    const result = await addAgencyVideo({
      title: newVideo.title,
      description: newVideo.description,
      videoUrl: newVideo.videoUrl,
      thumbnailUrl: newVideo.thumbnailUrl,
      agencyId: selectedDealer.id,
      agencyName: selectedDealer.agencyOrBranch || selectedDealer.agencyAffiliation || 'TaisarTownHub Partner Agency',
      dealerName: selectedDealer.fullName,
      dealerAvatar: selectedDealer.avatar || 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=200&q=80',
      phase: newVideo.phase,
      sector: newVideo.sector,
      duration: newVideo.duration,
      uploadedAt: 'Just now',
      tags: [newVideo.phase, newVideo.sector, 'On-Ground Tour']
    });

    if (result.ok) {
      setIsAddModalOpen(false);
      setNewVideo({
        title: '',
        description: '',
        videoUrl: '/sample-video.mp4',
        thumbnailUrl: 'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=1000&q=80',
        agencyId: dealers[0]?.id || 'deal-1',
        agencyName: dealers[0]?.agencyOrBranch || 'TaisarTownHub Premier Advisory',
        dealerName: dealers[0]?.fullName || 'Saleem Siddiqui',
        dealerAvatar: dealers[0]?.avatar || 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=200&q=80',
        phase: 'Phase 1',
        sector: 'Sector 70-D',
        duration: '3:30'
      });
    }
    // On failure the modal stays open with entered values intact and no
    // false success is shown to the user.
  };

  return (
    <section className="py-20 px-4 sm:px-6 lg:px-8 bg-[#07141E] border-t border-[#E7C878]/15">
      <div className="max-w-7xl mx-auto space-y-12">
        {/* Section Header */}
        <div className="flex flex-col md:flex-row md:items-end justify-between gap-6 pb-6 border-b border-slate-800">
          <div className="max-w-3xl space-y-3">
            <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-[#E7C878]/15 text-[#E7C878] border border-[#E7C878]/30">
              <Video className="w-3.5 h-3.5" /> Agency Video Tours & Reels
            </span>
            <h2 className="font-serif-luxury text-3xl sm:text-4xl font-bold text-white">
              On-Ground Scheme 45 Video Walkthroughs
            </h2>
            <p className="text-slate-400 text-sm leading-relaxed">
              Watch verified partner agencies inspect street demarcation, possession plots, road development, and biometric file clearances. Like and comment directly on any tour.
            </p>
          </div>

          {canPublishTour && (
          <div className="flex flex-wrap items-center gap-3">
            <button
              type="button"
              onClick={() => setIsAddModalOpen(true)}
              className="px-5 py-2.5 rounded-xl bg-[#E7C878] hover:bg-[#F3C464] text-[#0B1220] font-bold text-xs transition shadow-lg shadow-[#E7C878]/20 flex items-center gap-2 cursor-pointer"
            >
              <PlusCircle className="w-4 h-4" />
              <span>Add Agency Video Tour</span>
            </button>
          </div>
          )}
        </div>

        {/* Phase Filter Tabs */}
        <div className="flex flex-wrap items-center gap-2 pb-2">
          {phases.map((phase) => {
            const isActive = selectedPhase === phase;
            return (
              <button
                key={phase}
                onClick={() => setSelectedPhase(phase)}
                className={`px-5 py-2 rounded-xl text-xs font-semibold transition cursor-pointer flex items-center gap-2 ${
                  isActive
                    ? 'bg-[#E7C878] text-[#0B1220] shadow-md shadow-[#E7C878]/25 font-bold'
                    : 'bg-[#0B1220] text-slate-300 hover:bg-slate-800 border border-slate-800'
                }`}
              >
                <Compass className="w-3.5 h-3.5" />
                <span>{phase === 'All' ? 'All Video Tours' : phase}</span>
              </button>
            );
          })}
        </div>

        {/* Video Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          {filteredVideos.map((video) => {
            const isCommentsOpen = activeCommentsVideoId === video.id;

            return (
              <div
                key={video.id}
                className="bg-[#0B1220] rounded-2xl overflow-hidden border border-slate-800 hover:border-[#E7C878]/50 transition-all duration-300 shadow-xl flex flex-col justify-between group"
              >
                <div>
                  {/* Thumbnail / Video Preview Surface */}
                  <div
                    onClick={() => setActiveVideo({ url: video.videoUrl, title: video.title })}
                    className="relative aspect-video bg-black cursor-pointer overflow-hidden group/thumb"
                  >
                    <img
                      src={video.thumbnailUrl}
                      alt={video.title}
                      className="w-full h-full object-cover group-hover/thumb:scale-105 transition-transform duration-500 opacity-90 group-hover/thumb:opacity-100"
                    />

                    {/* Gradient Overlay */}
                    <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent flex items-center justify-center">
                      <div className="w-14 h-14 rounded-full bg-[#E7C878] text-[#0B1220] flex items-center justify-center shadow-xl group-hover/thumb:scale-110 transition-transform">
                        <Play className="w-6 h-6 fill-current ml-0.5" />
                      </div>
                    </div>

                    {/* Top Badges */}
                    <div className="absolute top-3 left-3 flex items-center gap-2">
                      <span className="px-2.5 py-1 rounded-full text-[10px] font-bold bg-black/80 text-[#F6E3B4] border border-[#E7C878]/40 backdrop-blur-md">
                        {video.phase}
                      </span>
                      <span className="px-2 py-0.5 rounded text-[10px] font-medium bg-[#0B1220]/90 text-slate-300 border border-slate-700 backdrop-blur-md">
                        {video.sector}
                      </span>
                    </div>

                    {/* Duration badge */}
                    <div className="absolute bottom-3 right-3 px-2 py-0.5 rounded bg-black/85 text-white text-[11px] font-mono flex items-center gap-1 backdrop-blur-md">
                      <Clock className="w-3 h-3 text-[#E7C878]" />
                      <span>{video.duration}</span>
                    </div>
                  </div>

                  {/* Agency Attribution Row */}
                  <div className="p-5 pb-3 space-y-3">
                    <div className="flex items-center gap-3">
                      <img
                        src={video.dealerAvatar}
                        alt={video.dealerName}
                        className="w-10 h-10 rounded-xl object-cover border border-[#E7C878]/50 shrink-0"
                      />
                      <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-1.5">
                          <span className="font-bold text-white text-xs truncate group-hover:text-[#E7C878] transition-colors">
                            {video.agencyName}
                          </span>
                          <span className="text-emerald-400 shrink-0" title="MDA Licensed Partner">
                            <CheckCircle2 className="w-3.5 h-3.5" />
                          </span>
                        </div>
                        <div className="text-[11px] text-slate-400 flex items-center gap-2">
                          <span>{video.dealerName}</span>
                          <span>•</span>
                          <span>{video.uploadedAt}</span>
                        </div>
                      </div>
                    </div>

                    {/* Video Title & Description */}
                    <div className="space-y-1.5">
                      <h3
                        onClick={() => setActiveVideo({ url: video.videoUrl, title: video.title })}
                        className="font-serif-luxury font-bold text-white text-base hover:text-[#E7C878] transition-colors cursor-pointer line-clamp-2 leading-snug"
                      >
                        {video.title}
                      </h3>
                      <p className="text-xs text-slate-300 line-clamp-2 leading-relaxed font-light">
                        {video.description}
                      </p>
                    </div>
                  </div>
                </div>

                {/* Engagement Bar: Likes & Comments */}
                <div className="px-5 py-3.5 bg-[#07141E] border-t border-slate-800 space-y-3">
                  <div className="flex items-center justify-between text-xs text-slate-300">
                    <div className="flex items-center gap-4">
                      {/* Like Button */}
                      <button
                        onClick={() => likeAgencyVideo(video.id)}
                        className="flex items-center gap-1.5 py-1 px-2.5 rounded-lg bg-slate-800/80 hover:bg-slate-800 text-slate-200 hover:text-white transition cursor-pointer border border-slate-700/60 active:scale-95"
                      >
                        <Heart className="w-4 h-4 text-rose-400 fill-rose-500/30 hover:fill-rose-500 transition-colors" />
                        <span className="font-semibold text-[11px]">{video.likes} Likes</span>
                      </button>

                      {/* Comment Button (Toggles Drawer) */}
                      <button
                        onClick={() => setActiveCommentsVideoId(isCommentsOpen ? null : video.id)}
                        className={`flex items-center gap-1.5 py-1 px-2.5 rounded-lg transition cursor-pointer border ${
                          isCommentsOpen
                            ? 'bg-[#E7C878]/20 text-[#F6E3B4] border-[#E7C878]/40'
                            : 'bg-slate-800/80 hover:bg-slate-800 text-slate-200 border-slate-700/60'
                        }`}
                      >
                        <MessageSquare className="w-4 h-4 text-[#E7C878]" />
                        <span className="font-semibold text-[11px]">{video.comments.length} Comments</span>
                      </button>
                    </div>

                    {/* Share Button */}
                    <button
                      onClick={() => handleShareVideo(video)}
                      aria-label={`Share link for ${video.title}`}
                      className="p-1.5 rounded-lg bg-slate-800/60 hover:bg-slate-700 text-slate-400 hover:text-white transition cursor-pointer"
                      title="Share Video Link"
                    >
                      <Share2 className="w-3.5 h-3.5" />
                    </button>
                  </div>

                  {/* Expanded Interactive Comments Drawer */}
                  {isCommentsOpen && (
                    <div className="pt-3 border-t border-slate-800/80 space-y-3 animate-fadeIn">
                      {/* Comments List */}
                      <div className="max-h-52 overflow-y-auto space-y-2.5 pr-1">
                        {video.comments.length === 0 ? (
                          <p className="text-[11px] text-slate-500 italic text-center py-2">
                            No comments yet. Be the first to ask the agency a question!
                          </p>
                        ) : (
                          video.comments.map((comm) => (
                            <div
                              key={comm.id}
                              className={`p-2.5 rounded-xl text-xs space-y-1 ${
                                comm.isDealer
                                  ? 'bg-[#E7C878]/10 border border-[#E7C878]/30'
                                  : 'bg-slate-800/70 border border-slate-700/50'
                              }`}
                            >
                              <div className="flex items-center justify-between">
                                <span className={`font-bold text-[11px] ${comm.isDealer ? 'text-[#F6E3B4]' : 'text-white'}`}>
                                  {comm.userName} {comm.isDealer && '★ (Agency Response)'}
                                </span>
                                <span className="text-[10px] text-slate-400">{comm.createdAt}</span>
                              </div>
                              <p className="text-[11px] text-slate-300 leading-relaxed">
                                {comm.comment}
                              </p>
                            </div>
                          ))
                        )}
                      </div>

                      {/* Add Comment Input Form */}
                      <form onSubmit={(e) => handlePostComment(video.id, e)} className="space-y-2 pt-1">
                        <input
                          type="text"
                          value={commentAuthor}
                          onChange={(e) => setCommentAuthor(e.target.value)}
                          placeholder="Your Name (e.g. Asad Ali)"
                          className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-1.5 border border-slate-700 focus:border-[#E7C878] focus:outline-none"
                        />
                        <div className="flex gap-2">
                          <input
                            type="text"
                            value={commentText}
                            onChange={(e) => setCommentText(e.target.value)}
                            placeholder="Write a comment or ask a question..."
                            className="flex-1 min-w-0 bg-[#0B1220] text-white text-xs rounded-lg px-3 py-1.5 border border-slate-700 focus:border-[#E7C878] focus:outline-none"
                          />
                          <button
                            type="submit"
                            className="px-3 py-1.5 rounded-lg bg-[#E7C878] hover:bg-[#F3C464] text-[#0B1220] font-bold text-xs transition cursor-pointer flex items-center gap-1 shrink-0"
                          >
                            <Send className="w-3.5 h-3.5" />
                            <span>Post</span>
                          </button>
                        </div>
                      </form>
                    </div>
                  )}
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

        {/* Modal: Add Agency Video Walkthrough */}
        {isAddModalOpen && typeof document !== 'undefined' && createPortal(
          <div
            className="fixed inset-0 z-50 bg-black/85 backdrop-blur-sm flex items-center justify-center p-4 animate-fadeIn"
            onMouseDown={(e) => {
              if (e.target === e.currentTarget) setIsAddModalOpen(false);
            }}
          >
            <div className="bg-[#0B1220] border border-[#E7C878]/40 rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl animate-scaleUp">
              <div className="p-5 border-b border-slate-800 flex items-center justify-between bg-[#07141E]">
                <div className="flex items-center gap-2">
                  <Video className="w-5 h-5 text-[#E7C878]" />
                  <h3 className="font-serif-luxury font-bold text-lg text-white">
                    Publish Agency Video Walkthrough
                  </h3>
                </div>
                <button
                  onClick={() => setIsAddModalOpen(false)}
                  className="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition cursor-pointer"
                >
                  <X className="w-5 h-5" />
                </button>
              </div>

              <form onSubmit={handleCreateVideo} className="p-6 space-y-4">
                <div>
                  <label className="block text-xs font-semibold text-slate-300 mb-1">
                    Select Agency Partner *
                  </label>
                  <select
                    value={newVideo.agencyId}
                    onChange={(e) => setNewVideo({ ...newVideo, agencyId: e.target.value })}
                    className="w-full bg-[#07141E] border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:border-[#E7C878] focus:outline-none"
                  >
                    {(dealers || []).map((d) => (
                      <option key={d.id} value={d.id}>
                        {d.agencyOrBranch || d.agencyAffiliation || 'TaisarTownHub Advisory'} — {d.fullName}
                      </option>
                    ))}
                  </select>
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-xs font-semibold text-slate-300 mb-1">
                      Scheme 45 Phase *
                    </label>
                    <select
                      value={newVideo.phase}
                      onChange={(e) => setNewVideo({ ...newVideo, phase: e.target.value as any })}
                      className="w-full bg-[#07141E] border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:border-[#E7C878] focus:outline-none"
                    >
                      <option value="Phase 1">Phase 1</option>
                      <option value="Phase 2">Phase 2</option>
                      <option value="Phase 3">Phase 3</option>
                    </select>
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-slate-300 mb-1">
                      Sector *
                    </label>
                    <input
                      type="text"
                      value={newVideo.sector}
                      onChange={(e) => setNewVideo({ ...newVideo, sector: e.target.value })}
                      placeholder="e.g. Sector 70-D, Block A"
                      className="w-full bg-[#07141E] border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:border-[#E7C878] focus:outline-none"
                      required
                    />
                  </div>
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-300 mb-1">
                    Video Title *
                  </label>
                  <input
                    type="text"
                    value={newVideo.title}
                    onChange={(e) => setNewVideo({ ...newVideo, title: e.target.value })}
                    placeholder="e.g. 120 Sq Yd On-Ground Demarcation & Street Overview"
                    className="w-full bg-[#07141E] border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:border-[#E7C878] focus:outline-none"
                    required
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-300 mb-1">
                    Description & Ground Insights *
                  </label>
                  <textarea
                    value={newVideo.description}
                    onChange={(e) => setNewVideo({ ...newVideo, description: e.target.value })}
                    rows={3}
                    placeholder="Share on-ground details, street width, water/electricity status, and possession clearance."
                    className="w-full bg-[#07141E] border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:border-[#E7C878] focus:outline-none"
                    required
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-300 mb-1">
                    Video Stream / MP4 URL *
                  </label>
                  <input
                    type="url"
                    value={newVideo.videoUrl}
                    onChange={(e) => setNewVideo({ ...newVideo, videoUrl: e.target.value })}
                    placeholder="https://..."
                    className="w-full bg-[#07141E] border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:border-[#E7C878] focus:outline-none font-mono"
                    required
                  />
                </div>

                <div className="pt-3 border-t border-slate-800 flex items-center justify-end gap-3">
                  <button
                    type="button"
                    onClick={() => setIsAddModalOpen(false)}
                    className="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-medium cursor-pointer"
                  >
                    Cancel
                  </button>
                  <button
                    type="submit"
                    className="px-5 py-2 rounded-xl bg-[#E7C878] hover:bg-[#F3C464] text-[#0B1220] font-bold text-xs transition cursor-pointer shadow-md shadow-[#E7C878]/20"
                  >
                    Publish Video Tour
                  </button>
                </div>
              </form>
            </div>
          </div>,
          document.body
        )}
      </div>
    </section>
  );
};
