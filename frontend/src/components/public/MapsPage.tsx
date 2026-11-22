import React, { useState, useEffect, useCallback, useRef } from 'react';
import { Search, Filter, ZoomIn, ZoomOut, Maximize2, X, Download, MapPin, Loader2, ChevronLeft, ChevronRight } from 'lucide-react';
import { api } from '../../services/api';

interface MapItem {
  id: string;
  title: string;
  slug: string;
  sector: string | null;
  phase: string | null;
  category: string;
  category_label: string;
  description: string | null;
  image_url: string;
  image_width: number | null;
  image_height: number | null;
  is_featured: boolean;
  display_order: number;
}

const CATEGORIES = [
  { key: 'All', label: 'All Maps' },
  { key: 'overall_map', label: 'Overall Map' },
  { key: 'sector_map', label: 'Sector Maps' },
  { key: 'phase_map', label: 'Phase Maps' },
  { key: 'guide_map', label: 'Guide Maps' },
];

export const MapsPage: React.FC = () => {
  const [maps, setMaps] = useState<MapItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [search, setSearch] = useState('');
  const [category, setCategory] = useState('All');
  const [sector, setSector] = useState('All');
  const [lightboxOpen, setLightboxOpen] = useState(false);
  const [lightboxIndex, setLightboxIndex] = useState(0);
  const [zoom, setZoom] = useState(1);
  const [pan, setPan] = useState({ x: 0, y: 0 });
  const [isDragging, setIsDragging] = useState(false);
  const dragStart = useRef({ x: 0, y: 0 });
  const panStart = useRef({ x: 0, y: 0 });

  const fetchMaps = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await api.getMaps(category === 'All' ? undefined : category, sector === 'All' ? undefined : sector, search || undefined);
      if (data?.success && Array.isArray(data.maps)) {
        setMaps(data.maps);
      } else {
        setError('Unable to load maps. Please try again.');
      }
    } catch {
      setError('Unable to load maps. Please try again.');
    } finally {
      setLoading(false);
    }
  }, [category, sector, search]);

  useEffect(() => {
    fetchMaps();
  }, [fetchMaps]);

  const openLightbox = (index: number) => {
    setLightboxIndex(index);
    setZoom(1);
    setPan({ x: 0, y: 0 });
    setLightboxOpen(true);
  };

  const closeLightbox = () => {
    setLightboxOpen(false);
    setZoom(1);
    setPan({ x: 0, y: 0 });
  };

  const handleZoomIn = () => setZoom((z) => Math.min(z + 0.25, 4));
  const handleZoomOut = () => setZoom((z) => Math.max(z - 0.25, 0.5));
  const handleReset = () => { setZoom(1); setPan({ x: 0, y: 0 }); };

  const handleMouseDown = (e: React.MouseEvent) => {
    setIsDragging(true);
    dragStart.current = { x: e.clientX, y: e.clientY };
    panStart.current = { ...pan };
  };

  const handleMouseMove = (e: React.MouseEvent) => {
    if (!isDragging) return;
    setPan({
      x: panStart.current.x + (e.clientX - dragStart.current.x),
      y: panStart.current.y + (e.clientY - dragStart.current.y),
    });
  };

  const handleMouseUp = () => setIsDragging(false);

  const handleWheel = (e: React.WheelEvent) => {
    e.preventDefault();
    const delta = e.deltaY > 0 ? -0.1 : 0.1;
    setZoom((z) => Math.min(Math.max(z + delta, 0.5), 4));
  };

  const nextImage = () => {
    setLightboxIndex((i) => (i + 1) % maps.length);
    setZoom(1);
    setPan({ x: 0, y: 0 });
  };

  const prevImage = () => {
    setLightboxIndex((i) => (i - 1 + maps.length) % maps.length);
    setZoom(1);
    setPan({ x: 0, y: 0 });
  };

  const handleDownload = (map: MapItem) => {
    const a = document.createElement('a');
    a.href = map.image_url;
    a.download = `${map.title.replace(/[^a-zA-Z0-9]/g, '-')}.jpg`;
    a.target = '_blank';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  };

  const sectors = Array.from(new Set(maps.map((m) => m.sector).filter(Boolean))) as string[];

  const filteredMaps = maps.filter((m) => {
    const matchesSearch = !search || m.title.toLowerCase().includes(search.toLowerCase()) || (m.sector || '').toLowerCase().includes(search.toLowerCase());
    const matchesCategory = category === 'All' || m.category === category;
    const matchesSector = sector === 'All' || m.sector === sector;
    return matchesSearch && matchesCategory && matchesSector;
  });

  return (
    <div className="min-h-screen bg-[#07141E] text-white">
      {/* Hero */}
      <section className="py-16 sm:py-24 px-4 sm:px-6 lg:px-8 bg-gradient-to-b from-[#07141E] to-[#0a1a26]">
        <div className="max-w-7xl mx-auto text-center">
          <h1 className="font-serif-luxury text-4xl sm:text-5xl font-bold text-white mb-4">Taiser Town Maps</h1>
          <p className="text-slate-400 text-lg max-w-2xl mx-auto">
            Explore detailed sector maps, phase plans, and the overall Taiser Town layout. Click any map to zoom, pan, and view in full screen.
          </p>
        </div>
      </section>

      {/* Filters */}
      <section className="py-8 px-4 sm:px-6 lg:px-8 bg-[#07141E] border-b border-slate-800">
        <div className="max-w-7xl mx-auto flex flex-col sm:flex-row gap-4 items-center">
          <div className="relative flex-1 w-full sm:max-w-md">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
            <input
              type="text"
              placeholder="Search by title or sector..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-800/50 border border-slate-700 text-white text-sm placeholder-slate-500 focus:outline-none focus:border-[#E7C878]/50"
            />
          </div>
          <div className="flex gap-2 flex-wrap">
            {CATEGORIES.map((c) => (
              <button
                key={c.key}
                onClick={() => setCategory(c.key)}
                className={`px-3 py-2 rounded-lg text-xs font-medium transition-colors ${
                  category === c.key
                    ? 'bg-[#E7C878] text-[#07141E]'
                    : 'bg-slate-800/50 text-slate-300 hover:bg-slate-700'
                }`}
              >
                {c.label}
              </button>
            ))}
          </div>
          {sectors.length > 0 && (
            <select
              value={sector}
              onChange={(e) => setSector(e.target.value)}
              className="px-3 py-2 rounded-lg bg-slate-800/50 border border-slate-700 text-white text-xs focus:outline-none"
            >
              <option value="All">All Sectors</option>
              {sectors.map((s) => (
                <option key={s} value={s}>{s}</option>
              ))}
            </select>
          )}
        </div>
      </section>

      {/* Map Grid */}
      <section className="py-12 px-4 sm:px-6 lg:px-8 bg-[#07141E]">
        <div className="max-w-7xl mx-auto">
          {loading && (
            <div className="flex items-center justify-center py-20">
              <Loader2 className="w-8 h-8 text-[#E7C878] animate-spin" />
              <span className="ml-3 text-slate-400">Loading maps…</span>
            </div>
          )}

          {error && (
            <div className="text-center py-20">
              <p className="text-red-400 text-sm">{error}</p>
              <button onClick={fetchMaps} className="mt-3 text-[#E7C878] text-sm hover:underline">Try again</button>
            </div>
          )}

          {!loading && !error && filteredMaps.length === 0 && (
            <div className="text-center py-20">
              <MapPin className="w-12 h-12 text-slate-600 mx-auto mb-4" />
              <p className="text-slate-400 text-sm">No maps found matching your filters.</p>
              <p className="text-slate-500 text-xs mt-2">Try adjusting your search or category filter.</p>
            </div>
          )}

          {!loading && !error && filteredMaps.length > 0 && (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
              {filteredMaps.map((map, idx) => (
                <div
                  key={map.id}
                  className="group relative bg-[#0a1a26] border border-slate-800 rounded-xl overflow-hidden hover:border-[#E7C878]/30 transition-colors cursor-pointer"
                  onClick={() => openLightbox(idx)}
                >
                  {map.is_featured && (
                    <div className="absolute top-3 left-3 z-10 bg-[#E7C878] text-[#07141E] text-[10px] font-bold px-2 py-1 rounded">
                      FEATURED
                    </div>
                  )}
                  <div className="relative aspect-[4/3] overflow-hidden">
                    <img
                      src={map.image_url}
                      alt={map.title}
                      className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                      loading="lazy"
                      onError={(e) => {
                        (e.target as HTMLImageElement).src = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300"><rect fill="%230a1a26" width="400" height="300"/><text fill="%2364748b" x="50%" y="50%" text-anchor="middle" font-size="14">Image unavailable</text></svg>';
                      }}
                    />
                    <div className="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-colors flex items-center justify-center">
                      <Maximize2 className="w-8 h-8 text-white opacity-0 group-hover:opacity-100 transition-opacity" />
                    </div>
                  </div>
                  <div className="p-4">
                    <h3 className="font-serif-luxury text-lg font-bold text-white mb-1 group-hover:text-[#E7C878] transition-colors">
                      {map.title}
                    </h3>
                    <div className="flex items-center gap-2 mb-2 flex-wrap">
                      {map.sector && (
                        <span className="text-[10px] bg-slate-800 text-slate-300 px-2 py-0.5 rounded">
                          {map.sector}
                        </span>
                      )}
                      {map.phase && (
                        <span className="text-[10px] bg-slate-800 text-slate-300 px-2 py-0.5 rounded">
                          {map.phase}
                        </span>
                      )}
                      <span className="text-[10px] bg-[#E7C878]/10 text-[#E7C878] px-2 py-0.5 rounded">
                        {map.category_label}
                      </span>
                    </div>
                    {map.description && (
                      <p className="text-slate-400 text-xs line-clamp-2">{map.description}</p>
                    )}
                    {map.image_width && map.image_height && (
                      <p className="text-slate-500 text-[10px] mt-2">
                        {map.image_width} × {map.image_height}px
                      </p>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </section>

      {/* Lightbox */}
      {lightboxOpen && filteredMaps.length > 0 && (
        <div
          className="fixed inset-0 z-50 bg-black/95 flex items-center justify-center"
          onMouseMove={handleMouseMove}
          onMouseUp={handleMouseUp}
          onMouseLeave={handleMouseUp}
        >
          {/* Controls */}
          <div className="absolute top-4 left-4 right-4 flex items-center justify-between z-50">
            <div className="flex items-center gap-2">
              <button onClick={handleZoomOut} className="p-2 rounded-lg bg-white/10 hover:bg-white/20 text-white" aria-label="Zoom out">
                <ZoomOut className="w-5 h-5" />
              </button>
              <button onClick={handleZoomIn} className="p-2 rounded-lg bg-white/10 hover:bg-white/20 text-white" aria-label="Zoom in">
                <ZoomIn className="w-5 h-5" />
              </button>
              <button onClick={handleReset} className="p-2 rounded-lg bg-white/10 hover:bg-white/20 text-white text-xs" aria-label="Reset zoom">
                Reset
              </button>
              <span className="text-white/60 text-xs ml-2">{Math.round(zoom * 100)}%</span>
            </div>
            <div className="flex items-center gap-2">
              <button
                onClick={() => handleDownload(filteredMaps[lightboxIndex])}
                className="p-2 rounded-lg bg-white/10 hover:bg-white/20 text-white"
                aria-label="Download map"
              >
                <Download className="w-5 h-5" />
              </button>
              <button onClick={closeLightbox} className="p-2 rounded-lg bg-white/10 hover:bg-white/20 text-white" aria-label="Close">
                <X className="w-5 h-5" />
              </button>
            </div>
          </div>

          {/* Navigation */}
          {filteredMaps.length > 1 && (
            <>
              <button
                onClick={prevImage}
                className="absolute left-4 top-1/2 -translate-y-1/2 z-50 p-3 rounded-full bg-white/10 hover:bg-white/20 text-white"
                aria-label="Previous map"
              >
                <ChevronLeft className="w-6 h-6" />
              </button>
              <button
                onClick={nextImage}
                className="absolute right-4 top-1/2 -translate-y-1/2 z-50 p-3 rounded-full bg-white/10 hover:bg-white/20 text-white"
                aria-label="Next map"
              >
                <ChevronRight className="w-6 h-6" />
              </button>
            </>
          )}

          {/* Image */}
          <div
            className="relative overflow-hidden cursor-grab active:cursor-grabbing"
            style={{ width: '90vw', height: '85vh' }}
            onWheel={handleWheel}
            onMouseDown={handleMouseDown}
          >
            <img
              src={filteredMaps[lightboxIndex].image_url}
              alt={filteredMaps[lightboxIndex].title}
              className="max-w-none select-none"
              style={{
                transform: `translate(${pan.x}px, ${pan.y}px) scale(${zoom})`,
                transformOrigin: 'center center',
                transition: isDragging ? 'none' : 'transform 0.1s ease-out',
              }}
              draggable={false}
            />
          </div>

          {/* Caption */}
          <div className="absolute bottom-4 left-1/2 -translate-x-1/2 z-50 bg-black/60 backdrop-blur px-4 py-2 rounded-lg text-center max-w-lg">
            <p className="text-white text-sm font-medium">{filteredMaps[lightboxIndex].title}</p>
            {filteredMaps[lightboxIndex].sector && (
              <p className="text-slate-400 text-xs">{filteredMaps[lightboxIndex].sector}</p>
            )}
            <p className="text-slate-500 text-[10px] mt-1">
              {lightboxIndex + 1} of {filteredMaps.length} · Use scroll to zoom, drag to pan
            </p>
          </div>
        </div>
      )}
    </div>
  );
};
