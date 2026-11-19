import React, { useState } from 'react';
import { useApp } from '../../context/AppContext';
import {
  Settings,
  Save,
  RotateCcw,
  Sparkles,
  Phone,
  Mail,
  MapPin,
  Clock,
  Globe,
  CheckCircle2
} from 'lucide-react';

export const AdminCMS: React.FC = () => {
  const { cmsConfig, updateCMSConfig, showToast } = useApp();

  const [formData, setFormData] = useState({ ...cmsConfig });

  const handleSave = (e: React.FormEvent) => {
    e.preventDefault();
    updateCMSConfig(formData);
  };

  return (
    <div className="space-y-6 animate-fadeIn">
      {/* Header bar */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-6 rounded-2xl bg-[#0B1220] border border-slate-800 shadow-xl">
        <div>
          <h2 className="font-serif-luxury text-2xl font-bold text-white flex items-center gap-2">
            <span>Website CMS & Homepage Builder</span>
          </h2>
          <p className="text-xs text-slate-400">
            Edit headlines, contact details, hero visuals, and branding in real time without touching code.
          </p>
        </div>

        <button
          onClick={handleSave}
          className="px-5 py-2.5 rounded-xl bg-[#E7C878] hover:bg-[#F3C464] text-[#0B1220] font-bold text-xs flex items-center gap-2 cursor-pointer shadow-lg shadow-[#E7C878]/20 self-start sm:self-auto"
        >
          <Save className="w-4 h-4" />
          <span>Save Live Changes</span>
        </button>
      </div>

      <form onSubmit={handleSave} className="space-y-8">
        {/* Section 1: Hero & Branding */}
        <div className="bg-[#0B1220] p-6 sm:p-8 rounded-2xl border border-slate-800 space-y-4 shadow-xl">
          <div className="flex items-center gap-2 text-[#E7C878] font-bold text-sm">
            <Sparkles className="w-4 h-4" />
            <span>Homepage Hero Banner & Catchphrase</span>
          </div>

          <div className="space-y-4 pt-2">
            <div className="space-y-1">
              <label className="text-xs font-semibold text-slate-300">
                Hero Main Headline
              </label>
              <input
                type="text"
                value={formData.heroHeadline}
                onChange={(e) => setFormData({ ...formData, heroHeadline: e.target.value })}
                className="w-full bg-[#07141E] text-white text-xs sm:text-sm rounded-lg px-3.5 py-2.5 border border-slate-700 focus:border-[#E7C878] focus:outline-none"
              />
              <span className="text-[10px] text-slate-500">
                Tip: Use periods to separate lines into gold emphasis text.
              </span>
            </div>

            <div className="space-y-1">
              <label className="text-xs font-semibold text-slate-300">
                Hero Subtitle / Description
              </label>
              <textarea
                rows={2}
                value={formData.heroSubtitle}
                onChange={(e) => setFormData({ ...formData, heroSubtitle: e.target.value })}
                className="w-full bg-[#07141E] text-white text-xs rounded-lg p-3 border border-slate-700 focus:border-[#E7C878] focus:outline-none"
              />
            </div>

            <div className="space-y-1">
              <label className="text-xs font-semibold text-slate-300">
                Hero Background Image URL
              </label>
              <input
                type="url"
                value={formData.heroBackground}
                onChange={(e) => setFormData({ ...formData, heroBackground: e.target.value })}
                className="w-full bg-[#07141E] text-white text-xs rounded-lg px-3.5 py-2 border border-slate-700 focus:border-[#E7C878] focus:outline-none"
              />
            </div>
          </div>
        </div>

        {/* Section 2: Contact Information */}
        <div className="bg-[#0B1220] p-6 sm:p-8 rounded-2xl border border-slate-800 space-y-4 shadow-xl">
          <div className="flex items-center gap-2 text-[#E7C878] font-bold text-sm">
            <Phone className="w-4 h-4" />
            <span>Agency Contact Coordinates</span>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
            <div className="space-y-1">
              <label className="text-xs font-semibold text-slate-300">Direct Phone Number</label>
              <input
                type="text"
                value={formData.contactPhone}
                onChange={(e) => setFormData({ ...formData, contactPhone: e.target.value })}
                className="w-full bg-[#07141E] text-white text-xs rounded-lg px-3.5 py-2 border border-slate-700 focus:border-[#E7C878] focus:outline-none"
              />
            </div>

            <div className="space-y-1">
              <label className="text-xs font-semibold text-slate-300">WhatsApp Inquiry Number</label>
              <input
                type="text"
                value={formData.contactWhatsApp}
                onChange={(e) => setFormData({ ...formData, contactWhatsApp: e.target.value })}
                className="w-full bg-[#07141E] text-white text-xs rounded-lg px-3.5 py-2 border border-slate-700 focus:border-[#E7C878] focus:outline-none"
              />
            </div>

            <div className="space-y-1">
              <label className="text-xs font-semibold text-slate-300">Email Address</label>
              <input
                type="email"
                value={formData.contactEmail}
                onChange={(e) => setFormData({ ...formData, contactEmail: e.target.value })}
                className="w-full bg-[#07141E] text-white text-xs rounded-lg px-3.5 py-2 border border-slate-700 focus:border-[#E7C878] focus:outline-none"
              />
            </div>

            <div className="space-y-1">
              <label className="text-xs font-semibold text-slate-300">Operating Hours</label>
              <input
                type="text"
                value={formData.workingHours}
                onChange={(e) => setFormData({ ...formData, workingHours: e.target.value })}
                className="w-full bg-[#07141E] text-white text-xs rounded-lg px-3.5 py-2 border border-slate-700 focus:border-[#E7C878] focus:outline-none"
              />
            </div>
          </div>

          <div className="space-y-1 pt-2">
            <label className="text-xs font-semibold text-slate-300">Office Physical Address</label>
            <input
              type="text"
              value={formData.officeAddress}
              onChange={(e) => setFormData({ ...formData, officeAddress: e.target.value })}
              className="w-full bg-[#07141E] text-white text-xs rounded-lg px-3.5 py-2 border border-slate-700 focus:border-[#E7C878] focus:outline-none"
            />
          </div>
        </div>

        {/* Section 3: About Us & Mission */}
        <div className="bg-[#0B1220] p-6 sm:p-8 rounded-2xl border border-slate-800 space-y-4 shadow-xl">
          <div className="flex items-center gap-2 text-[#E7C878] font-bold text-sm">
            <Globe className="w-4 h-4" />
            <span>About Us & Agency Mission</span>
          </div>

          <div className="space-y-4 pt-2">
            <div className="space-y-1">
              <label className="text-xs font-semibold text-slate-300">About Title</label>
              <input
                type="text"
                value={formData.aboutTitle}
                onChange={(e) => setFormData({ ...formData, aboutTitle: e.target.value })}
                className="w-full bg-[#07141E] text-white text-xs rounded-lg px-3.5 py-2 border border-slate-700 focus:border-[#E7C878] focus:outline-none"
              />
            </div>

            <div className="space-y-1">
              <label className="text-xs font-semibold text-slate-300">About Description</label>
              <textarea
                rows={3}
                value={formData.aboutDescription}
                onChange={(e) => setFormData({ ...formData, aboutDescription: e.target.value })}
                className="w-full bg-[#07141E] text-white text-xs rounded-lg p-3 border border-slate-700 focus:border-[#E7C878] focus:outline-none"
              />
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div className="space-y-1">
                <label className="text-xs font-semibold text-slate-300">Mission Statement</label>
                <textarea
                  rows={2}
                  value={formData.aboutMission}
                  onChange={(e) => setFormData({ ...formData, aboutMission: e.target.value })}
                  className="w-full bg-[#07141E] text-white text-xs rounded-lg p-2.5 border border-slate-700 focus:border-[#E7C878] focus:outline-none"
                />
              </div>

              <div className="space-y-1">
                <label className="text-xs font-semibold text-slate-300">Vision Statement</label>
                <textarea
                  rows={2}
                  value={formData.aboutVision}
                  onChange={(e) => setFormData({ ...formData, aboutVision: e.target.value })}
                  className="w-full bg-[#07141E] text-white text-xs rounded-lg p-2.5 border border-slate-700 focus:border-[#E7C878] focus:outline-none"
                />
              </div>
            </div>
          </div>
        </div>

        {/* Section 4: SEO Metadata */}
        <div className="bg-[#0B1220] p-6 sm:p-8 rounded-2xl border border-slate-800 space-y-4 shadow-xl">
          <div className="flex items-center gap-2 text-[#E7C878] font-bold text-sm">
            <Globe className="w-4 h-4" />
            <span>Search Engine Optimization (SEO) Metadata</span>
          </div>

          <div className="space-y-3 pt-2">
            <div className="space-y-1">
              <label className="text-xs font-semibold text-slate-300">SEO Page Title</label>
              <input
                type="text"
                value={formData.seoTitle}
                onChange={(e) => setFormData({ ...formData, seoTitle: e.target.value })}
                className="w-full bg-[#07141E] text-white text-xs rounded-lg px-3.5 py-2 border border-slate-700 focus:border-[#E7C878] focus:outline-none"
              />
            </div>

            <div className="space-y-1">
              <label className="text-xs font-semibold text-slate-300">SEO Meta Description</label>
              <textarea
                rows={2}
                value={formData.seoDescription}
                onChange={(e) => setFormData({ ...formData, seoDescription: e.target.value })}
                className="w-full bg-[#07141E] text-white text-xs rounded-lg p-2.5 border border-slate-700 focus:border-[#E7C878] focus:outline-none"
              />
            </div>
          </div>
        </div>

        {/* Submit */}
        <div className="flex items-center justify-end gap-3 pt-4">
          <button
            type="submit"
            className="px-8 py-3 rounded-xl bg-[#E7C878] hover:bg-[#F3C464] text-[#0B1220] font-bold text-sm transition-colors shadow-lg shadow-[#E7C878]/20 cursor-pointer flex items-center gap-2"
          >
            <Save className="w-4 h-4" />
            <span>Save & Apply to Public Website</span>
          </button>
        </div>
      </form>
    </div>
  );
};
