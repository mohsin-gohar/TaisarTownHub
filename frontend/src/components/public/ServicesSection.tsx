import React from 'react';
import { useApp } from '../../context/AppContext';
import {
  FileCheck2,
  TrendingUp,
  Building,
  ShieldCheck,
  Compass,
  Briefcase,
  ArrowRight,
  Sparkles
} from 'lucide-react';
import { Reveal } from '../common/Reveal';

export const ServicesSection: React.FC = () => {
  const { cmsConfig, setCurrentView } = useApp();

  const services = [
    {
      icon: ShieldCheck,
      title: 'MDA File Verification & Biometric Support',
      desc: 'Complete ledger check at Malir Development Authority (MDA) headquarters, biometric transfer guidance, and clearance of outstanding development dues before purchase.'
    },
    {
      icon: Compass,
      title: 'On-Ground Plot Demarcation',
      desc: 'Physical site visits with electronic measuring tools and GPS coordinates to verify corner pegs, street widths (40ft/60ft/100ft), and elevation levels.'
    },
    {
      icon: Building,
      title: 'Housing Scheme & Commercial Promotion',
      desc: 'High-production digital marketing, 3D architectural elevations, and investor syndication for developers building in Taiser Town.'
    },
    {
      icon: TrendingUp,
      title: 'Investment Advisory for Overseas Pakistanis',
      desc: 'Targeted sector advice for overseas buyers looking for high capital growth, secure land banking, and transparent token payment milestones.'
    },
    {
      icon: FileCheck2,
      title: 'Ready Villa & House Turnkey Sales',
      desc: 'Curated inventory of constructed 80 and 120 sq. yard single-storey and duplex homes with active utility meters and occupancy readiness.'
    },
    {
      icon: Briefcase,
      title: 'Commercial Boulevard Land Acquisition',
      desc: 'Securing high-visibility commercial plots on 150ft and 100ft boulevards for shopping arcades, private clinics, schools, and corporate towers.'
    }
  ];

  return (
    <section className="py-20 px-4 sm:px-6 lg:px-8 bg-[#07141E] border-t border-[#E7C878]/15">
      <div className="max-w-7xl mx-auto space-y-12">
        <div className="text-center max-w-3xl mx-auto space-y-3">
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-[#E7C878]/15 text-[#E7C878] border border-[#E7C878]/30">
            <Sparkles className="w-3.5 h-3.5" /> Comprehensive Real Estate Services
          </span>
          <h2 className="font-serif-luxury text-3xl sm:text-4xl font-bold text-white">
            End-to-End Real Estate Expertise
          </h2>
          <p className="text-slate-400 text-sm">
            From initial sector appraisal to biometric transfer and physical possession, our team ensures every rupee invested is protected.
          </p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          {services.map((service, idx) => {
            const Icon = service.icon;
            return (
              <Reveal key={idx} delay={(idx % 3) * 80} y={22} className="h-full">
                <div className="glass-panel p-8 rounded-2xl border border-slate-800 hover:border-[#E7C878]/40 transition-all duration-300 hover:-translate-y-1 space-y-4 group h-full">
                  <div className="w-12 h-12 rounded-xl bg-[#E7C878]/15 text-[#E7C878] flex items-center justify-center border border-[#E7C878]/30 group-hover:scale-110 transition-transform">
                    <Icon className="w-6 h-6" />
                  </div>
                  <h3 className="font-serif-luxury text-xl font-bold text-white group-hover:text-[#E7C878] transition-colors">
                    {service.title}
                  </h3>
                  <p className="text-xs text-slate-300 leading-relaxed font-light">
                    {service.desc}
                  </p>
                </div>
              </Reveal>
            );
          })}
        </div>

        {/* Action prompt */}
        <div className="p-8 rounded-2xl bg-gradient-to-r from-[#0B1220] to-[#1A2638] border border-[#E7C878]/30 flex flex-col sm:flex-row items-center justify-between gap-6">
          <div className="space-y-1 text-center sm:text-left">
            <h4 className="font-serif-luxury text-xl font-bold text-white">
              Need Verification on an Existing Allotment File?
            </h4>
            <p className="text-xs text-slate-400">
              Share your sector and plot number with our legal consultants for a confidential verification report.
            </p>
          </div>
          <button
            onClick={() => setCurrentView('contact')}
            className="px-6 py-3 rounded-lg bg-[#E7C878] hover:bg-[#F3C464] text-[#0B1220] font-bold text-xs transition-colors shadow flex items-center gap-2 cursor-pointer shrink-0"
          >
            <span>Consult Our Agency</span>
            <ArrowRight className="w-4 h-4" />
          </button>
        </div>
      </div>
    </section>
  );
};
