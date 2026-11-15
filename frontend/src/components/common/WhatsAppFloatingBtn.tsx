import React from 'react';
import { useApp } from '../../context/AppContext';
import { MessageCircle } from 'lucide-react';
import { generateWhatsAppLink } from '../../utils/logic';

export const WhatsAppFloatingBtn: React.FC = () => {
  const { cmsConfig } = useApp();

  const whatsAppLink = generateWhatsAppLink(
    cmsConfig.contactWhatsApp,
    'Salam! I am viewing TaisarTownHub website and would like instant consultation on Taiser Town Scheme 45 plots.'
  );

  return (
    <aside aria-label="Contact via WhatsApp" className="fixed bottom-6 left-6 z-40">
      <a
        href={whatsAppLink}
        target="_blank" rel="noopener noreferrer"
        className="group flex items-center gap-2.5 bg-emerald-600 hover:bg-emerald-500 text-white pl-3.5 pr-4 py-3 rounded-full shadow-xl shadow-emerald-950/60 transition-all hover:scale-105 border border-emerald-400/40 cursor-pointer"
        title="Chat with TaisarTownHub Agent on WhatsApp"
      >
        <span className="relative flex h-3 w-3">
          <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-300 opacity-75"></span>
          <span className="relative inline-flex rounded-full h-3 w-3 bg-white"></span>
        </span>
        <MessageCircle className="w-5 h-5 fill-current text-white" />
        <span className="text-xs font-bold tracking-wide hidden sm:inline-block">
          WhatsApp Advisory
        </span>
      </a>
    </aside>
  );
};



