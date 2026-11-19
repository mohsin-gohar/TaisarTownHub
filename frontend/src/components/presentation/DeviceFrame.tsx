import React from 'react';
import { useApp } from '../../context/AppContext';
import { Smartphone, Tablet, Monitor } from 'lucide-react';

export const DeviceFrame: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const { deviceViewport, setDeviceViewport, isPresentationMode } = useApp();

  if (!isPresentationMode || deviceViewport === 'desktop') {
    return <>{children}</>;
  }

  return (
    <div className="min-h-screen bg-[#070B14] pt-28 pb-12 px-4 flex flex-col items-center justify-start overflow-x-hidden">
      {/* Device Indicator */}
      <div className="mb-4 flex items-center gap-2 text-xs text-slate-400 bg-slate-900/80 px-4 py-1.5 rounded-full border border-slate-700">
        {deviceViewport === 'mobile' ? (
          <>
            <Smartphone className="w-3.5 h-3.5 text-[#E7C878]" />
            <span>Mobile Device Simulation (390 x 844)</span>
          </>
        ) : (
          <>
            <Tablet className="w-3.5 h-3.5 text-[#E7C878]" />
            <span>Tablet Simulation (768 x 1024)</span>
          </>
        )}
        <button
          onClick={() => setDeviceViewport('desktop')}
          className="ml-3 text-[#E7C878] hover:underline font-medium"
        >
          Exit Frame
        </button>
      </div>

      {/* Frame Container */}
      <div
        className={`relative transition-all duration-300 rounded-[44px] p-3 shadow-2xl bg-[#1E293B] border-[10px] border-[#334155] ${
          deviceViewport === 'mobile'
            ? 'w-[410px] max-w-full'
            : 'w-[820px] max-w-full'
        }`}
      >
        {/* Notch / Dynamic Island */}
        {deviceViewport === 'mobile' && (
          <div className="absolute top-5 left-1/2 -translate-x-1/2 w-32 h-4 bg-black rounded-full z-50 flex items-center justify-center">
            <div className="w-2.5 h-2.5 rounded-full bg-slate-900 ml-auto mr-3 border border-slate-800" />
          </div>
        )}

        {/* Screen Frame */}
        <div className="rounded-[32px] overflow-hidden bg-[#07141E] shadow-inner max-h-[85vh] overflow-y-auto">
          {children}
        </div>
      </div>
    </div>
  );
};
