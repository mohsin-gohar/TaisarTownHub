/**
 * Agency-specific WhatsApp contact helpers.
 *
 * These helpers deliberately never fall back to the platform owner's number:
 * a customer enquiry must reach the agency (or its publicly listed property
 * contact) that actually owns the listing.
 */

export type WhatsAppSource = 'property' | 'agent' | 'agency' | 'agency_phone';

export interface WhatsAppContactInput {
  /** Public WhatsApp number attached to a listing (contactWhatsApp). */
  propertyWhatsApp?: string | null;
  /** Explicitly public WhatsApp number of the responsible agent, if exposed. */
  agentWhatsApp?: string | null;
  /** Agency's public WhatsApp number. */
  agencyWhatsApp?: string | null;
  /** Agency's public phone, used as a WhatsApp fallback only when valid. */
  agencyPhone?: string | null;
}

export interface WhatsAppLink {
  url: string;
  /** E.164-like digits used in wa.me (no leading '+'). */
  destination: string;
  source: WhatsAppSource;
}

/**
 * Normalize a public contact number into the digits accepted by wa.me.
 * Returns null for missing, malformed or implausible numbers so callers can
 * hide the CTA instead of emitting a broken link.
 */
export function normalizeWhatsAppNumber(raw?: string | null): string | null {
  const value = (raw ?? '').trim();
  if (!value) return null;

  let digits = value.replace(/\D/g, '');
  if (!digits) return null;

  // International dialling prefixes used in local data.
  if (digits.startsWith('0092')) digits = digits.slice(2); // 0092... -> 92...
  else if (digits.startsWith('00')) digits = digits.slice(2); // generic 00 prefix

  // A repeated country code (e.g. "+92 92 300..." or "0092 0300...") must not
  // become wa.me/9292... . Strip one extra 92 followed by any leading zeros.
  if (digits.startsWith('92') && digits.length >= 12) {
    let local = digits.slice(2).replace(/^0+/, '');
    if (local.startsWith('92')) local = local.slice(2).replace(/^0+/, '');
    if (local.length === 10 && local.startsWith('3')) return `92${local}`;
    digits = `92${local}`;
  }

  // Pakistani local formats.
  if (digits.startsWith('0')) {
    const local = digits.replace(/^0+/, '');
    if (local.length === 10 && local.startsWith('3')) return `92${local}`;
    return null;
  }
  if (digits.length === 10 && digits.startsWith('3')) return `92${digits}`;

  // Other international numbers (already without '+'), 10-15 digits.
  if (digits.length >= 10 && digits.length <= 15 && !digits.startsWith('0')) {
    return digits;
  }
  return null;
}

/** Build a WhatsApp URL, or null when no usable number is available. */
export function buildWhatsAppLink(number: string | null | undefined, message: string): string | null {
  const destination = normalizeWhatsAppNumber(number);
  if (!destination) return null;
  return `https://wa.me/${destination}?text=${encodeURIComponent(message)}`;
}

export const AGENCY_WHATSAPP_MESSAGE =
  'Assalam-o-Alaikum, I found your agency on TaisarTownHub Scheme 45 marketplace. I would like information about your available properties.';

export function propertyWhatsAppMessage(title?: string | null): string {
  const safeTitle = (title ?? '').trim() || 'this property';
  return `Assalam-o-Alaikum, I am interested in ${safeTitle} listed on TaisarTownHub, Scheme 45. Please share details, availability and price.`;
}

/**
 * Resolve the first usable, publicly authorised contact number.
 * Priority: property -> responsible agent -> agency WhatsApp -> agency phone.
 */
export function resolveWhatsAppLink(
  input: WhatsAppContactInput,
  message: string
): WhatsAppLink | null {
  const candidates: Array<[WhatsAppSource, string | null | undefined]> = [
    ['property', input.propertyWhatsApp],
    ['agent', input.agentWhatsApp],
    ['agency', input.agencyWhatsApp],
    ['agency_phone', input.agencyPhone],
  ];
  for (const [source, value] of candidates) {
    const destination = normalizeWhatsAppNumber(value);
    if (destination) {
      return {
        url: `https://wa.me/${destination}?text=${encodeURIComponent(message)}`,
        destination,
        source,
      };
    }
  }
  return null;
}
