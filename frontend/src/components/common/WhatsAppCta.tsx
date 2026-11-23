import React from 'react';
import { MessageCircle } from 'lucide-react';

export interface WhatsAppCtaProps {
  /** A URL produced by buildWhatsAppLink / resolveWhatsAppLink. */
  href: string;
  label?: string;
  ariaLabel?: string;
  title?: string;
  variant?: 'solid' | 'outline' | 'subtle' | 'icon';
  className?: string;
}

/**
 * Consistent, accessible WhatsApp contact button for public marketplace pages.
 * Never rendered when no usable public number exists — callers resolve the
 * destination first and only mount this component on success.
 */
export const WhatsAppCta: React.FC<WhatsAppCtaProps> = ({
  href,
  label = 'WhatsApp',
  ariaLabel,
  title,
  variant = 'solid',
  className = '',
}) => {
  const styles: Record<NonNullable<WhatsAppCtaProps['variant']>, string> = {
    solid: 'bg-emerald-600 hover:bg-emerald-500 text-white border border-emerald-400/40',
    outline: 'border border-emerald-500/50 text-emerald-300 hover:bg-emerald-500/10',
    subtle: 'text-slate-300 hover:text-white',
    icon: 'bg-emerald-600 hover:bg-emerald-500 text-white border border-emerald-400/40 p-2.5',
  };
  const shape = variant === 'icon' ? 'rounded-xl' : 'rounded-xl px-4 py-2.5 text-sm font-bold';
  const accessibleLabel = ariaLabel || `${label} (opens in a new tab)`;

  return (
    <a
      href={href}
      target="_blank"
      rel="noopener noreferrer"
      aria-label={accessibleLabel}
      title={title || accessibleLabel}
      className={`inline-flex items-center justify-center gap-2 transition ${styles[variant]} ${shape} ${className}`}
    >
      <MessageCircle className="w-4 h-4" aria-hidden="true" />
      {variant !== 'icon' && <span>{label}</span>}
    </a>
  );
};
