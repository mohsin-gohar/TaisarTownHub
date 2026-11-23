import React, { useState } from 'react';
import { useApp } from '../../context/AppContext';
import {
  MapPin,
  Phone,
  Mail,
  Clock,
  Send,
  CheckCircle2,
  AlertCircle,
  Sparkles,
  Building2
} from 'lucide-react';
import { validateInquiryForm } from '../../utils/logic';
import { WhatsAppCta } from '../common/WhatsAppCta';
import { buildWhatsAppLink } from '../../utils/whatsapp';

export const ContactSection: React.FC = () => {
  const { cmsConfig, addInquiry, showToast } = useApp();
  // This is the platform's own contact page; the number comes from CMS config
  // and is never used as a substitute for an agency's public contact.
  const platformWhatsapp = buildWhatsAppLink(
    cmsConfig.contactWhatsApp,
    'Assalam-o-Alaikum, I would like to speak with the TaisarTownHub advisory desk about Scheme 45 properties.'
  );

  const [formData, setFormData] = useState({
    fullName: '',
    phone: '',
    email: '',
    subject: 'Property Consultation / Site Visit Request',
    preferredPropertyOrProject: 'Sector 70-D / General Scheme 45',
    budgetRange: 'PKR 30 - 50 Lacs',
    message: ''
  });

  const [formError, setFormError] = useState<string | null>(null);
  const [formSuccess, setFormSuccess] = useState<boolean>(false);
  const [formSubmitting, setFormSubmitting] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (formSubmitting) return;
    setFormError(null);

    const validation = validateInquiryForm({
      fullName: formData.fullName,
      phone: formData.phone,
      email: formData.email,
      message: formData.message || 'Interested in property options in Taiser Town Scheme 45.'
    });

    if (!validation.isValid) {
      setFormError(validation.error || 'Please fill out all required fields.');
      return;
    }

    setFormSubmitting(true);
    const sent = await addInquiry({
      fullName: formData.fullName.trim(),
      phone: formData.phone.trim(),
      email: formData.email.trim(),
      subject: formData.subject,
      preferredPropertyOrProject: formData.preferredPropertyOrProject,
      budgetRange: formData.budgetRange,
      message: formData.message.trim(),
      source: 'Contact Form'
    });
    setFormSubmitting(false);

    if (!sent) {
      setFormError('Your inquiry was not sent. Please check the connection and try again.');
      return;
    }

    setFormSuccess(true);
    setFormData({
      fullName: '',
      phone: '',
      email: '',
      subject: 'Property Consultation / Site Visit Request',
      preferredPropertyOrProject: 'Sector 70-D / General Scheme 45',
      budgetRange: 'PKR 30 - 50 Lacs',
      message: ''
    });
  };

  return (
    <section className="py-20 px-4 sm:px-6 lg:px-8 bg-[#07141E] border-t border-[#E7C878]/15">
      <div className="max-w-7xl mx-auto space-y-16">
        {/* Title */}
        <div className="text-center max-w-3xl mx-auto space-y-3">
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-[#E7C878]/15 text-[#E7C878] border border-[#E7C878]/30">
            <Mail className="w-3.5 h-3.5" /> Direct Agency Connect
          </span>
          <h2 className="font-serif-luxury text-3xl sm:text-4xl font-bold text-white">
            Get in Touch & Book a Site Visit
          </h2>
          <p className="text-slate-400 text-sm">
            Speak directly with our senior Scheme 45 consultants. Schedule an on-ground visit or inquire about specific plot numbers.
          </p>
        </div>

        {/* Content Layout */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-10">
          {/* Contact Details & Quick Links (5 cols) */}
          <div className="lg:col-span-5 space-y-6">
            <div className="bg-[#0B1220] p-8 rounded-2xl border border-slate-800 space-y-6 shadow-xl">
              <h3 className="font-serif-luxury text-2xl font-bold text-white">
                Main Advisory Office
              </h3>
              <p className="text-xs text-slate-400 leading-relaxed">
                Located right at the entrance of Scheme 45 off the Northern Bypass corridor. We arrange accompanied site tours in AC 4x4 vehicles.
              </p>

              <div className="space-y-4 pt-2 text-xs sm:text-sm">
                <div className="flex items-start gap-3 text-slate-300">
                  <MapPin className="w-5 h-5 text-[#E7C878] shrink-0 mt-0.5" />
                  <div>
                    <span className="block font-semibold text-white">Office Address</span>
                    <span className="text-slate-400 text-xs">{cmsConfig.officeAddress}</span>
                  </div>
                </div>

                <div className="flex items-center gap-3 text-slate-300">
                  <Phone className="w-5 h-5 text-[#E7C878] shrink-0" />
                  <div>
                    <span className="block font-semibold text-white">Direct Phone</span>
                    <span className="text-slate-400 text-xs">{cmsConfig.contactPhone}</span>
                  </div>
                </div>

                <div className="flex items-center gap-3 text-slate-300">
                  <Mail className="w-5 h-5 text-[#E7C878] shrink-0" />
                  <div>
                    <span className="block font-semibold text-white">Email Desk</span>
                    <span className="text-slate-400 text-xs">{cmsConfig.contactEmail}</span>
                  </div>
                </div>

                <div className="flex items-center gap-3 text-slate-300">
                  <Clock className="w-5 h-5 text-[#E7C878] shrink-0" />
                  <div>
                    <span className="block font-semibold text-white">Operating Hours</span>
                    <span className="text-slate-400 text-xs">{cmsConfig.workingHours}</span>
                  </div>
                </div>
              </div>

              <div className="pt-4 border-t border-slate-800">
                <div className="p-4 rounded-xl bg-[#07141E] border border-[#E7C878]/20 text-center space-y-1.5 shadow-inner">
                  <div className="text-xs font-bold text-[#F6E3B4] flex items-center justify-center gap-1.5">
                    <Phone className="w-4 h-4 text-[#E7C878]" />
                    <span>Speak with the advisory desk</span>
                  </div>
                  <p className="text-[11px] text-slate-400 leading-relaxed">
                    Written inquiries on this page are saved to the agency desk and reviewed by a consultant.
                  </p>
                  <div className="flex flex-wrap items-center justify-center gap-2 pt-1">
                    {cmsConfig.contactPhone.trim() && (
                      <a
                        href={`tel:${cmsConfig.contactPhone.replace(/[^\d+]/g, '')}`}
                        className="inline-flex items-center gap-1.5 rounded-lg border border-slate-700 px-3 py-2 text-xs font-semibold text-slate-200 hover:text-white"
                      >
                        <Phone className="w-3.5 h-3.5" /> Call {cmsConfig.contactPhone.trim()}
                      </a>
                    )}
                    {platformWhatsapp && (
                      <WhatsAppCta href={platformWhatsapp} label="WhatsApp advisory desk" variant="outline" className="px-3 py-2 text-xs" />
                    )}
                  </div>
                </div>
              </div>
            </div>

            {/* Google Map Card */}
            <div className="rounded-2xl overflow-hidden border border-slate-800 shadow-xl h-64 bg-slate-900">
              <iframe
                src={cmsConfig.googleMapEmbedUrl}
                width="100%"
                height="100%"
                style={{ border: 0 }}
                allowFullScreen={false}
                loading="lazy"
                title="Taiser Town Karachi Location Map"
              />
            </div>
          </div>

          {/* Form (7 cols) */}
          <div className="lg:col-span-7">
            <div className="bg-[#0B1220] p-8 sm:p-10 rounded-2xl border border-[#E7C878]/30 shadow-2xl space-y-6">
              <div>
                <h3 className="font-serif-luxury text-2xl font-bold text-white">
                  Send an Inquiry or Schedule a Site Tour
                </h3>
                <p className="text-xs text-slate-400 mt-1">
                  Submissions are instantly routed to our agency CRM dashboard.
                </p>
              </div>

              {formSuccess ? (
                <div className="p-6 rounded-xl bg-emerald-950/60 border border-emerald-500/40 text-emerald-200 space-y-3 animate-fadeIn">
                  <div className="flex items-center gap-2 text-base font-bold text-emerald-400">
                    <CheckCircle2 className="w-5 h-5" />
                    <span>Inquiry Registered Successfully!</span>
                  </div>
                  <p className="text-xs text-emerald-300">
                    Your request has been filed in the TaisarTownHub agency system. A senior consultant will contact your phone or WhatsApp shortly.
                  </p>
                  <button
                    onClick={() => setFormSuccess(false)}
                    className="mt-2 text-xs font-semibold text-white underline"
                  >
                    Send another inquiry
                  </button>
                </div>
              ) : (
                <form onSubmit={handleSubmit} className="space-y-4">
                  {formError && (
                    <div className="p-3.5 rounded-lg bg-rose-950/60 border border-rose-500/40 text-rose-300 text-xs flex items-center gap-2">
                      <AlertCircle className="w-4 h-4 shrink-0 text-rose-400" />
                      <span>{formError}</span>
                    </div>
                  )}

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div className="space-y-1">
                      <label className="text-xs font-semibold text-slate-300">
                        Full Name <span className="text-rose-400">*</span>
                      </label>
                      <input
                        type="text"
                        required
                        value={formData.fullName}
                        onChange={(e) => setFormData({ ...formData, fullName: e.target.value })}
                        placeholder="e.g. Tariq Siddiqui"
                        className="w-full bg-[#07141E] text-white text-xs sm:text-sm rounded-lg px-3.5 py-2.5 border border-slate-700 focus:border-[#E7C878] focus:outline-none"
                      />
                    </div>

                    <div className="space-y-1">
                      <label className="text-xs font-semibold text-slate-300">
                        Phone / WhatsApp <span className="text-rose-400">*</span>
                      </label>
                      <input
                        type="tel"
                        required
                        value={formData.phone}
                        onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                        placeholder="e.g. +92 300 1234567"
                        className="w-full bg-[#07141E] text-white text-xs sm:text-sm rounded-lg px-3.5 py-2.5 border border-slate-700 focus:border-[#E7C878] focus:outline-none"
                      />
                    </div>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div className="space-y-1">
                      <label className="text-xs font-semibold text-slate-300">
                        Email Address (Optional)
                      </label>
                      <input
                        type="email"
                        value={formData.email}
                        onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                        placeholder="e.g. investor@gmail.com"
                        className="w-full bg-[#07141E] text-white text-xs sm:text-sm rounded-lg px-3.5 py-2.5 border border-slate-700 focus:border-[#E7C878] focus:outline-none"
                      />
                    </div>

                    <div className="space-y-1">
                      <label className="text-xs font-semibold text-slate-300">
                        Budget Range
                      </label>
                      <select
                        value={formData.budgetRange}
                        onChange={(e) => setFormData({ ...formData, budgetRange: e.target.value })}
                        className="w-full bg-[#07141E] text-white text-xs sm:text-sm rounded-lg px-3.5 py-2.5 border border-slate-700 focus:border-[#E7C878] focus:outline-none cursor-pointer"
                      >
                        <option value="Under PKR 25 Lacs">Under PKR 25 Lacs (Phase 2 Plots)</option>
                        <option value="PKR 25 - 50 Lacs">PKR 25 - 50 Lacs (120 Sq Yd Phase 1)</option>
                        <option value="PKR 50 - 90 Lacs">PKR 50 - 90 Lacs (240 Sq Yd Executive)</option>
                        <option value="PKR 1 Crore - 2 Crore">PKR 1 - 2 Crore (Boulevard / Villas)</option>
                        <option value="PKR 2 Crore+">Above PKR 2 Crore (Commercial / Estate)</option>
                      </select>
                    </div>
                  </div>

                  <div className="space-y-1">
                    <label className="text-xs font-semibold text-slate-300">
                      Sector or Property of Interest
                    </label>
                    <input
                      type="text"
                      value={formData.preferredPropertyOrProject}
                      onChange={(e) => setFormData({ ...formData, preferredPropertyOrProject: e.target.value })}
                      placeholder="e.g. Sector 70-D, 120 Sq Yd West Open Plot"
                      className="w-full bg-[#07141E] text-white text-xs sm:text-sm rounded-lg px-3.5 py-2.5 border border-slate-700 focus:border-[#E7C878] focus:outline-none"
                    />
                  </div>

                  <div className="space-y-1">
                    <label className="text-xs font-semibold text-slate-300">
                      Message / Requirement Details <span className="text-rose-400">*</span>
                    </label>
                    <textarea
                      required
                      rows={4}
                      value={formData.message}
                      onChange={(e) => setFormData({ ...formData, message: e.target.value })}
                      placeholder="Please mention your preferred location, whether you require ready possession, or if you wish to book an accompanied Sunday site visit..."
                      className="w-full bg-[#07141E] text-white text-xs sm:text-sm rounded-lg p-3.5 border border-slate-700 focus:border-[#E7C878] focus:outline-none"
                    />
                  </div>

                  <button
                    type="submit"
                    disabled={formSubmitting}
                    className="w-full py-3.5 px-6 rounded-xl bg-[#E7C878] hover:bg-[#F3C464] disabled:opacity-60 disabled:cursor-not-allowed text-[#0B1220] font-bold text-sm transition-all duration-200 shadow-lg shadow-[#E7C878]/20 flex items-center justify-center gap-2 cursor-pointer"
                  >
                    <Send className="w-4 h-4" />
                    <span>{formSubmitting ? 'Sending inquiry…' : 'Submit Official Inquiry'}</span>
                  </button>
                </form>
              )}
            </div>
          </div>
        </div>
      </div>
    </section>
  );
};
