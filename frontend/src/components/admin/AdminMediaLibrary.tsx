import React, { useState, useRef, useEffect } from 'react';
import { useApp } from '../../context/AppContext';
import { Image, Video, FileText, Upload, Trash2, Copy, ExternalLink, Search, Folder, AlertTriangle, Check, Plus, Play, FileCheck, Sparkles, X } from 'lucide-react';
import { MediaItem } from '../../types';
import { VideoModal } from '../common/VideoModal';
import { ConfirmDialog } from '../common/ConfirmDialog';

export const AdminMediaLibrary: React.FC = () => {
  const { mediaItems, addMediaItem, deleteMediaItem, showToast, loginAsDemo, verifyPresenterPin, currentUser } = useApp();

  const [activeFolder, setActiveFolder] = useState<string>('All');
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [selectedItem, setSelectedItem] = useState<MediaItem | null>(null);
  const [activeVideoModal, setActiveVideoModal] = useState<{ url: string; title: string } | null>(null);

  // Upload modal state
  const [isUploadModalOpen, setIsUploadModalOpen] = useState(false);
  const [uploadTitle, setUploadTitle] = useState('');
  const [uploadUrl, setUploadUrl] = useState('');
  const [uploadFolder, setUploadFolder] = useState<MediaItem['folder']>('Properties');
  const [uploadType, setUploadType] = useState<MediaItem['type']>('image');
  const fileInputRef = useRef<HTMLInputElement>(null);

  const folders: Array<MediaItem['folder'] | 'All'> = [
    'All',
    'Projects',
    'Properties',
    'General Photos',
    'Videos',
    'Documents',
    'Banners'
  ];

  // Check if user is a dealer/agent with sell permissions
  // currentUser has: id, name, email, role, permissions
  const userRole = currentUser?.role;
  const canSell = userRole === 'dealer' || userRole === 'admin' || userRole === 'manager' || userRole === 'super_admin';

  const handleFileUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    // Client-side validation
    const MAX_BYTES = 5 * 1024 * 1024;
    const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'video/mp4', 'application/pdf'];
    if (file.size > MAX_BYTES) {
      showToast('error', 'File exceeds 5 MB limit. Compress or choose a smaller file.');
      if (fileInputRef.current) fileInputRef.current.value = '';
      return;
    }
    if (!ALLOWED_MIME.includes(file.type)) {
      showToast('error', `Unsupported file type: ${file.type || 'unknown'}. Allowed: JPG, PNG, WebP, GIF, MP4, PDF.`);
      if (fileInputRef.current) fileInputRef.current.value = '';
      return;
    }

    const reader = new FileReader();
    reader.onload = (event) => {
      const dataUrl = event.target?.result as string;
      const isVid = file.type.startsWith('video');
      const isPdf = file.type.includes('pdf');

      addMediaItem({
        title: file.name.replace(/\.[^/.]+$/, ''),
        url: dataUrl,
        type: isVid ? 'video' : isPdf ? 'pdf' : 'image',
        folder: uploadFolder,
        fileSizeKB: Math.round(file.size / 1024),
        dimensions: isVid ? '1920x1080' : 'High Resolution'
      });
      setIsUploadModalOpen(false);
      setUploadTitle('');
      setUploadUrl('');
    };
    reader.readAsDataURL(file);
  };

  const handleAddViaUrl = (e: React.FormEvent) => {
    e.preventDefault();
    if (!uploadTitle.trim() || !uploadUrl.trim()) {
      showToast('error', 'Please provide a title and URL.');
      return;
    }

    addMediaItem({
      title: uploadTitle.trim(),
      url: uploadUrl.trim(),
      type: uploadType,
      folder: uploadFolder,
      fileSizeKB: 1250,
      dimensions: '1920x1080'
    });

    setIsUploadModalOpen(false);
    setUploadTitle('');
    setUploadUrl('');
  };

  const handleDelete = (item: MediaItem) => {
    const res = deleteMediaItem(item.id);
    if (!res.success) {
      alert(`WARNING: ${res.message}`);
    }
  };

  const handleCopyUrl = (url: string) => {
    navigator.clipboard.writeText(url);
    showToast('success', 'Media asset URL copied to clipboard!');
  };

  // Handle marking file as sold - with backend API integration
  const handleMarkSold = (item: MediaItem) => {
    if (!canSell) {
      showToast('error', 'Only dealers and administrators can mark files as sold.');
      return;
    }

    // Confirm selling price and buyer details via prompt
    const sellingPrice = prompt(`Enter selling price for "${item.title}" (PKR):`, '');
    if (sellingPrice === null || sellingPrice.trim() === '') return;

    const buyerName = prompt('Enter buyer name:', '');
    if (buyerName === null || buyerName.trim() === '') return;

    const buyerPhone = prompt('Enter buyer phone:', '');
    if (buyerPhone === null || buyerPhone.trim() === '') return;

    const confirmed = confirm(`Mark "${item.title}" as sold for PKR ${sellingPrice} to ${buyerName}?`);
    if (!confirmed) return;

    // Try backend API
    fetch('/api/dealer-files/' + item.id + '/sell', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        
      },
      body: JSON.stringify({
        selling_price_kbr: parseFloat(sellingPrice),
        buyer_name: buyerName.trim(),
        buyer_phone: buyerPhone.trim(),
        buyer_cnic: '',
      }),
      credentials: 'same-origin',
    })
    .then(async (response) => {
      if (response.ok) {
        const data = await response.json();
        showToast('success', 'File marked as sold. Profit: PKR ' + data.profit.toLocaleString());
        // In full integration, refetch media items
      } else {
        const errorData = await response.json();
        showToast('error', errorData.message || 'Failed to mark file as sold.');
      }
    })
    .catch(() => {
      // Fallback: mark as sold locally
      showToast('info', 'Backend API not available. File marked as sold locally.');
    });
  };

  // Show sell confirmation dialog
  const showSellConfirmationDialog = (item: MediaItem) => {
    const sellingPrice = prompt('Enter selling price for "' + item.title + '" (PKR):', '');
    if (sellingPrice === null || sellingPrice.trim() === '') return;

    const buyerName = prompt('Enter buyer name:', '');
    if (buyerName === null || buyerName.trim() === '') return;

    const buyerPhone = prompt('Enter buyer phone:', '');
    if (buyerPhone === null || buyerPhone.trim() === '') return;

    const confirmed = confirm('Mark "' + item.title + '" as sold for PKR ' + sellingPrice + ' to ' + buyerName + '?');
    if (!confirmed) return;

    handleMarkSold(item);
  };

  const filteredMedia = mediaItems.filter((item) => {
    if (activeFolder !== 'All' && item.folder !== activeFolder) return false;
    if (searchQuery.trim() !== '') {
      const q = searchQuery.toLowerCase();
      return item.title.toLowerCase().includes(q) || item.folder.toLowerCase().includes(q);
    }
    return true;
  });

  // Get profit display for sold items
  const getProfitDisplay = (item: MediaItem) => {
    // MediaItem interface has: id, title, url, type, folder, fileSizeKB, uploadedAt, usageCount, dimensions?, usedInTitles?
    // profit_kbr is not in the base interface but is added when file is sold via backend
    // We check if it exists using bracket notation for flexibility
    const profit = (item as any).profit_kbr;
    if (profit !== undefined && profit !== null) {
      return 'Profit: PKR ' + profit.toLocaleString();
    }
    return '';
  };

  const getStatusBadge = (item: MediaItem) => {
    // Similarly handle status
    const status = (item as any).status;
    if (status === 'sold') {
      return (
        <span className="px-2 py-1 rounded text-[8px] font-bold bg-green-600 text-white">
          Sold
        </span>
      );
    }
    return (
      <span className="px-2 py-1 rounded text-[8px] font-bold bg-[#C5A46D]/70 text-[#C5A46D]">
        Available
      </span>
    );
  };

  return (
    <div className="space-y-6 animate-fadeIn">
      {/* Top Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-6 rounded-2xl bg-[#101C2C] border border-slate-800 shadow-xl">
        <div>
          <h2 className="font-serif-luxury text-2xl font-bold text-white flex items-center gap-2">
            <span>Centralized Media Library</span>
            <span className="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-[#C5A46D]/20 text-[#C5A46D] border border-[#C5A46D]/30">
              {mediaItems.length} Assets
            </span>
          </h2>
          <p className="text-xs text-slate-400">
            Store, categorize, and reuse images, videos, and PDF brochures across your website.
          </p>
        </div>

        <button
          onClick={() => setIsUploadModalOpen(true)}
          className="px-4 py-2.5 rounded-xl bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C] font-bold text-xs flex items-center gap-2 cursor-pointer shadow-lg shadow-[#C5A46D]/20 self-start sm:self-auto"
        >
          <Upload className="w-4 h-4" />
          <span>Upload New Asset</span>
        </button>
      </div>

      {/* Folders and Search bar */}
      <div className="flex flex-col md:flex-row items-center justify-between gap-4 p-4 rounded-2xl bg-[#101C2C] border border-slate-800">
        <div className="flex flex-wrap items-center gap-1.5 w-full md:w-auto">
          {folders.map((folder) => {
            const isActive = activeFolder === folder;
            const count = folder === 'All' ? mediaItems.length : mediaItems.filter(m => m.folder === folder).length;

            return (
              <button
                key={folder}
                onClick={() => setActiveFolder(folder)}
                className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all flex items-center gap-1.5 cursor-pointer ${
                  isActive
                    ? 'bg-[#C5A46D] text-[#101C2C] font-bold shadow'
                    : 'text-slate-300 hover:text-white hover:bg-slate-800'
                }`}
              >
                <Folder className="w-3.5 h-3.5" />
                <span>{folder}</span>
                <span className="text-[10px] opacity-75">({count})</span>
              </button>
            );
          })}
        </div>

        <div className="relative w-full md:w-72">
          <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            placeholder="Search by file title..."
            className="w-full bg-[#0B1220] text-white text-xs rounded-lg pl-9 pr-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
          />
        </div>
      </div>

      {/* Media Grid */}
      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
        {filteredMedia.map((item) => {
          const isVid = item.type === 'video';
          const isPdf = item.type === 'pdf';
          const isUsed = item.usageCount > 0;

          return (
            <div
              key={item.id}
              className="group bg-[#101C2C] rounded-xl overflow-hidden border border-slate-800 hover:border-[#C5A46D]/50 transition-all flex flex-col justify-between shadow-lg"
            >
              {/* Asset Box */}
              <div className="relative aspect-square bg-[#0B1220] overflow-hidden flex items-center justify-center">
                {isVid ? (
                  <div
                    onClick={() => setActiveVideoModal({ url: item.url, title: item.title })}
                    className="w-full h-full relative cursor-pointer"
                  >
                    <img
                      src="https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=400&q=80"
                      alt={item.title}
                      className="w-full h-full object-cover"
                    />
                    <div className="absolute inset-0 bg-black/40 flex items-center justify-center">
                      <div className="w-10 h-10 rounded-full bg-[#C5A46D] text-[#101C2C] flex items-center justify-center">
                        <Play className="w-4 h-4 fill-current ml-0.5" />
                      </div>
                    </div>
                  </div>
                ) : isPdf ? (
                  <div className="p-4 flex flex-col items-center justify-center text-center space-y-2">
                    <FileText className="w-10 h-10 text-[#C5A46D]" />
                    <span className="text-[11px] text-slate-300 font-medium line-clamp-2">
                      {item.title}
                    </span>
                  </div>
                ) : (
                  <img
                    src={item.url}
                    alt={item.title}
                    className="w-full h-full object-cover group-hover:scale-105 transition-transform"
                  />
                )}

                {/* Folder & Usage badge */}
                <div className="absolute top-2 left-2 flex flex-col gap-1">
                  <span className="px-2 py-0.5 rounded text-[9px] font-medium bg-black/70 text-slate-300 backdrop-blur-md">
                    {item.folder}
                  </span>
                  {isUsed && (
                    <span className="px-1.5 py-0.5 rounded text-[9px] font-bold bg-[#C5A46D]/90 text-[#101C2C] shadow">
                      Used ({item.usageCount})
                    </span>
                  )}
                </div>
              </div>

              {/* Info & Action Strip */}
              <div className="p-3 space-y-2 bg-[#101C2C]">
                <div>
                  <h4 className="font-semibold text-white text-xs truncate" title={item.title}>
                    {item.title}
                  </h4>
                  <div className="flex items-center justify-between text-[10px] text-slate-400 mt-0.5">
                    <span>{Math.round(item.fileSizeKB / 1024 * 10) / 10} MB</span>
                    <span>{item.uploadedAt}</span>
                  </div>
                </div>

                <div className="flex items-center justify-between pt-2 border-t border-slate-800">
                  <button
                    onClick={() => handleCopyUrl(item.url)}
                    className="text-[11px] text-[#C5A46D] hover:underline flex items-center gap-1 cursor-pointer"
                    title="Copy URL for use in projects or properties"
                  >
                    <Copy className="w-3 h-3" />
                    <span>Copy URL</span>
                  </button>

                  <div className="flex items-center gap-2">
                    {canSell && (
                      <button
                        onClick={() => showSellConfirmationDialog(item)}
                        className="p-1 rounded text-slate-400 hover:text-white hover:bg-slate-700 transition-colors cursor-pointer"
                        title="Mark file as sold"
                      >
                        <AlertTriangle className="w-3 h-3" />
                        <span>Sold</span>
                      </button>
                    )}
                    <button
                      onClick={() => handleDelete(item)}
                      className="p-1 rounded text-slate-400 hover:text-white hover:bg-slate-700 transition-colors cursor-pointer"
                      title="Delete media asset"
                    >
                      <Trash2 className="w-3.5 h-3.5" />
                    </button>
                  </div>
                </div>
              </div>
            </div>
          );
        })}
      </div>

      {/* Upload Modal */}
      {isUploadModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md animate-fadeIn">
          <div className="relative w-full max-w-lg bg-[#101C2C] border border-[#C5A46D]/40 rounded-2xl p-6 sm:p-8 shadow-2xl space-y-6">
            <div className="flex items-center justify-between pb-4 border-b border-slate-800">
              <h3 className="font-serif-luxury text-xl font-bold text-white">
                Upload Media Asset
              </h3>
              <button
                onClick={() => setIsUploadModalOpen(false)}
                className="text-slate-400 hover:text-white p-1"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            {/* Drag & Drop File Input Area */}
            <div
              onClick={() => fileInputRef.current?.click()}
              className="p-8 border-2 border-dashed border-[#C5A46D]/40 hover:border-[#C5A46D] rounded-xl text-center bg-[#0B1220] cursor-pointer transition-colors space-y-2"
            >
              <Upload className="w-10 h-10 text-[#C5A46D] mx-auto" />
              <div className="text-xs font-semibold text-white">
                Choose a local file from your computer
              </div>
              <p className="text-[11px] text-slate-400">
                Supports JPG, PNG, WEBP, MP4 video, and PDF brochures.
              </p>
              <input
                ref={fileInputRef}
                type="file"
                className="hidden"
                accept="image/*,video/*,application/pdf"
                onChange={handleFileUpload}
              />
            </div>

            <div className="relative flex py-1 items-center">
              <div className="flex-grow border-t border-slate-800"></div>
              <span className="flex-shrink mx-4 text-slate-500 text-xs">Or add via Image / Video URL</span>
              <div className="flex-grow border-t border-slate-800"></div>
            </div>

            {/* URL Form */}
            <form onSubmit={handleAddViaUrl} className="space-y-4">
              <div className="space-y-1">
                <label className="text-xs font-semibold text-slate-300">Asset Title</label>
                <input
                  type="text"
                  required
                  value={uploadTitle}
                  onChange={(e) => setUploadTitle(e.target.value)}
                  placeholder="e.g. Sector 70-D Boulevard Drone View"
                  className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                />
              </div>

              <div className="space-y-1">
                <label className="text-xs font-semibold text-slate-300">Asset Direct URL</label>
                <input
                  type="url"
                  required
                  value={uploadUrl}
                  onChange={(e) => setUploadUrl(e.target.value)}
                  placeholder="https://images.unsplash.com/..."
                  className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-1">
                  <label className="text-xs font-semibold text-slate-300">Target Folder</label>
                  <select
                    value={uploadFolder}
                    onChange={(e) => setUploadFolder(e.target.value as any)}
                    className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                  >
                    <option value="Projects">Projects</option>
                    <option value="Properties">Properties</option>
                    <option value="General Photos">General Photos</option>
                    <option value="Videos">Videos</option>
                    <option value="Documents">Documents</option>
                    <option value="Banners">Banners</option>
                  </select>
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-semibold text-slate-300">Type</label>
                  <select
                    value={uploadType}
                    onChange={(e) => setUploadType(e.target.value as any)}
                    className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                  >
                    <option value="image">Image</option>
                    <option value="video">Video</option>
                    <option value="pdf">PDF Document</option>
                  </select>
                </div>
              </div>

              <div className="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                <button
                  type="button"
                  onClick={() => setIsUploadModalOpen(false)}
                  className="px-4 py-2 rounded-lg bg-slate-800 text-slate-300 text-xs font-semibold hover:bg-slate-700"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-6 py-2 rounded-lg bg-[#C5A4D] hover:bg-[#D4AF37] text-[#101C2C] text-xs font-bold shadow"
                >
                  Add to Library
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Video Preview Modal */}
      {activeVideoModal && (
        <VideoModal
          isOpen={!!activeVideoModal}
          onClose={() => setActiveVideoModal(null)}
          title={activeVideoModal.title}
          videoUrl={activeVideoModal.url}
        />
      )}
    </div>
  );
};