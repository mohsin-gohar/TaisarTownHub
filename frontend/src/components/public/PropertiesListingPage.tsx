import React, { useEffect, useState } from 'react';
import { useApp } from '../../context/AppContext';
import {
  Search,
  MapPin,
  Maximize2,
  Flame,
  ArrowRight,
  RotateCcw,
  Sparkles,
  Scale,
  Check,
} from 'lucide-react';
import { Property, PropertyType, PublicProperty } from '../../types';
import { filterProperties, toPublicProperty } from '../../utils/logic';
import { EmptyState } from '../common/EmptyState';
import { FavoriteHeart } from '../common/FavoriteHeart';
import { ImageWithFallback } from '../common/ImageWithFallback';
import { api } from '../../services/api';
import {
  MAX_COMPARE_ITEMS,
  buildCompareShareUrl,
  parseCompareIdsFromSearch,
  readCompareIds,
  toggleCompareId,
  writeCompareIds,
} from '../../utils/propertyCompare';
import {
  describeListingFilters,
  listingStateFromSavedSearch,
  savedSearchFiltersFromState,
} from '../../utils/savedSearchFilters';
import { CompareModal } from './CompareModal';
import { CompareTray } from './CompareTray';
import { SaveSearchModal } from './SaveSearchModal';
import { SavedSearchPanel, SavedSearchRecord } from './SavedSearchPanel';

interface PropertiesListingPageProps {
  initialFilter?: {
    type?: PropertyType | 'All';
    phase?: 'Phase 1' | 'Phase 2' | 'Phase 3' | 'All';
    sector?: string | 'All';
    budget?: string;
    size?: string;
    purpose?: 'sale' | 'rent' | 'All';
  };
}

export const PropertiesListingPage: React.FC<PropertiesListingPageProps> = ({ initialFilter }) => {
  const {
    properties,
    setSelectedPropertyId,
    setCurrentView,
    isAdminAuthenticated,
    showToast,
    confirmAction,
  } = useApp();

  // Filters State
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedPhase, setSelectedPhase] = useState<'Phase 1' | 'Phase 2' | 'Phase 3' | 'All'>(
    initialFilter?.phase || 'All'
  );
  const [selectedType, setSelectedType] = useState<PropertyType | 'All'>(initialFilter?.type || 'All');
  const [selectedSector, setSelectedSector] = useState<string>(initialFilter?.sector || 'All');
  const [selectedSize, setSelectedSize] = useState<string>(initialFilter?.size || 'All');
  const [selectedPurpose, setSelectedPurpose] = useState<'sale' | 'rent' | 'All'>(
    (initialFilter?.purpose as 'sale' | 'rent' | undefined) ||
    (typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('purpose') === 'rent' ? 'rent' :
      typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('purpose') === 'sale' ? 'sale' : 'All')
  );
  const [possessionOnly, setPossessionOnly] = useState<boolean>(false);
  const [sortBy, setSortBy] = useState<'featured' | 'price-asc' | 'price-desc' | 'newest'>('featured');

  // Saved searches / alerts
  const [savedSearches, setSavedSearches] = useState<SavedSearchRecord[]>([]);
  const [savedLoading, setSavedLoading] = useState(false);
  const [savedBusyId, setSavedBusyId] = useState<string | null>(null);
  const [saveModalOpen, setSaveModalOpen] = useState(false);
  const [saveSaving, setSaveSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);

  // Comparison shortlist (URL param wins so shared links work, then storage)
  const [compareIds, setCompareIds] = useState<string[]>(() => {
    const fromUrl = typeof window !== 'undefined' ? parseCompareIdsFromSearch(window.location.search) : [];
    return fromUrl.length > 0 ? fromUrl : readCompareIds();
  });
  const [compareOpen, setCompareOpen] = useState(false);

  useEffect(() => {
    writeCompareIds(compareIds);
  }, [compareIds]);

  useEffect(() => {
    if (!isAdminAuthenticated) {
      setSavedSearches([]);
      return;
    }
    let active = true;
    setSavedLoading(true);
    void api.getSavedSearches().then((items) => {
      if (!active) return;
      setSavedSearches(Array.isArray(items) ? (items as SavedSearchRecord[]) : []);
      setSavedLoading(false);
    });
    return () => {
      active = false;
    };
  }, [isAdminAuthenticated]);

  const reloadSavedSearches = async () => {
    const items = await api.getSavedSearches();
    setSavedSearches(Array.isArray(items) ? (items as SavedSearchRecord[]) : []);
  };

  // Convert budget helper
  let minPrice = 0;
  let maxPrice = 0;
  if (initialFilter?.budget === 'under-25') {
    maxPrice = 2500000;
  } else if (initialFilter?.budget === '25-50') {
    minPrice = 2500000;
    maxPrice = 5000000;
  } else if (initialFilter?.budget === '50-100') {
    minPrice = 5000000;
    maxPrice = 10000000;
  } else if (initialFilter?.budget === '1cr-plus') {
    minPrice = 10000000;
  }

  const [priceRange, setPriceRange] = useState<{ min: number; max: number }>({
    min: minPrice,
    max: maxPrice,
  });

  const filtered = filterProperties(properties, {
    searchTerm,
    type: selectedType,
    phase: selectedPhase,
    sector: selectedSector,
    minPrice: priceRange.min,
    maxPrice: priceRange.max > 0 ? priceRange.max : undefined,
    sizeSqYards: selectedSize !== 'All' ? Number(selectedSize) : 'All',
    possessionOnly,
  }).filter((p: any) => {
    if (selectedPurpose === 'All') return true;
    const purpose = p.listing_purpose || p.listingPurpose || p.purpose;
    if (selectedPurpose === 'rent') return purpose === 'rent';
    return purpose === 'sale' || purpose === undefined || purpose === null || purpose === '';
  });

  // Sorting & Serializing for Public View
  const sortedProperties: PublicProperty[] = [...filtered]
    .sort((a, b) => {
      if (sortBy === 'featured') {
        if (a.isFeatured && !b.isFeatured) return -1;
        if (!a.isFeatured && b.isFeatured) return 1;
        return 0;
      }
      if (sortBy === 'price-asc') return a.pricePKR - b.pricePKR;
      if (sortBy === 'price-desc') return b.pricePKR - a.pricePKR;
      if (sortBy === 'newest') return new Date(b.createdDate).getTime() - new Date(a.createdDate).getTime();
      return 0;
    })
    .map(toPublicProperty);

  const resetAllFilters = () => {
    setSearchTerm('');
    setSelectedPhase('All');
    setSelectedType('All');
    setSelectedSector('All');
    setSelectedSize('All');
    setSelectedPurpose('All');
    setPossessionOnly(false);
    setPriceRange({ min: 0, max: 0 });
    setSortBy('featured');
  };

  const handleOpenProperty = (property: { id: string }) => {
    setSelectedPropertyId(property.id);
    setCurrentView('property-detail');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  /**
   * Property detail pages are excluded from sitemap.xml on purpose (they are
   * dynamic), so crawlers can only discover them through these listing links.
   * Real anchors — crawlable, middle-clickable, open-in-new-tab — with an SPA
   * transition on a plain left-click.
   */
  const propertyHref = (id: string) => `/properties?property=${encodeURIComponent(id)}`;

  const onPropertyLinkClick =
    (id: string) => (e: React.MouseEvent<HTMLAnchorElement>) => {
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
      e.preventDefault();
      handleOpenProperty({ id });
    };

  const compareItems: PublicProperty[] = compareIds
    .map((id) => properties.find((property) => property.id === id))
    .filter((property): property is Property => Boolean(property))
    .map(toPublicProperty);

  const handleToggleCompare = (property: { id: string; title: string }) => {
    const result = toggleCompareId(compareIds, property.id);
    if (result.limitReached) {
      showToast('info', `You can compare up to ${MAX_COMPARE_ITEMS} properties. Remove one from the shortlist first.`);
      return;
    }
    setCompareIds(result.ids);
    showToast('success', result.added ? `Added “${property.title}” to the comparison shortlist.` : `Removed “${property.title}” from the shortlist.`);
  };

  const handleShareShortlist = async () => {
    const url = buildCompareShareUrl(`${window.location.origin}/properties`, compareIds);
    try {
      await navigator.clipboard.writeText(url);
      showToast('success', 'Shortlist link copied. Share it with your buyer or team.');
    } catch {
      showToast('info', `Shortlist link: ${url}`);
    }
  };

  const currentFilterState = {
    searchTerm,
    type: selectedType,
    phase: selectedPhase,
    sector: selectedSector,
    sizeSqYards: selectedSize,
    possessionOnly,
    minPrice: priceRange.min,
    maxPrice: priceRange.max,
  };
  const currentFilterSummary = describeListingFilters(currentFilterState);
  const currentFiltersPayload = savedSearchFiltersFromState(currentFilterState);

  const handleOpenSaveSearch = () => {
    if (!isAdminAuthenticated) {
      showToast('info', 'Sign in to save this search and receive email alerts.');
      setCurrentView('login');
      return;
    }
    setSaveError(null);
    setSaveModalOpen(true);
  };

  const handleCreateSavedSearch = async (payload: {
    name: string;
    frequency: 'instant' | 'daily' | 'weekly';
    alerts_enabled: boolean;
  }) => {
    setSaveSaving(true);
    setSaveError(null);
    const result = await api.createSavedSearch({
      name: payload.name,
      filters: currentFiltersPayload,
      frequency: payload.frequency,
      alerts_enabled: payload.alerts_enabled,
    });
    setSaveSaving(false);
    if (!result.ok) {
      setSaveError(result.error || 'Could not save this search.');
      return;
    }
    setSaveModalOpen(false);
    showToast('success', payload.alerts_enabled ? 'Search saved. We will email you when a new match is published.' : 'Search saved. Email alerts are paused.');
    await reloadSavedSearches();
  };

  const handleApplySavedSearch = (search: SavedSearchRecord) => {
    const state = listingStateFromSavedSearch(search.filters);
    setSearchTerm(state.searchTerm);
    setSelectedType(state.type as PropertyType | 'All');
    setSelectedPhase(state.phase as 'Phase 1' | 'Phase 2' | 'Phase 3' | 'All');
    setSelectedSector(state.sector);
    setSelectedSize(state.sizeSqYards);
    setPossessionOnly(state.possessionOnly);
    setPriceRange({ min: state.minPrice, max: state.maxPrice });
    showToast('success', `Applied saved search “${search.name}”.`);
    document.getElementById('listing-results')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  const handleToggleSavedSearchAlerts = async (search: SavedSearchRecord) => {
    setSavedBusyId(search.id);
    const result = await api.updateSavedSearch(search.id, { alerts_enabled: !search.alerts_enabled });
    setSavedBusyId(null);
    if (!result.ok) {
      showToast('error', result.error || 'Could not update alerts for this search.');
      return;
    }
    showToast('success', search.alerts_enabled ? 'Email alerts paused for this search.' : 'Email alerts resumed.');
    await reloadSavedSearches();
  };

  const handleDeleteSavedSearch = async (search: SavedSearchRecord) => {
    const confirmed = await confirmAction(`Delete “${search.name}”? Email alerts for this search will stop.`, {
      title: 'Delete saved search',
      confirmLabel: 'Delete search',
    });
    if (!confirmed) return;

    setSavedBusyId(search.id);
    const ok = await api.deleteSavedSearch(search.id);
    setSavedBusyId(null);
    if (!ok) {
      showToast('error', 'Could not delete this saved search.');
      return;
    }
    showToast('success', 'Saved search deleted.');
    await reloadSavedSearches();
  };

  return (
    <div className={`py-12 px-4 sm:px-6 lg:px-8 bg-[#0B1220] min-h-screen space-y-10 ${compareIds.length > 0 ? 'pb-40' : ''}`}>
      <div className="max-w-7xl mx-auto space-y-10">
        {/* Header */}
        <div className="text-center max-w-3xl mx-auto space-y-3">
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-[#C5A46D]/15 text-[#C5A46D] border border-[#C5A46D]/30">
            <Sparkles className="w-3.5 h-3.5" /> Verified Land & Homes
          </span>
          <h1 className="font-serif-luxury text-3xl sm:text-4xl lg:text-5xl font-bold text-white">
            Taiser Town Scheme 45 Property Listings
          </h1>
          <p className="text-slate-400 text-sm">
            Browse verified 80, 120, 240, 400 Sq. Yd. plots, commercial avenues, and luxury duplex villas.
          </p>
        </div>

        {/* Filter Controls Panel */}
        <div className="bg-[#101C2C] p-6 rounded-2xl border border-slate-800 space-y-5 shadow-xl">
          {/* Phase Quick Selector Tabs */}
          <div>
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-1 mb-2.5">
              <span className="text-xs font-bold text-slate-200 uppercase tracking-wider flex items-center gap-1.5">
                <Sparkles className="w-3.5 h-3.5 text-[#C5A46D]" /> Taiser Town Phase:
              </span>
              <span className="text-[11px] text-slate-400">
                Scheme 45 is organized into 3 operational phases
              </span>
            </div>
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
              {[
                { id: 'All', label: 'All Phases', desc: 'Complete Inventory' },
                { id: 'Phase 1', label: 'Phase 1', desc: 'Possession Sectors' },
                { id: 'Phase 2', label: 'Phase 2', desc: 'Developing Avenues' },
                { id: 'Phase 3', label: 'Phase 3', desc: 'Upcoming Expansions' }
              ].map((tab) => {
                const isActive = selectedPhase === tab.id;
                return (
                  <button
                    key={tab.id}
                    type="button"
                    onClick={() => setSelectedPhase(tab.id as any)}
                    className={`p-2.5 rounded-xl border text-left transition-all cursor-pointer ${
                      isActive
                        ? 'bg-[#C5A46D] text-[#101C2C] border-[#F9E7BA] font-bold shadow-md shadow-[#C5A46D]/20'
                        : 'bg-[#0B1220] text-slate-300 border-slate-800 hover:border-slate-700 hover:text-white'
                    }`}
                  >
                    <div className="text-xs font-semibold">{tab.label}</div>
                    <div className={`text-[10px] truncate ${isActive ? 'text-[#101C2C]/80 font-medium' : 'text-slate-400'}`}>
                      {tab.desc}
                    </div>
                  </button>
                );
              })}
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5 pt-3 border-t border-slate-800">
            {/* Search */}
            <div className="space-y-1">
              <label className="text-[11px] font-semibold text-slate-300 uppercase">Search Keywords</label>
              <div className="relative">
                <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                <input
                  type="text"
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  placeholder="Plot number, sector, road..."
                  className="w-full bg-[#0B1220] text-white text-xs rounded-lg pl-9 pr-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                />
              </div>
            </div>

            {/* Type */}
            <div className="space-y-1">
              <label className="text-[11px] font-semibold text-slate-300 uppercase">Property Type</label>
              <select
                value={selectedType}
                onChange={(e) => setSelectedType(e.target.value as PropertyType | 'All')}
                className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none cursor-pointer"
              >
                <option value="All">All Types</option>
                <option value="Residential Plot">Residential Plot</option>
                <option value="Commercial Plot">Commercial Plot</option>
                <option value="House / Villa">House / Villa</option>
                <option value="Commercial Shop">Commercial Shop</option>
                <option value="Apartment">Apartment</option>
              </select>
            </div>

            {/* Sector */}
            <div className="space-y-1">
              <label className="text-[11px] font-semibold text-slate-300 uppercase">Sector / Block</label>
              <select
                value={selectedSector}
                onChange={(e) => setSelectedSector(e.target.value)}
                className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none cursor-pointer"
              >
                <option value="All">All Sectors</option>
                <optgroup label="Phase 1 (Possession Ready)">
                  <option value="Sector 70-D">Sector 70-D (Prime Possession)</option>
                  <option value="Sector 71">Sector 71 (Park View & Villas)</option>
                  <option value="Sector 72">Sector 72 (Executive Enclave)</option>
                  <option value="Sector 50">Sector 50 (Main Commercial)</option>
                  <option value="Sector 30-A">Sector 30-A (MDA Homes)</option>
                  <option value="Sector 40">Sector 40 (Boulevard)</option>
                </optgroup>
                <optgroup label="Phase 2 (Developing Avenues)">
                  <option value="Sector 63">Sector 63 (High Ground)</option>
                  <option value="Sector 64">Sector 64 (Boulevard Links)</option>
                  <option value="Sector 65">Sector 65 (Arterial)</option>
                  <option value="Sector 77">Sector 77 (Budget Residential)</option>
                  <option value="Sector 78">Sector 78 (Expansion)</option>
                  <option value="Sector 80">Sector 80 (Hill View)</option>
                </optgroup>
                <optgroup label="Phase 3 (Upcoming & Enclaves)">
                  <option value="Sector 84">Sector 84 (Green Farmhouses)</option>
                  <option value="Sector 85">Sector 85 (Long-Term Value)</option>
                  <option value="Sector 88">Sector 88 (Northern Crest)</option>
                </optgroup>
              </select>
            </div>

            {/* Size */}
            <div className="space-y-1">
              <label className="text-[11px] font-semibold text-slate-300 uppercase">Size (Sq. Yards)</label>
              <select
                value={selectedSize}
                onChange={(e) => setSelectedSize(e.target.value)}
                className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none cursor-pointer"
              >
                <option value="All">All Sizes</option>
                <option value="80">80 Sq. Yds</option>
                <option value="120">120 Sq. Yds</option>
                <option value="240">240 Sq. Yds</option>
                <option value="400">400 Sq. Yds</option>
              </select>
            </div>

            {/* Sort Order */}
            <div className="space-y-1">
              <label className="text-[11px] font-semibold text-slate-300 uppercase">Sort Listings</label>
              <select
                value={sortBy}
                onChange={(e) => setSortBy(e.target.value as any)}
                className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none cursor-pointer"
              >
                <option value="featured">Featured First</option>
                <option value="price-asc">Price: Low to High</option>
                <option value="price-desc">Price: High to Low</option>
                <option value="newest">Newest First</option>
              </select>
            </div>
          </div>

          {/* Secondary Filter Row */}
          <div className="flex flex-wrap items-center justify-between gap-4 pt-4 border-t border-slate-800 text-xs">
            <label className="flex items-center gap-2 cursor-pointer text-slate-300 select-none">
              <input
                type="checkbox"
                checked={possessionOnly}
                onChange={(e) => setPossessionOnly(e.target.checked)}
                className="rounded text-[#C5A46D] focus:ring-[#C5A46D] accent-[#C5A46D]"
              />
              <span>Show Ready Possession Plots Only</span>
            </label>

            <div className="flex items-center gap-4">
              <span className="text-slate-400">
                Found <strong>{sortedProperties.length}</strong> matching properties
              </span>
              <button
                onClick={resetAllFilters}
                className="text-[#C5A46D] hover:underline flex items-center gap-1 cursor-pointer"
              >
                <RotateCcw className="w-3.5 h-3.5" />
                <span>Reset Filters</span>
              </button>
            </div>
          </div>
        </div>

        {/* Saved searches + email alerts */}
        <SavedSearchPanel
          searches={savedSearches}
          loading={savedLoading}
          authenticated={isAdminAuthenticated}
          onSaveCurrent={handleOpenSaveSearch}
          onApply={handleApplySavedSearch}
          onToggleAlerts={handleToggleSavedSearchAlerts}
          onDelete={handleDeleteSavedSearch}
          busyId={savedBusyId}
        />

        {/* Property Grid or Empty State */}
        {sortedProperties.length === 0 ? (
          <EmptyState
            icon={<Search className="w-9 h-9" />}
            chip="Zero Matches"
            title="No Properties Found"
            description="Try adjusting your sector, budget, or size criteria — or contact our agency desk for off-market inventory across MDA Scheme 45."
            actionLabel="Reset All Filters"
            onAction={resetAllFilters}
          />
        ) : (
          <div id="listing-results" className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            {sortedProperties.map((prop) => {
              return (
                <div
                  key={prop.id}
                  className="group bg-[#101C2C] rounded-2xl overflow-hidden border border-slate-800 hover:border-[#C5A46D]/50 shadow-xl transition-all duration-300 hover:-translate-y-1 flex flex-col justify-between"
                >
                  <div
                    className="relative h-56 overflow-hidden cursor-pointer"
                    onClick={() => handleOpenProperty(prop)}
                  >
                    <ImageWithFallback
                      src={prop.images?.[0]}
                      alt={prop.title}
                      loading="lazy"
                      decoding="async"
                      className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                    />
                    <div className="absolute inset-0 bg-gradient-to-t from-[#101C2C] via-transparent to-black/30" />

                    <div className="absolute top-3 left-3 flex flex-wrap gap-1.5">
                      <span className="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-[#C5A46D] text-[#101C2C]">
                        {prop.type}
                      </span>
                      <span className="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-[#101C2C]/90 text-[#F9E7BA] border border-[#C5A46D]/40 backdrop-blur-sm">
                        {prop.phase || 'Phase 1'}
                      </span>
                      {prop.status === 'Hot Deal' && (
                        <span className="px-2 py-0.5 rounded-full text-[11px] font-bold bg-rose-600 text-white flex items-center gap-1 shadow">
                          <Flame className="w-3 h-3 fill-current" /> Hot Deal
                        </span>
                      )}
                      {prop.status === 'Sold' && (
                        <span className="px-2 py-0.5 rounded-full text-[11px] font-bold bg-slate-900/90 text-slate-300 border border-slate-600 backdrop-blur-sm">
                          Sold
                        </span>
                      )}
                      {prop.possessionReady && (
                        <span className="px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-700 text-white">
                          Possession Ready
                        </span>
                      )}
                    </div>

                    {/* Wishlist toggle (persisted) */}
                    <div className="absolute top-3 right-3">
                      <FavoriteHeart id={prop.id} title={prop.title} />
                    </div>

                    {/* Price overlay with per-sq-yd reference */}
                    <div className="absolute bottom-3 left-3 flex flex-col">
                      <span className="px-3 py-1 rounded-lg bg-black/80 font-serif-luxury font-bold text-base text-[#F9E7BA]">
                        {prop.formattedPrice}
                      </span>
                      <span className="mt-1 px-2 text-[10px] text-slate-300 bg-black/60 rounded inline-block">
                        PKR {Math.round(prop.pricePKR / Math.max(prop.sizeSqYards, 1)).toLocaleString()} / Sq. Yd
                      </span>
                    </div>

                    {/* Comparison shortlist toggle */}
                    <button
                      type="button"
                      onClick={(event) => {
                        event.stopPropagation();
                        handleToggleCompare(prop);
                      }}
                      aria-pressed={compareIds.includes(prop.id)}
                      aria-label={
                        compareIds.includes(prop.id)
                          ? `Remove ${prop.title} from comparison`
                          : `Add ${prop.title} to comparison`
                      }
                      className={`absolute bottom-3 right-3 inline-flex items-center gap-1 rounded-lg border px-2 py-1 text-[10px] font-bold backdrop-blur-md transition ${
                        compareIds.includes(prop.id)
                          ? 'border-[#C5A46D] bg-[#C5A46D] text-[#101C2C]'
                          : 'border-white/20 bg-black/65 text-slate-200 hover:border-[#C5A46D]/70 hover:text-[#F9E7BA]'
                      }`}
                    >
                      {compareIds.includes(prop.id) ? <Check className="h-3 w-3" /> : <Scale className="h-3 w-3" />}
                      {compareIds.includes(prop.id) ? 'Comparing' : 'Compare'}
                    </button>
                  </div>

                  <div className="p-5 flex-1 flex flex-col justify-between space-y-4">
                    <div className="space-y-2">
                      <div className="flex items-center gap-1.5 text-xs text-[#C5A46D]">
                        <MapPin className="w-3.5 h-3.5 shrink-0" />
                        <span className="truncate">{prop.location}</span>
                      </div>

                      <h3 className="font-serif-luxury text-lg font-bold text-white group-hover:text-[#C5A46D] transition-colors line-clamp-1">
                        <a
                          href={propertyHref(prop.id)}
                          onClick={onPropertyLinkClick(prop.id)}
                          className="hover:text-[#C5A46D] transition-colors"
                        >
                          {prop.title}
                        </a>
                      </h3>

                      <p className="text-xs text-slate-400 line-clamp-2 leading-relaxed">
                        {prop.description}
                      </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2 py-2 border-t border-b border-slate-800 text-xs text-slate-300">
                      <span className="inline-flex items-center gap-1 px-2 py-1 rounded bg-[#0B1220] border border-slate-700">
                        <Maximize2 className="w-3 h-3 text-[#C5A46D]" />
                        <strong>{prop.sizeSqYards}</strong> Sq. Yd
                      </span>
                      {prop.plotNumber && (
                        <span className="px-2 py-1 rounded bg-[#0B1220] border border-[#C5A46D]/30 text-[#C5A46D] text-[11px] font-mono">
                          Plot {prop.plotNumber}
                        </span>
                      )}
                      {prop.fileNumber && (
                        <span className="px-2 py-1 rounded bg-[#0B1220] border border-slate-700 text-slate-300 text-[11px] font-mono">
                          File #{prop.fileNumber}
                        </span>
                      )}
                      {prop.westOpen && (
                        <span className="px-2 py-1 rounded bg-[#0B1220] border border-slate-700 text-[11px]">
                          West Open
                        </span>
                      )}
                      {prop.cornerPlot && (
                        <span className="px-2 py-1 rounded bg-[#0B1220] border border-slate-700 text-[11px]">
                          Corner
                        </span>
                      )}
                      {prop.parkFacing && (
                        <span className="px-2 py-1 rounded bg-[#0B1220] border border-slate-700 text-[11px]">
                          Park Facing
                        </span>
                      )}
                    </div>

                    <div className="pt-2">
                      <a
                        href={propertyHref(prop.id)}
                        onClick={onPropertyLinkClick(prop.id)}
                        className="w-full py-2.5 px-3 rounded-xl bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C] font-bold text-xs transition-colors flex items-center justify-center gap-1.5 cursor-pointer shadow-md shadow-[#C5A46D]/20"
                      >
                        <span>View Details &amp; Dossier</span>
                        <ArrowRight className="w-3.5 h-3.5" />
                      </a>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>

      <CompareTray
        items={compareItems}
        onRemove={(id) => setCompareIds(toggleCompareId(compareIds, id).ids)}
        onClear={() => setCompareIds([])}
        onCompare={() => {
          if (compareItems.length < 2) {
            showToast('info', 'Select at least two properties to compare.');
            return;
          }
          setCompareOpen(true);
        }}
        onShare={handleShareShortlist}
      />

      {compareOpen && compareItems.length >= 2 && (
        <CompareModal
          items={compareItems}
          onClose={() => setCompareOpen(false)}
          onRemove={(id) => {
            const next = toggleCompareId(compareIds, id).ids;
            setCompareIds(next);
            if (next.length < 2) setCompareOpen(false);
          }}
          onOpenProperty={(property) => {
            setCompareOpen(false);
            handleOpenProperty(property);
          }}
        />
      )}

      <SaveSearchModal
        open={saveModalOpen}
        summary={currentFilterSummary}
        defaultName={currentFilterSummary === 'All published listings' ? 'All Scheme 45 listings' : currentFilterSummary.slice(0, 110)}
        saving={saveSaving}
        error={saveError}
        onClose={() => setSaveModalOpen(false)}
        onCreate={handleCreateSavedSearch}
      />
    </div>
  );
};
