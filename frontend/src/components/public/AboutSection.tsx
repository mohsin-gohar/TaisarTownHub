import React from 'react';
import { useApp } from '../../context/AppContext';
import {
  ShieldCheck,
  Target,
  Eye,
  Award,
  Users,
  Building,
  CheckCircle2,
  Sparkles
} from 'lucide-react';
import { Reveal } from '../common/Reveal';
import { ImageWithFallback } from '../common/ImageWithFallback';

export const AboutSection: React.FC = () => {
  const { cmsConfig, currentTheme } = useApp();

  const coreValues = [
    {
      title: 'Complete File Authenticity',
      desc: 'Zero tolerance for disputed or unverified files. Every plot undergoes double biometric verification at the Malir Development Authority.'
    },
    {
      title: 'Ground Truth & GPS Demarcation',
      desc: 'We never sell paper allotments without physical on-ground survey, corner verification, and road access inspection.'
    },
    {
      title: 'Overseas Investor Safeguards',
      desc: 'Dedicated escrow coordination and video walkthroughs for non-resident Pakistanis in UAE, UK, North America, and Saudi Arabia.'
    },
    {
      title: 'Transparent Pricing & Zero Hidden Surcharges',
      desc: 'All transfer costs, utility connection charges, and MDA fees are outlined upfront in writing before any token deposit.'
    }
  ];

  const teamMembers = [
    {
      name: 'Saleem Siddiqui',
      role: 'Principal Broker & Head of Scheme 45 Operations',
      exp: '14+ Years in Karachi Real Estate',
      image: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=400&q=80'
    },
    {
      name: 'Kamran Haider',
      role: 'Senior Project Consultant & Commercial Lead',
      exp: '10+ Years in Master Developments',
      image: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=400&q=80'
    },
    {
      name: 'Sobia Tariq',
      role: 'Client Documentation & Biometric Coordinator',
      exp: '8+ Years MDA & Legal Verification',
      image: 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=400&q=80'
    }
  ];

  return (
    <section className="py-20 px-4 sm:px-6 lg:px-8 bg-[#07141E] border-t border-[#E7C878]/15">
      <div className="max-w-7xl mx-auto space-y-16">
        {/* Intro Grid */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
          <div className="space-y-6">
            <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-[#E7C878]/15 text-[#E7C878] border border-[#E7C878]/30">
              <Sparkles className="w-3.5 h-3.5" /> Agency Overview
            </span>
            <h2 className="font-serif-luxury text-3xl sm:text-4xl lg:text-5xl font-bold text-white leading-tight">
              {cmsConfig.aboutTitle}
            </h2>
            <p className="text-sm sm:text-base text-slate-300 leading-relaxed font-light">
              {cmsConfig.aboutDescription}
            </p>

            {/* Mission & Vision cards */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
              <div className="p-5 rounded-xl bg-[#0B1220] border border-slate-800 space-y-2">
                <div className="flex items-center gap-2 text-[#E7C878] font-bold text-xs uppercase tracking-wider">
                  <Target className="w-4 h-4" /> Our Mission
                </div>
                <p className="text-xs text-slate-400 leading-relaxed">
                  {cmsConfig.aboutMission}
                </p>
              </div>

              <div className="p-5 rounded-xl bg-[#0B1220] border border-slate-800 space-y-2">
                <div className="flex items-center gap-2 text-[#E7C878] font-bold text-xs uppercase tracking-wider">
                  <Eye className="w-4 h-4" /> Our Vision
                </div>
                <p className="text-xs text-slate-400 leading-relaxed">
                  {cmsConfig.aboutVision}
                </p>
              </div>
            </div>
          </div>

          {/* Visual Showcase */}
          <Reveal className="relative" y={26}>
            <div className="relative rounded-2xl overflow-hidden border border-[#E7C878]/30 shadow-2xl">
              <ImageWithFallback
                src="https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1000&q=80"
                alt="Luxury Modern Architecture"
                loading="lazy"
                decoding="async"
                className="w-full h-full object-cover"
              />
              <div className="absolute inset-0 bg-gradient-to-t from-[#07141E] via-transparent to-transparent" />
            </div>

            {/* Floating Trust Badge */}
            <div className="absolute -bottom-6 -left-4 sm:left-6 p-4 rounded-xl bg-[#0B1220]/95 border border-[#E7C878]/40 shadow-xl backdrop-blur-md flex items-center gap-4">
              <div className="w-12 h-12 rounded-lg bg-[#E7C878] text-[#0B1220] flex items-center justify-center font-bold text-xl">
                100%
              </div>
              <div>
                <div className="text-xs font-bold text-white">Biometric Verified</div>
                <div className="text-[11px] text-slate-400">MDA Direct Transfer Facilitation</div>
              </div>
            </div>
          </Reveal>
        </div>

        {/* Core Values */}
        <div className="space-y-8 pt-8">
          <Reveal className="text-center space-y-2 max-w-2xl mx-auto">
            <h3 className="font-serif-luxury text-2xl sm:text-3xl font-bold text-white">
              Why Investors Choose TaisarTownHub
            </h3>
            <p className="text-xs text-slate-400">
              Our four institutional pillars ensure safety, security, and exponential capital growth.
            </p>
          </Reveal>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            {coreValues.map((val, i) => (
              <Reveal key={i} delay={i * 80} y={24} className="h-full">
                <div className="p-6 rounded-xl bg-[#0B1220] border border-slate-800 space-y-3 h-full">
                  <div className="w-8 h-8 rounded-lg bg-[#E7C878]/15 text-[#E7C878] flex items-center justify-center text-sm font-bold border border-[#E7C878]/30">
                    0{i + 1}
                  </div>
                  <h4 className="font-bold text-white text-sm">
                    {val.title}
                  </h4>
                  <p className="text-xs text-slate-400 leading-relaxed">
                    {val.desc}
                  </p>
                </div>
              </Reveal>
            ))}
          </div>
        </div>

        {/* Leadership Team */}
        <div className="space-y-8 pt-8">
          <Reveal className="text-center space-y-2 max-w-2xl mx-auto">
            <h3 className="font-serif-luxury text-2xl sm:text-3xl font-bold text-white">
              Senior Real Estate Advisory Team
            </h3>
            <p className="text-xs text-slate-400">
              Seasoned property specialists with boots-on-the-ground presence in Taiser Town.
            </p>
          </Reveal>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
            {teamMembers.map((member, i) => (
              <Reveal key={i} delay={i * 90} y={24}>
                <div
                  className="bg-[#0B1220] rounded-2xl overflow-hidden border border-slate-800 text-center p-6 space-y-4 hover:border-[#E7C878]/40 transition-colors"
                >
                  <ImageWithFallback
                    src={member.image}
                    alt={member.name}
                    loading="lazy"
                    decoding="async"
                    className="w-24 h-24 rounded-full mx-auto object-cover border-2 border-[#E7C878]"
                  />
                  <div>
                    <h4 className="font-serif-luxury font-bold text-white text-base">
                      {member.name}
                    </h4>
                    <div className="text-xs text-[#E7C878] font-medium mt-1">
                      {member.role}
                    </div>
                    <div className="text-[11px] text-slate-400 mt-1">
                      {member.exp}
                    </div>
                  </div>
                </div>
              </Reveal>
            ))}
          </div>
        </div>
      </div>
    </section>
  );
};
