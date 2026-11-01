import React from 'react';
import { useApp } from '../../context/AppContext';
import {
  Presentation,
  Palette,
  Smartphone,
  Tablet,
  Monitor,
  LayoutDashboard,
  RotateCcw,
  X,
  Sparkles,
  Check
} from 'lucide-react';
import { clientThemes } from '../../data/publicSeed';
import { ClientThemeId, DeviceViewport } from '../../types';

export const PresentationBar: React.FC = () => {
  const {
    isPresentationMode,
    togglePresentationMode,
    currentTheme,
    setClientThemeId,
    deviceViewport,
    setDeviceViewport,
    setCurrentView,
    resetToDefaultData
  } = useApp();

  if (!isPresentationMode) return null;

  return (
    <aside aria-label="Client Presentation Controls" className="fixed top-2 left-1/2 -translate-x-1/2 z-50 w-full max-w-5xl px-3 pointer-events-none">
      <div className="pointer-events-auto bg-[#101C2C]/95 backdrop-blur-xl border-2 border-[#C5A46D] rounded-2xl shadow-2xl p-2.5 sm:p-3 text-white">
        <div className="flex flex-wrap items-center justify-between gap-3">
          {/* Header Badge */}
          <div className="flex items-center gap-2">
            <span className="p-1.5 rounded-lg bg-[#C5A46D] text-[#101C2C]">
              <Presentation className="w-4 h-4" />
            </span>
            <div>
              <div className="flex items-center gap-1.5 text-xs font-bold text-[#C5A46D] uppercase tracking-wider">
                <Sparkles className="w-3.5 h-3.5" /> Client Presentation Deck
              </div>
              <p className="text-[11px] text-slate-300 hidden sm:block">
                Show your real estate client custom branding, device reflow, and live CMS.
              </p>
            </div>
          </div>

          {/* Theme Selector */}
          <div className="flex items-center gap-1.5 bg-slate-900/90 p-1 rounded-xl border border-slate-700">
            <span className="text-[11px] text-slate-400 px-2 hidden md:inline font-medium">Theme:</span>
            {clientThemes.map((theme) => {
              const isActive = currentTheme.id === theme.id;
              return (
                <button
                  key={theme.id}
                  onClick={() => setClientThemeId(theme.id as ClientThemeId)}
                  className={`px-2.5 py-1 rounded-lg text-xs font-medium transition-all flex items-center gap-1.5 cursor-pointer ${
                    isActive
                      ? 'bg-[#C5A46D] text-[#101C2C] font-bold shadow'
                      : 'text-slate-300 hover:text-white hover:bg-slate-800'
                  }`}
                  title={theme.description}
                >
                  <span
                    className="w-2.5 h-2.5 rounded-full border border-white/40"
                    style={{ backgroundColor: theme.primaryAccent }}
                  />
                  <span>{theme.agencyName.split(' ')[0]}</span>
                  {isActive && <Check className="w-3 h-3 text-[#101C2C]" />}
                </button>
              );
            })}
          </div>

          {/* Viewport Frame Switcher */}
          <div className="flex items-center gap-1 bg-slate-900/90 p-1 rounded-xl border border-slate-700">
            <button
              onClick={() => setDeviceViewport('desktop')}
              className={`p-1.5 rounded-lg text-xs transition-colors cursor-pointer ${
                deviceViewport === 'desktop'
                  ? 'bg-[#C5A46D] text-white'
                  : 'text-slate-400 hover:text-white'
              }`}
              title="Desktop View (Full Screen)"
            >
              <Monitor className="w-4 h-4" />
            </button>
            <button
              onClick={() => setDeviceViewport('tablet')}
              className={`p-1.5 rounded-lg text-xs transition-colors cursor-pointer ${
                deviceViewport === 'tablet'
                  ? 'bg-[#C5A46D] text-white'
                  : 'text-slate-400 hover:text-white'
              }`}
              title="Tablet Preview (768px)"
            >
              <Tablet className="w-4 h-4" />
            </button>
            <button
              onClick={() => setDeviceViewport('mobile')}
              className={`p-1.5 rounded-lg text-xs transition-colors cursor-pointer ${
                deviceViewport === 'mobile'
                  ? 'bg-[#C5A46D] text-white'
                  : 'text-slate-400 hover:text-white'
              }`}
              title="Mobile Mockup Preview (390px)"
            >
              <Smartphone className="w-4 h-4" />
            </button>
          </div>

          {/* Quick Actions */}
          <div className="flex items-center gap-2">
            <button
              onClick={() => setCurrentView('admin')}
              className="px-2.5 py-1.5 rounded-lg bg-[#C5A46D] hover:bg-[#D4AF37] text-white text-xs font-semibold flex items-center gap-1.5 cursor-pointer shadow"
              title="Show Client the CMS Back-Office"
            >
              <LayoutDashboard className="w-3.5 h-3.5" />
              <span>CMS Demo</span>
            </button>
            <button
              onClick={resetToDefaultData}
              className="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs cursor-pointer border border-slate-700"
              title="Reset all demo data to pristine state"
            >
              <RotateCcw className="w-3.5 h-3.5" />
            </button>
            <button
              onClick={togglePresentationMode}
              className="p-1.5 rounded-lg bg-slate-800 hover:bg-rose-900/60 text-slate-400 hover:text-white text-xs cursor-pointer"
              title="Close Presentation Mode"
            >
              <X className="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
    </aside>
  );
};
