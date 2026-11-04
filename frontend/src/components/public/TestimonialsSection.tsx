import React, { useEffect, useState } from 'react';
import { api } from '../../services/api';
import { Testimonial } from '../../types';
import { Star, Quote, Sparkles, CheckCircle2, BadgeCheck } from 'lucide-react';
import { Reveal } from '../common/Reveal';

export const TestimonialsSection: React.FC = () => {
  const [testimonials, setTestimonials] = useState<Testimonial[]>([]);

  useEffect(() => {
    void api.getCatalog().then((catalog) => {
      const rows = catalog?.testimonials;
      if (Array.isArray(rows)) setTestimonials(rows as Testimonial[]);
    });
  }, []);

  return (
    <section className="py-20 px-4 sm:px-6 lg:px-8 bg-[#101C2C]/40 border-t border-[#C5A46D]/15 relative overflow-hidden">
      {/* Ambient gold glow */}
      <div className="pointer-events-none absolute -top-32 left-1/2 -translate-x-1/2 w-[600px] h-[300px] rounded-full bg-[#C5A46D]/5 blur-3xl" aria-hidden="true" />

      <div className="max-w-7xl mx-auto space-y-12 relative">
        <Reveal className="text-center max-w-3xl mx-auto space-y-3">
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-[#C5A46D]/15 text-[#C5A46D] border border-[#C5A46D]/30">
            <Sparkles className="w-3.5 h-3.5" /> Client Experiences
          </span>
          <h2 className="font-serif-luxury text-3xl sm:text-4xl font-bold text-white">
            Trusted by Overseas & Local Investors
          </h2>
          <p className="text-slate-400 text-sm">
            Read what property buyers and homebuilders say about our transparency and on-ground execution.
          </p>
        </Reveal>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-4xl mx-auto">
          {testimonials.map((t, i) => (
            <Reveal key={t.id} delay={i * 110} y={24}>
              <div className="relative bg-[#0B1220]/90 backdrop-blur-sm p-8 pt-10 rounded-2xl border border-slate-800 hover:border-[#C5A46D]/40 transition-all shadow-2xl flex flex-col justify-between space-y-6 hover:-translate-y-1 duration-300 group">
                {/* Oversized quote mark */}
                <Quote
                  aria-hidden="true"
                  className="absolute -top-4 left-7 w-10 h-10 text-[#C5A46D]/30 fill-[#C5A46D]/10 group-hover:text-[#C5A46D]/50 transition-colors"
                />

                {/* Demo verified badge */}
                {t.isVerifiedDemo && (
                  <span className="absolute top-4 right-4 px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 flex items-center gap-1">
                    <BadgeCheck className="w-3 h-3" />
                    Demo
                  </span>
                )}

                <div className="space-y-4">
                  {/* Stars */}
                  <div className="flex items-center gap-1 text-[#C5A46D]">
                    {[...Array(t.rating)].map((_, s) => (
                      <Star key={s} className="w-4 h-4 fill-current" />
                    ))}
                  </div>

                  <p className="text-sm text-slate-300 italic leading-relaxed">
                    "{t.quote}"
                  </p>
                </div>

                <div className="pt-4 border-t border-slate-800/80 flex items-center gap-4">
                  <img
                    src={t.avatar}
                    alt={t.clientName}
                    loading="lazy"
                    decoding="async"
                    className="w-12 h-12 rounded-full object-cover border border-[#C5A46D]/40 ring-2 ring-transparent group-hover:ring-[#C5A46D]/20 transition-all"
                  />
                  <div>
                    <h4 className="font-bold text-white text-sm">
                      {t.clientName}
                    </h4>
                    <div className="text-[11px] text-[#C5A46D]">
                      {t.designation}
                    </div>
                    <div className="text-[10px] text-slate-500 mt-0.5 flex items-center gap-1">
                      <CheckCircle2 className="w-3 h-3 text-emerald-400" />
                      <span>{t.propertyPurchased}</span>
                    </div>
                  </div>
                </div>
              </div>
            </Reveal>
          ))}
        </div>

        <Reveal className="text-center">
          <span className="text-[10px] uppercase tracking-widest text-slate-500">
            Client notes stored with the agency records
          </span>
        </Reveal>
      </div>
    </section>
  );
};
