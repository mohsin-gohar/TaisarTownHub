import React, { useEffect, useState } from 'react';
import { Check, Copy, ExternalLink, Scale, X } from 'lucide-react';
import { PublicProperty } from '../../types';
import { ImageWithFallback } from '../common/ImageWithFallback';
import { buildCompareShareUrl } from '../../utils/propertyCompare';

interface CompareModalProps {
  items: PublicProperty[];
  onClose: () => void;
  onRemove: (id: string) => void;
  onOpenProperty: (property: PublicProperty) => void;
}

interface CompareRow {
  label: string;
  value: (property: PublicProperty) => string;
  /** Adds a subtle badge on the best value in the row. */
  best?: (property: PublicProperty) => boolean;
}

const money = (value: number) => `PKR ${Math.round(value).toLocaleString()}`;

const rows: CompareRow[] = [
  {
    label: 'Demand price',
    value: (p) => p.formattedPrice || money(p.pricePKR),
    best: (p) => p.pricePKR > 0,
  },
  {
    label: 'Price / Sq. Yd.',
    value: (p) => (p.sizeSqYards > 0 ? money(p.pricePKR / p.sizeSqYards) : '—'),
  },
  {
    label: 'Size',
    value: (p) => `${p.sizeSqYards} Sq. Yd.`,
  },
  {
    label: 'Type',
    value: (p) => p.type || '—',
  },
  {
    label: 'Phase',
    value: (p) => p.phase || '—',
  },
  {
    label: 'Sector / block',
    value: (p) => [p.sector, p.block].filter(Boolean).join(' · ') || '—',
  },
  {
    label: 'Plot / file no.',
    value: (p) => [p.plotNumber && `Plot ${p.plotNumber}`, p.fileNumber && `File ${p.fileNumber}`].filter(Boolean).join(' · ') || '—',
  },
  {
    label: 'Possession',
    value: (p) => (p.possessionReady ? 'Possession ready' : 'On request'),
  },
  {
    label: 'Status',
    value: (p) => p.status || '—',
  },
  {
    label: 'Bedrooms',
    value: (p) => (p.bedrooms ? String(p.bedrooms) : '—'),
  },
  {
    label: 'Bathrooms',
    value: (p) => (p.bathrooms ? String(p.bathrooms) : '—'),
  },
  {
    label: 'Road width',
    value: (p) => (p.roadWidthFeet || p.roadWidthFt ? `${p.roadWidthFeet || p.roadWidthFt} ft` : '—'),
  },
  {
    label: 'Features',
    value: (p) => (Array.isArray(p.features) && p.features.length ? p.features.join(', ') : '—'),
  },
];

export const CompareModal: React.FC<CompareModalProps> = ({
  items,
  onClose,
  onRemove,
  onOpenProperty,
}) => {
  const [copied, setCopied] = useState(false);
  const [copyFailed, setCopyFailed] = useState(false);

  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose();
    };
    document.addEventListener('keydown', onKey);
    const previous = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      document.removeEventListener('keydown', onKey);
      document.body.style.overflow = previous;
    };
  }, [onClose]);

  const shareUrl = buildCompareShareUrl(
    `${window.location.origin}/properties`,
    items.map((item) => item.id)
  );

  const copyShareLink = async () => {
    try {
      await navigator.clipboard.writeText(shareUrl);
      setCopied(true);
      setCopyFailed(false);
      window.setTimeout(() => setCopied(false), 2000);
    } catch {
      // Clipboard access can be denied (permissions, non-secure origin). Never
      // leave the user without the link: expose it for manual copying.
      setCopied(false);
      setCopyFailed(true);
    }
  };

  const lowestPrice = Math.min(...items.map((item) => item.pricePKR).filter((price) => price > 0));

  return (
    <div
      className="fixed inset-0 z-[60] flex items-center justify-center bg-black/80 p-2 backdrop-blur-sm sm:p-6"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget) onClose();
      }}
    >
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="compare-dialog-title"
        className="flex max-h-[94vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl border border-[#E7C878]/35 bg-[#07141E] shadow-2xl"
      >
        <header className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800 bg-[#0B1220] px-4 py-3 sm:px-6">
          <div>
            <p className="text-[10px] font-bold uppercase tracking-[0.2em] text-[#E7C878]">Shortlist analysis</p>
            <h2 id="compare-dialog-title" className="font-serif-luxury text-lg font-bold text-white sm:text-xl">
              Compare {items.length} properties
            </h2>
          </div>
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={copyShareLink}
              className="inline-flex items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-[11px] font-semibold text-slate-200 transition hover:border-[#E7C878]/50 hover:text-white"
            >
              {copied ? <Check className="h-3.5 w-3.5 text-emerald-400" /> : <Copy className="h-3.5 w-3.5" />}
              {copied ? 'Link copied' : 'Copy share link'}
            </button>
            <button
              type="button"
              onClick={onClose}
              aria-label="Close comparison"
              className="rounded-lg border border-slate-700 bg-slate-800 p-2 text-slate-300 transition hover:text-white"
            >
              <X className="h-4 w-4" />
            </button>
          </div>
        </header>

        {copyFailed && (
          <div className="flex flex-col gap-1.5 border-b border-slate-800 bg-[#0E1724] px-4 py-2.5 sm:px-6" role="status">
            <span className="text-[10px] font-semibold uppercase tracking-wider text-amber-300">
              Clipboard access was blocked — copy this shortlist link manually
            </span>
            <input
              type="text"
              readOnly
              aria-label="Shortlist share link"
              value={shareUrl}
              onFocus={(event) => event.currentTarget.select()}
              className="w-full rounded-lg border border-slate-700 bg-[#07141E] px-3 py-1.5 font-mono text-[11px] text-slate-200 focus:border-[#E7C878] focus:outline-none"
            />
          </div>
        )}

        <div className="overflow-auto">
          <table className="w-full min-w-[680px] border-collapse text-left">
            <thead className="sticky top-0 z-10 bg-[#0E1724]">
              <tr>
                <th scope="col" className="w-36 border-b border-r border-slate-800 p-3 align-bottom text-[10px] font-bold uppercase tracking-wider text-slate-500">
                  <span className="inline-flex items-center gap-1.5">
                    <Scale className="h-3.5 w-3.5 text-[#E7C878]" /> Attribute
                  </span>
                </th>
                {items.map((item) => (
                  <th key={item.id} scope="col" className="min-w-[220px] border-b border-slate-800 p-3 align-top">
                    <div className="relative">
                      <ImageWithFallback
                        src={item.images?.[0]}
                        alt={item.title}
                        className="mb-2 h-28 w-full rounded-lg object-cover"
                      />
                      <button
                        type="button"
                        onClick={() => onRemove(item.id)}
                        aria-label={`Remove ${item.title}`}
                        className="absolute right-1.5 top-1.5 rounded-md bg-black/70 p-1 text-slate-300 backdrop-blur transition hover:text-rose-300"
                      >
                        <X className="h-3.5 w-3.5" />
                      </button>
                      <p className="line-clamp-2 text-sm font-bold text-white">{item.title}</p>
                      <p className="mt-1 truncate text-[11px] text-slate-400">{item.location}</p>
                      <button
                        type="button"
                        onClick={() => onOpenProperty(item)}
                        className="mt-2 inline-flex items-center gap-1 text-[11px] font-semibold text-[#E7C878] hover:underline"
                      >
                        Open dossier <ExternalLink className="h-3 w-3" />
                      </button>
                    </div>
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((row, index) => (
                <tr key={row.label} className={index % 2 === 0 ? 'bg-[#07141E]' : 'bg-[#0D1624]'}>
                  <th scope="row" className="border-b border-r border-slate-800 px-3 py-2.5 text-[11px] font-semibold text-slate-400">
                    {row.label}
                  </th>
                  {items.map((item) => {
                    const best = row.label === 'Demand price' && item.pricePKR === lowestPrice && items.length > 1;
                    return (
                      <td key={item.id} className="border-b border-slate-800 px-3 py-2.5 text-xs text-slate-200">
                        <span className={best ? 'inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2 py-0.5 font-semibold text-emerald-300' : ''}>
                          {row.value(item)}
                          {best && <span className="text-[9px] font-bold uppercase tracking-wider">Lowest</span>}
                        </span>
                      </td>
                    );
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <footer className="flex flex-wrap items-center justify-between gap-2 border-t border-slate-800 bg-[#0B1220] px-4 py-3 text-[11px] text-slate-500 sm:px-6">
          <span>Comparison uses only publicly published listing data. Verify ownership and documents before payment.</span>
          <button
            type="button"
            onClick={onClose}
            className="rounded-lg bg-[#E7C878] px-4 py-2 text-[11px] font-bold text-[#0B1220] transition hover:bg-[#F3C464]"
          >
            Back to listings
          </button>
        </footer>
      </div>
    </div>
  );
};
