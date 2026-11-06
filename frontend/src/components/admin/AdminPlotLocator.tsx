import React, { useState } from 'react';
import { useApp } from '../../context/AppContext';
import {
  MapPin,
  Search,
  Compass,
  CheckCircle2,
  Clock,
  Sparkles,
  Layers,
  ChevronRight,
  Maximize2,
  Eye,
  Info,
  X,
  AlertCircle
} from 'lucide-react';
import { PlotMarker } from '../../types';
import { formatPKRCurrency } from '../../utils/logic';

export const AdminPlotLocator: React.FC = () => {
  const { plotMarkers, updatePlotMarker, properties, transactions, setAdminTab } = useApp();

  const [activeSector, setActiveSector] = useState<'Sector 63' | 'Sector 70-D'>('Sector 63');
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedPlot, setSelectedPlot] = useState<PlotMarker | null>(null);

  // Filter markers by active sector and sync status from property & transaction records
  const sectorMarkers = plotMarkers
    .filter((p) => p.sector === activeSector)
    .map((marker) => {
      // Find matching property by plotNumber and sector
      const matchingProp = properties.find(
        (prop) =>
          prop.sector === marker.sector &&
          (prop.plotNumber?.toLowerCase() === marker.plotNumber.toLowerCase() ||
           (prop.title?.toLowerCase() || '').includes(`plot ${marker.plotNumber.toLowerCase()}`))
      );

      // Find matching active transaction
      const matchingTx = transactions.find(
        (t) =>
          t.sector === marker.sector &&
          (t.plotNumber?.toLowerCase() === marker.plotNumber.toLowerCase()) &&
          t.status !== 'Cancelled'
      );

      let effectiveStatus = marker.status;
      if (matchingTx) {
        if (matchingTx.status === 'Completed' || matchingTx.status === 'Under Transfer') {
          effectiveStatus = 'Sold';
        } else if (matchingTx.status === 'Agreement Signed' || matchingTx.status === 'Partially Paid' || matchingTx.status === 'Payment Pending') {
          effectiveStatus = 'Under Deal';
        } else if (matchingTx.status === 'Negotiation' || matchingTx.status === 'Draft' || matchingTx.status === 'Token Paid') {
          effectiveStatus = 'Token Paid';
        }
      } else if (matchingProp) {
        if (matchingProp.status === 'Sold' || matchingProp.status === 'Transferred') effectiveStatus = 'Sold';
        else if (matchingProp.status === 'Reserved' || matchingProp.status === 'Under Deal' || matchingProp.status === 'Under Offer') effectiveStatus = 'Under Deal';
        else if (matchingProp.status === 'Available') effectiveStatus = 'Available';
      }

      return {
        ...marker,
        status: effectiveStatus,
      };
    });

  // Search filter
  const matchingPlot = searchQuery.trim()
    ? plotMarkers.find(
        (p) =>
          p.plotNumber.toLowerCase().includes(searchQuery.toLowerCase().trim()) ||
          p.id.toLowerCase().includes(searchQuery.toLowerCase().trim())
      )
    : null;

  const handleSelectPlot = (plot: PlotMarker) => {
    setSelectedPlot(plot);
  };

  const getStatusColor = (status: PlotMarker['status']) => {
    switch (status) {
      case 'Available':
        return 'bg-[#C5A46D]/20 text-[#F9E7BA] border-[#C5A46D]/40 hover:bg-[#C5A46D]/30';
      case 'Token Paid':
        return 'bg-[#C5A46D]/10 text-[#C5A46D] border-[#C5A46D]/30 hover:bg-[#C5A46D]/20';
      case 'Under Deal':
        return 'bg-slate-800 text-slate-300 border-slate-700 hover:bg-slate-700';
      case 'Sold':
        return 'bg-slate-900/80 text-slate-500 border-slate-800 hover:bg-slate-800';
      default:
        return 'bg-slate-800 text-slate-300 border-slate-700';
    }
  };

  return (
    <div className="space-y-6 animate-fadeIn">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-6 rounded-2xl bg-gradient-to-r from-[#101C2C] to-[#1A2638] border border-[#C5A46D]/30 shadow-xl">
        <div>
          <div className="flex items-center gap-2">
            <h2 className="font-serif-luxury text-2xl font-bold text-white">
              Sector Master Map & Plot Grid Locator
            </h2>
            <span className="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-[#C5A46D]/20 text-[#C5A46D] border border-[#C5A46D]/40">
              Interactive GIS
            </span>
            <span className="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/20 text-amber-400 border border-amber-500/40">
              Manual plot diagram — not a verified geographic map
            </span>
          </div>
          <p className="text-xs text-slate-300 mt-1">
            Visual plot positioning, 100ft road frontage identification, dimensions (24&apos;x45&apos;), and live inventory availability.
          </p>
        </div>

        {/* Sector Switcher */}
        <div className="flex items-center bg-[#0B1220] p-1 rounded-xl border border-slate-800">
          <button
            onClick={() => setActiveSector('Sector 63')}
            className={`px-3.5 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer ${
              activeSector === 'Sector 63'
                ? 'bg-[#C5A46D] text-[#101C2C] shadow'
                : 'text-slate-400 hover:text-white'
            }`}
          >
            Sector 63 (Phase 2)
          </button>
          <button
            onClick={() => setActiveSector('Sector 70-D')}
            className={`px-3.5 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer ${
              activeSector === 'Sector 70-D'
                ? 'bg-[#C5A46D] text-[#101C2C] shadow'
                : 'text-slate-400 hover:text-white'
            }`}
          >
            Sector 70-D (Phase 1)
          </button>
        </div>
      </div>

      {/* Search & Legend Controls */}
      <div className="flex flex-col md:flex-row items-center justify-between gap-4 bg-[#101C2C] p-4 rounded-xl border border-slate-800">
        <div className="relative w-full md:w-80">
          <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            placeholder="Search plot number (e.g. R-66, R-65, D-101)..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="w-full bg-[#0B1220] text-white text-xs pl-9 pr-4 py-2.5 rounded-lg border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
          />
        </div>

        {/* Status Legend */}
        <div className="flex flex-wrap items-center gap-4 text-xs">
          <span className="flex items-center gap-1.5 text-slate-300">
            <span className="w-2.5 h-2.5 rounded-sm bg-[#C5A46D] inline-block" />
            Available
          </span>
          <span className="flex items-center gap-1.5 text-slate-300">
            <span className="w-2.5 h-2.5 rounded-sm bg-[#8C6D37] inline-block" />
            Token Paid
          </span>
          <span className="flex items-center gap-1.5 text-slate-300">
            <span className="w-2.5 h-2.5 rounded-sm bg-slate-700 inline-block" />
            Under Deal
          </span>
          <span className="flex items-center gap-1.5 text-slate-400">
            <span className="w-2.5 h-2.5 rounded-sm bg-slate-800 inline-block" />
            Sold / Booked
          </span>
        </div>
      </div>

      {/* Main Map View & Plot Grid Layout */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {/* Left Interactive Plot Map Grid (8 cols) */}
        <div className="lg:col-span-8 bg-[#101C2C] p-6 rounded-2xl border border-slate-800 shadow-xl space-y-4">
          <div className="flex items-center justify-between border-b border-slate-800 pb-3">
            <div>
              <h3 className="font-serif-luxury text-base font-bold text-white flex items-center gap-2">
                <span>{activeSector} — Block A Master Grid Layout</span>
                {activeSector === 'Sector 63' && (
                  <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-[#C5A46D] text-[#101C2C]">
                    Handwritten Notes Verified
                  </span>
                )}
              </h3>
              <p className="text-[11px] text-slate-400">
                Click any plot cell to inspect dimensions, road width, ownership, and deal status.
              </p>
            </div>
            <div className="flex items-center gap-2 text-xs text-slate-400">
              <Compass className="w-4 h-4 text-[#C5A46D]" />
              <span>North-West Facing</span>
            </div>
          </div>

          {/* Disclaimer Badge */}
          <div className="flex items-center justify-between flex-wrap gap-2 pt-1">
            <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-semibold bg-amber-500/15 text-amber-300 border border-amber-500/30">
              <AlertCircle className="w-3.5 h-3.5 text-amber-400" />
              Manual plot diagram — not a verified geographic map
            </span>
            <span className="text-[10px] text-slate-400">
              Live status synchronized with ERP transactions
            </span>
          </div>

          {/* 100ft Road Banner for Sector 63 */}
          {activeSector === 'Sector 63' && (
            <div className="p-2.5 rounded-xl bg-gradient-to-r from-amber-950/40 via-amber-900/20 to-transparent border border-amber-500/30 flex items-center justify-between text-xs text-amber-200">
              <span className="flex items-center gap-1.5 font-bold">
                <span className="w-2 h-2 rounded-full bg-amber-400 animate-pulse" />
                100-Foot Main Sector Boulevard Frontage (Front Row)
              </span>
              <span className="text-[10px] text-amber-300/80">Plot R-66 Direct Facing</span>
            </div>
          )}

          {/* Grid of Plots */}
          <div className="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-5 gap-3 pt-2">
            {sectorMarkers.map((plot) => {
              const isSelected = selectedPlot?.id === plot.id;
              const isSearchMatched = matchingPlot?.id === plot.id;
              const isSpecialR66 = plot.plotNumber === 'R-66';

              return (
                <button
                  key={plot.id}
                  onClick={() => handleSelectPlot(plot)}
                  className={`p-3.5 rounded-xl border text-left transition-all relative flex flex-col justify-between h-28 cursor-pointer ${
                    isSelected
                      ? 'border-[#C5A46D] bg-[#C5A46D]/15 shadow-lg shadow-[#C5A46D]/20 ring-2 ring-[#C5A46D]'
                      : isSearchMatched
                      ? 'border-amber-400 bg-amber-400/20 ring-2 ring-amber-400 animate-pulse'
                      : getStatusColor(plot.status)
                  }`}
                >
                  <div className="flex items-start justify-between">
                    <div>
                      <span className="font-mono font-bold text-sm block">
                        Plot {plot.plotNumber}
                      </span>
                      <span className="text-[10px] opacity-80 block">
                        {plot.block || plot.subSector} • {plot.sizeSqYards} Yds
                      </span>
                    </div>

                    {isSpecialR66 && (
                      <span className="w-2.5 h-2.5 rounded-full bg-[#C5A46D] shadow" title="Handwritten Note Plot" />
                    )}
                  </div>

                  {/* Badges */}
                  <div className="space-y-1">
                    {(plot.is100ftRoadFacing || plot.roadFacing) && (
                      <span className="px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-500/30 text-amber-300 block truncate">
                        100ft Road
                      </span>
                    )}
                    {plot.dimensions && (
                      <span className="text-[9px] text-slate-300 font-mono block">
                        {plot.dimensions}
                      </span>
                    )}
                    <span className="text-[10px] font-bold block">
                      {plot.status}
                    </span>
                  </div>
                </button>
              );
            })}
          </div>

          <div className="p-3 rounded-xl bg-[#0B1220] border border-slate-800 text-[11px] text-slate-400 flex items-center justify-between">
            <span>
              Total Plots Mapped in {activeSector}: <strong>{sectorMarkers.length} Plots</strong>
            </span>
            <span className="text-[#C5A46D]">
              {sectorMarkers.filter((p) => p.status === 'Available').length} Ready for Immediate Possession
            </span>
          </div>
        </div>

        {/* Right Plot Inspector Panel (4 cols) */}
        <div className="lg:col-span-4 bg-[#101C2C] p-6 rounded-2xl border border-slate-800 shadow-xl flex flex-col justify-between space-y-6">
          {selectedPlot ? (
            <div className="space-y-4">
              <div className="flex items-start justify-between border-b border-slate-800 pb-3">
                <div>
                  <span className="text-[10px] font-bold uppercase tracking-wider text-[#C5A46D] block">
                    Selected Plot Inspector
                  </span>
                  <h3 className="font-serif-luxury text-2xl font-bold text-white">
                    Plot {selectedPlot.plotNumber}
                  </h3>
                  <span className="text-xs text-slate-400">
                    {selectedPlot.sector} • {selectedPlot.block || selectedPlot.subSector}
                  </span>
                </div>
                <button
                  onClick={() => setSelectedPlot(null)}
                  className="p-1 rounded-lg text-slate-400 hover:text-white cursor-pointer"
                >
                  <X className="w-4 h-4" />
                </button>
              </div>

              {/* Status Pill */}
              <div className="flex items-center justify-between bg-[#0B1220] p-3 rounded-xl border border-slate-800">
                <span className="text-xs text-slate-400">Plot Status:</span>
                <select
                  value={selectedPlot.status}
                  onChange={(e) => {
                    const newStatus = e.target.value as PlotMarker['status'];
                    updatePlotMarker(selectedPlot.id, { status: newStatus as any });
                    setSelectedPlot({ ...selectedPlot, status: newStatus });
                  }}
                  className="bg-slate-900 text-white font-bold text-xs p-1.5 rounded border border-slate-700 cursor-pointer focus:outline-none focus:border-[#C5A46D]"
                >
                  <option value="Available">Available</option>
                  <option value="Token Paid">Token Paid</option>
                  <option value="Under Deal">Under Deal</option>
                  <option value="Sold">Sold</option>
                </select>
              </div>

              {/* Specifications Card */}
              <div className="space-y-2.5 text-xs">
                <div className="flex justify-between py-1 border-b border-slate-800/80">
                  <span className="text-slate-400">Plot Area:</span>
                  <span className="font-bold text-white">
                    {selectedPlot.sizeSqYards} Sq. Yards (1,080 Sq. Ft)
                  </span>
                </div>

                {selectedPlot.dimensions && (
                  <div className="flex justify-between py-1 border-b border-slate-800/80">
                    <span className="text-slate-400">Dimensions:</span>
                    <span className="font-mono font-bold text-[#C5A46D]">
                      {selectedPlot.dimensions} (24ft Front x 45ft Depth)
                    </span>
                  </div>
                )}

                <div className="flex justify-between py-1 border-b border-slate-800/80">
                  <span className="text-slate-400">Road Frontage:</span>
                  <span className="font-bold text-white">
                    {(selectedPlot.is100ftRoadFacing || selectedPlot.roadFacing) ? '100-Foot Main Boulevard' : `${selectedPlot.roadWidth || 40}-Foot Street`}
                  </span>
                </div>

                <div className="flex justify-between py-1 border-b border-slate-800/80">
                  <span className="text-slate-400">Corner Status:</span>
                  <span className="font-bold text-white">
                    {(selectedPlot.isCorner || selectedPlot.corner) ? 'Yes (Corner Plot)' : 'No (Normal Plot)'}
                  </span>
                </div>

                <div className="flex justify-between py-1 border-b border-slate-800/80">
                  <span className="text-slate-400">West Open:</span>
                  <span className="font-bold text-[#C5A46D]">
                    {(selectedPlot.isWestOpen || selectedPlot.westOpen) ? 'Yes (West Open)' : 'No'}
                  </span>
                </div>

                {selectedPlot.pricePKR && (
                  <div className="flex justify-between py-1 border-b border-slate-800/80">
                    <span className="text-slate-400">List Consideration:</span>
                    <span className="font-bold text-[#C5A46D] font-mono text-sm">
                      {formatPKRCurrency(selectedPlot.pricePKR)}
                    </span>
                  </div>
                )}
              </div>

              {/* Handwritten Note Highlight */}
              {selectedPlot.plotNumber === 'R-66' && (
                <div className="p-3.5 rounded-xl bg-[#C5A46D]/10 border border-[#C5A46D]/40 space-y-1 text-xs text-[#F8F7F3]">
                  <div className="font-bold text-[#C5A46D] flex items-center gap-1.5">
                    <Sparkles className="w-3.5 h-3.5" />
                    Handwritten Sheet Verification
                  </div>
                  <p className="text-[11px] text-slate-300">
                    Plot R-66, Sector 63 Block A. Frontage 24ft, Depth 45ft (120 Sq Yds). Facing 100ft road.
                    Seller Haji Abdul Rasheed, Buyer Muhammad Usman, Facilitating Agent Saleem Siddiqui (Commission: PKR 20,000).
                  </p>
                </div>
              )}
            </div>
          ) : (
            <div className="h-full flex flex-col items-center justify-center text-center p-6 space-y-3">
              <div className="w-14 h-14 rounded-2xl bg-slate-800/60 border border-slate-700 flex items-center justify-center text-[#C5A46D]">
                <MapPin className="w-7 h-7" />
              </div>
              <h4 className="font-serif-luxury font-bold text-white text-base">
                No Plot Selected
              </h4>
              <p className="text-xs text-slate-400 max-w-xs">
                Click on any plot marker on the map to review dimensions, 100ft road status, owner records, or update booking status.
              </p>
              <button
                onClick={() => {
                  const r66 = plotMarkers.find((p) => p.plotNumber === 'R-66');
                  if (r66) setSelectedPlot(r66);
                }}
                className="text-xs text-[#C5A46D] hover:underline font-bold cursor-pointer"
              >
                Inspect Plot R-66 (Handwritten Record) &rarr;
              </button>
            </div>
          )}

          {/* Bottom Action */}
          <div className="pt-4 border-t border-slate-800">
            <button
              onClick={() => setAdminTab('transactions')}
              className="w-full py-2.5 rounded-xl bg-slate-800 hover:bg-[#C5A46D] hover:text-[#101C2C] text-[#C5A46D] text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer border border-slate-700"
            >
              <span>View Deal Transactions Ledger</span>
              <ChevronRight className="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};
