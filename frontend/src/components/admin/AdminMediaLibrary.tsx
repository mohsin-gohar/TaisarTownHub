import React, { useState, useRef, useEffect } from 'react';
import { useApp } from '../../context/AppContext';
import { Image, Video, FileText, Upload, Trash2, Copy, ExternalLink, Search, Folder, Check, Plus, Play, X, Tag, BadgeCheck, Wallet, UserRound } from 'lucide-react';
import { MediaItem } from '../../types';
import { VideoModal } from '../common/VideoModal';
import { summarizeMediaSales } from '../../utils/logic';

export const AdminMediaLibrary: React.FC = () => {
  const { mediaItems, addMediaItem, deleteMediaItem, markMediaSold, showToast, currentUser } = useApp();

  const [activeFolder, setActiveFolder] = useState<string>('All');
  const [statusFilter, setStatusFilter] = useState<'all' | 'available' | 'sold'>('all');
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

  // Sell modal state (sale attribution + profit tracking)
  const [sellTarget, setSellTarget] = useState<MediaItem | null>(null);
  const [sellPrice, setSellPrice] = useState('');
  const [sellCost, setSellCost] = useState('');
  const [buyerName, setBuyerName] = useState('');
  const [buyerPhone, setBuyerPhone] = useState('');
  const [buyerCnic, setBuyerCnic] = useState('');

  // Permission to record sales (RBAC: admin & super_admin manage the library)
  const canSell = Array.isArray(currentUser?.permissions)
    ? currentUser.permissions.some((p) => p === 'all' || p === 'manage_media')
    : ['super_admin', 'admin'].includes(currentUser?.role ?? '');

  const folders: Array<MediaItem['folder'] | 'All'> = [
    'All',
    'Projects',
    'Properties',
    'General Photos',
    'Videos',
    'Documents',
    'Banners'
  ];

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
        title: uploadTitle.trim() || file.name.replace(/\.[^/.]+$/, ''),
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
    deleteMediaItem(item.id);
  };

  const handleCopyUrl = (url: string) => {
    navigator.clipboard.writeText(url);
    showToast('success', 'Media asset URL copied to clipboard!');
  };

  const openSellModal = (item: MediaItem) => {
    setSellTarget(item);
    setSellPrice('');
    setSellCost('');
    setBuyerName('');
    setBuyerPhone('');
    setBuyerCnic('');
  };

  const closeSellModal = () => setSellTarget(null);

  const handleSellSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!sellTarget) return;
    const result = markMediaSold(sellTarget.id, {
      soldPricePKR: Number(sellPrice),
      basePricePKR: sellCost.trim() === '' ? undefined : Number(sellCost),
      buyerName,
      buyerPhone,
      buyerNationalId: buyerCnic.trim() || undefined,
    });
    if (result.success) closeSellModal();
  };

  const visibleMedia = mediaItems.filter((item) => !item.deletedAt);

  const filteredMedia = visibleMedia.filter((item) => {
    if (activeFolder !== 'All' && item.folder !== activeFolder) return false;
    if (statusFilter === 'sold' && !item.soldAt) return false;
    if (statusFilter === 'available' && item.soldAt) return false;
    if (searchQuery.trim() !== '') {
      const q = searchQuery.toLowerCase();
      return item.title.toLowerCase().includes(q) || item.folder.toLowerCase().includes(q);
    }
    return true;
  });

  // Sale attribution summary (who sold what, files count, total profit)
  const salesSummary = summarizeMediaSales(visibleMedia);
  const totalProfit = salesSummary.reduce((sum, s) => sum + s.totalProfit, 0);
  const totalSoldCount = visibleMedia.filter((m) => m.soldAt).length;

  return (
    <div className="space-y-6 animate-fadeIn">
      {/* Top Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-6 rounded-2xl bg-[#101C2C] border border-slate-800 shadow-xl">
        <div>
          <h2 className="font-serif-luxury text-2xl font-bold text-white flex items-center gap-2">
            <span>Centralized Media Library</span>
            <span className="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-[#C5A46D]/20 text-[#C5A46D] border border-[#C5A46D]/30">
              {visibleMedia.length} Assets
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

      {/* Sale Attribution Report (who sold which file & profit earned) */}
      {(canSell || totalSoldCount > 0) && (
        <div className="p-5 rounded-2xl bg-[#101C2C] border border-slate-800 shadow-lg space-y-3">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <h3 className="text-sm font-bold text-white flex items-center gap-2">
              <Wallet className="w-4 h-4 text-[#C5A46D]" />
              <span>Sale Attribution &amp; Profit Report</span>
            </h3>
            <span className="text-[11px] text-slate-400">
              {totalSoldCount} file{totalSoldCount === 1 ? '' : 's'} sold &middot; Total profit{' '}
              <span className="text-emerald-400 font-bold">PKR {totalProfit.toLocaleString('en-PK')}</span>
            </span>
          </div>
          {salesSummary.length === 0 ? (
            <p className="text-[11px] text-slate-500">
              No sales recorded yet. Mark an asset as sold to attribute it to a team member with buyer details and profit.
            </p>
          ) : (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
              {salesSummary.map((s) => (
                <div key={s.seller} className="p-3 rounded-xl bg-[#0B1220] border border-slate-800 space-y-1">
                  <div className="flex items-center gap-2 text-xs font-semibold text-white">
                    <UserRound className="w-3.5 h-3.5 text-[#C5A46D]" />
                    <span className="truncate">{s.seller}</span>
                    <span className="text-[9px] px-1.5 py-0.5 rounded bg-slate-800 text-slate-300 uppercase">{s.role || 'staff'}</span>
                  </div>
                  <div className="flex items-center justify-between text-[10px] text-slate-400">
                    <span>{s.filesSold} file{s.filesSold === 1 ? '' : 's'} sold</span>
                    <span className="text-emerald-400 font-bold">Profit PKR {s.totalProfit.toLocaleString('en-PK')}</span>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {/* Folders and Search bar */}
      <div className="flex flex-col md:flex-row items-center justify-between gap-4 p-4 rounded-2xl bg-[#101C2C] border border-slate-800">
        <div className="flex flex-wrap items-center gap-1.5 w-full md:w-auto">
          {folders.map((folder) => {
            const isActive = activeFolder === folder;
            const count = folder === 'All' ? visibleMedia.length : visibleMedia.filter(m => m.folder === folder).length;

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

      {/* Status filter: All / Available / Sold */}
      <div className="flex items-center gap-1.5">
        <span className="text-[10px] uppercase tracking-wider text-slate-500 font-semibold mr-1">Status:</span>
        {(['all', 'available', 'sold'] as const).map((s) => (
          <button
            key={s}
            onClick={() => setStatusFilter(s)}
            className={`px-3 py-1 rounded-lg text-[11px] font-semibold capitalize transition-all cursor-pointer ${
              statusFilter === s
                ? 'bg-[#C5A46D] text-[#101C2C] font-bold'
                : 'bg-[#101C2C] text-slate-300 border border-slate-800 hover:text-white'
            }`}
          >
            {s}
          </button>
        ))}
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

                {/* Sold badge with profit */}
                {item.soldAt && (
                  <div className="absolute inset-0 bg-emerald-950/60 backdrop-blur-[2px] flex flex-col items-center justify-center gap-1 pointer-events-none">
                    <span className="flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-500 text-[#052e16] shadow-lg">
                      <BadgeCheck className="w-3.5 h-3.5" /> SOLD
                    </span>
                    <span className="text-[10px] font-bold text-emerald-300">
                      Profit PKR {(item.profitPKR ?? 0).toLocaleString('en-PK')}
                    </span>
                    <span className="text-[9px] text-emerald-200/80">by {item.soldBy}</span>
                  </div>
                )}
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
                    {canSell && !item.soldAt && (
                      <button
                        onClick={() => openSellModal(item)}
                        className="flex items-center gap-1 px-2 py-1 rounded-lg bg-emerald-600/20 text-emerald-300 border border-emerald-600/40 text-[10px] font-bold hover:bg-emerald-600/30 transition-colors cursor-pointer"
                        title="Mark file as sold with buyer details & profit"
                      >
                        <Tag className="w-3 h-3" />
                        <span>Sell</span>
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
                  className="px-6 py-2 rounded-lg bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C] text-xs font-bold shadow"
                >
                  Add to Library
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Sell / Sale Attribution Modal */}
      {sellTarget && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md animate-fadeIn"
          onClick={(e) => { if (e.target === e.currentTarget) closeSellModal(); }}
        >
          <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="sell-modal-title"
            className="relative w-full max-w-md bg-[#101C2C] border border-[#C5A46D]/40 rounded-2xl p-6 sm:p-8 shadow-2xl space-y-5"
          >
            <div className="flex items-center justify-between pb-4 border-b border-slate-800">
              <h3 id="sell-modal-title" className="font-serif-luxury text-lg font-bold text-white flex items-center gap-2">
                <Tag className="w-5 h-5 text-emerald-400" />
                <span>Mark as Sold</span>
              </h3>
              <button onClick={closeSellModal} aria-label="Close sell dialog" className="text-slate-400 hover:text-white p-1 cursor-pointer">
                <X className="w-5 h-5" />
              </button>
            </div>

            <p className="text-xs text-slate-400 -mt-2">
              <span className="text-white font-semibold">{sellTarget.title}</span> &mdash; the sale, buyer details and profit are
              recorded against your account ({currentUser?.name}) and persisted to MySQL.
            </p>

            <form onSubmit={handleSellSubmit} className="space-y-4">
              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1">
                  <label htmlFor="sell-price" className="text-xs font-semibold text-slate-300">Selling Price (PKR) *</label>
                  <input
                    id="sell-price"
                    type="number"
                    min="1"
                    required
                    value={sellPrice}
                    onChange={(e) => setSellPrice(e.target.value)}
                    placeholder="e.g. 3600000"
                    className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                  />
                </div>
                <div className="space-y-1">
                  <label htmlFor="sell-cost" className="text-xs font-semibold text-slate-300">Cost / Base (PKR)</label>
                  <input
                    id="sell-cost"
                    type="number"
                    min="0"
                    value={sellCost}
                    onChange={(e) => setSellCost(e.target.value)}
                    placeholder="Blank = 30% margin"
                    className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                  />
                </div>
              </div>

              <div className="space-y-1">
                <label htmlFor="buyer-name" className="text-xs font-semibold text-slate-300">Buyer Name *</label>
                <input
                  id="buyer-name"
                  type="text"
                  required
                  minLength={2}
                  value={buyerName}
                  onChange={(e) => setBuyerName(e.target.value)}
                  placeholder="e.g. Ahmed Raza"
                  className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1">
                  <label htmlFor="buyer-phone" className="text-xs font-semibold text-slate-300">Buyer Phone *</label>
                  <input
                    id="buyer-phone"
                    type="tel"
                    required
                    minLength={7}
                    value={buyerPhone}
                    onChange={(e) => setBuyerPhone(e.target.value)}
                    placeholder="+92 3XX XXXXXXX"
                    className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                  />
                </div>
                <div className="space-y-1">
                  <label htmlFor="buyer-cnic" className="text-xs font-semibold text-slate-300">Buyer CNIC</label>
                  <input
                    id="buyer-cnic"
                    type="text"
                    value={buyerCnic}
                    onChange={(e) => setBuyerCnic(e.target.value)}
                    placeholder="Stored masked"
                    className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                  />
                </div>
              </div>

              {sellPrice !== '' && Number(sellPrice) > 0 && (
                <div className="p-3 rounded-xl bg-emerald-950/40 border border-emerald-800/40 flex items-center justify-between">
                  <span className="text-[11px] text-emerald-300 font-semibold">Estimated profit</span>
                  <span className="text-sm font-bold text-emerald-400">
                    PKR {(Number(sellPrice) - (sellCost.trim() === '' ? Math.round(Number(sellPrice) * 0.7) : Number(sellCost || '0'))).toLocaleString('en-PK')}
                  </span>
                </div>
              )}

              <div className="flex items-center justify-end gap-3 pt-2 border-t border-slate-800">
                <button
                  type="button"
                  onClick={closeSellModal}
                  className="px-4 py-2 rounded-lg bg-slate-800 text-slate-300 text-xs font-semibold hover:bg-slate-700 cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow cursor-pointer"
                >
                  Record Sale
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