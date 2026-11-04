import {
  Property,
  PublicProperty,
  PropertyType,
  Transaction,
  PropertyFileItem,
  Project,
  UserRole
} from '../types';
import {
  ROLE_PERMISSIONS as DOMAIN_ROLE_PERMISSIONS,
  roleHasPermission,
  type Permission,
} from '../domain/permissions';
import { computeFinancials as calculateFinancialsDomain } from '../domain/transactions';

/**
 * Format currency into standard Pakistani Rupees notation (Lacs / Crores / Thousands)
 */
export function formatPKRCurrency(amount: number): string {
  if (isNaN(amount) || amount === 0) return 'PKR 0';

  // Negative values (refunds / reversals) render as an explicit signed amount.
  if (amount < 0) return `-PKR ${formatPKRCurrency(Math.abs(amount)).replace(/^PKR\s/, '')}`;

  if (amount >= 10000000) { // 1 Crore = 10,000,000
    const crore = amount / 10000000;
    return `PKR ${crore % 1 === 0 ? crore.toFixed(0) : crore.toFixed(2)} Crore`;
  }
  
  if (amount >= 100000) { // 1 Lac = 100,000
    const lac = amount / 100000;
    return `PKR ${lac % 1 === 0 ? lac.toFixed(0) : lac.toFixed(2)} Lacs`;
  }
  
  return `PKR ${amount.toLocaleString('en-PK')}`;
}

/**
 * Calculate simple loan / installment breakdown for real estate clients
 */
export function calculateInstallmentPlan(
  totalPrice: number,
  downPaymentPercent: number = 25,
  tenureYears: number = 3
) {
  const downPayment = (totalPrice * downPaymentPercent) / 100;
  const remainingAmount = totalPrice - downPayment;
  const totalMonths = tenureYears * 12;
  const monthlyInstallment = totalMonths > 0 ? remainingAmount / totalMonths : 0;

  return {
    downPayment: Math.round(downPayment),
    remainingAmount: Math.round(remainingAmount),
    totalMonths,
    monthlyInstallment: Math.round(monthlyInstallment),
  };
}

/**
 * Generate official WhatsApp click-to-chat link
 */
export function generateWhatsAppLink(rawPhone: string, textMessage: string): string {
  const encodedMsg = encodeURIComponent(textMessage);

  // Normalize to WhatsApp international format (digits only, no '+').
  let digits = (rawPhone || '').replace(/[^0-9]/g, '');
  if (digits.startsWith('0092')) digits = `92${digits.slice(4)}`; // 0092... → 92...
  else if (digits.startsWith('0')) digits = `92${digits.slice(1)}`; // 0300... → 92300...
  else if (digits.length === 10) digits = `92${digits}`; // 3001234567 → 923001234567

  // Implausible numbers degrade to a number-less WhatsApp chat link
  // rather than a broken wa.me/<garbage> URL.
  if (digits.length < 10 || digits.length > 15) return `https://wa.me/?text=${encodedMsg}`;

  return `https://wa.me/${digits}?text=${encodedMsg}`;
}

export interface FilterCriteria {
  searchTerm?: string;
  type?: PropertyType | 'All';
  phase?: 'Phase 1' | 'Phase 2' | 'Phase 3' | 'All';
  sector?: string | 'All';
  minPrice?: number;
  maxPrice?: number;
  sizeSqYards?: number | 'All';
  possessionOnly?: boolean;
}

/**
 * Helper to determine Phase of a property in Taiser Town Scheme 45
 */
export function getPropertyPhase(property: Property): 'Phase 1' | 'Phase 2' | 'Phase 3' {
  if (property.phase) return property.phase as 'Phase 1' | 'Phase 2' | 'Phase 3';
  const sec = property.sector || '';
  if (['70-D', '70', '71', '72', '50', '30-A', '30', '40'].some((s) => sec.includes(s))) {
    return 'Phase 1';
  }
  if (['63', '64', '65', '77', '78', '80'].some((s) => sec.includes(s))) {
    return 'Phase 2';
  }
  return 'Phase 3';
}

/**
 * Filter properties based on user selection
 */
export function filterProperties(
  properties: Property[],
  criteria: FilterCriteria
): Property[] {
  return properties.filter((p) => {
    // Soft-deleted listings never reach the public site (P0-5); the public
    // serializer strips deletedAt, so the exclusion must happen here.
    if (p.deletedAt) return false;

    // Phase check
    if (criteria.phase && criteria.phase !== 'All') {
      const propPhase = getPropertyPhase(p);
      if (propPhase !== criteria.phase) {
        return false;
      }
    }

    // Search term check
    if (criteria.searchTerm && criteria.searchTerm.trim() !== '') {
      const term = criteria.searchTerm.toLowerCase();
      const matchTitle = p.title.toLowerCase().includes(term);
      const matchDesc = p.description.toLowerCase().includes(term);
      const matchLocation = p.location.toLowerCase().includes(term);
      const matchSector = p.sector.toLowerCase().includes(term);
      const matchPlot = p.plotNumber ? p.plotNumber.toLowerCase().includes(term) : false;
      const matchFile = p.fileNumber ? p.fileNumber.toLowerCase().includes(term) : false;
      const matchPhase = p.phase ? p.phase.toLowerCase().includes(term) : false;
      if (!matchTitle && !matchDesc && !matchLocation && !matchSector && !matchPlot && !matchFile && !matchPhase) {
        return false;
      }
    }

    // Type check
    if (criteria.type && criteria.type !== 'All' && p.type !== criteria.type) {
      return false;
    }

    // Sector check
    if (criteria.sector && criteria.sector !== 'All' && !p.sector.includes(criteria.sector)) {
      return false;
    }

    // Min price check
    if (criteria.minPrice !== undefined && criteria.minPrice > 0 && p.pricePKR < criteria.minPrice) {
      return false;
    }

    // Max price check
    if (criteria.maxPrice !== undefined && criteria.maxPrice > 0 && p.pricePKR > criteria.maxPrice) {
      return false;
    }

    // Size check
    if (criteria.sizeSqYards && criteria.sizeSqYards !== 'All' && p.sizeSqYards !== Number(criteria.sizeSqYards)) {
      return false;
    }

    // Possession check
    if (criteria.possessionOnly && !p.possessionReady) {
      return false;
    }

    return true;
  });
}

/**
 * Validate inquiry submission
 */
export function validateInquiryForm(data: {
  fullName: string;
  phone: string;
  email?: string;
  message: string;
}): { isValid: boolean; error?: string } {
  if (!data.fullName || data.fullName.trim().length < 2) {
    return { isValid: false, error: 'Please enter a valid full name (minimum 2 characters).' };
  }
  if (!data.phone || data.phone.trim().length < 7) {
    return { isValid: false, error: 'Please enter a valid phone or WhatsApp number.' };
  }
  if (data.email && data.email.trim() !== '') {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(data.email)) {
      return { isValid: false, error: 'Please enter a valid email address.' };
    }
  }
  if (!data.message || data.message.trim().length < 5) {
    return { isValid: false, error: 'Please provide at least a brief message or requirement.' };
  }
  return { isValid: true };
}

/**
 * Mask sensitive CNIC numbers for privacy in UI views (e.g. 42101-1234567-1 -> 42101-*******-1)
 */
export function maskCNIC(rawCnic: string): string {
  if (!rawCnic) return 'N/A';
  const clean = rawCnic.trim();
  // Typical Pakistani CNIC pattern 13 digits with or without dashes: 42101-1234567-1
  const digits = clean.replace(/[^0-9]/g, '');
  if (digits.length === 13) {
    const prefix = digits.substring(0, 5);
    const suffix = digits.substring(12, 13);
    return `${prefix}-*******-${suffix}`;
  }
  if (clean.length > 6) {
    return `${clean.substring(0, 4)}****${clean.substring(clean.length - 2)}`;
  }
  return '*****';
}

/**
 * Mask bank account / IBAN numbers for privacy views: only the trailing 4
 * digits stay visible ("•••• •••• 1234"). Full reveal requires the
 * `view_sensitive_cnic` permission (same gate as CNIC).
 */
export function maskAccountNumber(raw: string | undefined | null): string {
  if (!raw) return 'N/A';
  const clean = String(raw).replace(/\s+/g, '');
  if (clean.length <= 4) return '••••';
  return `•••• •••• ${clean.slice(-4)}`;
}

/**
 * Check if a property with the same Plot Number and Sector already exists to prevent duplicate entries
 */
export function checkDuplicateProperty(
  properties: Property[],
  plotNumber?: string,
  sector?: string,
  block?: string,
  excludeId?: string
): { isDuplicate: boolean; duplicateProperty?: Property } {
  if (!plotNumber || !sector || plotNumber.trim() === '' || sector.trim() === '') {
    return { isDuplicate: false };
  }

  const cleanPlot = plotNumber.trim().toLowerCase();
  const cleanSector = sector.trim().toLowerCase();
  const cleanBlock = (block || '').trim().toLowerCase();

  const found = properties.find((p) => {
    if (excludeId && p.id === excludeId) return false;
    const samePlot = (p.plotNumber || '').trim().toLowerCase() === cleanPlot;
    const sameSector = p.sector.trim().toLowerCase() === cleanSector;
    const sameBlock = cleanBlock === '' || (p.block || '').trim().toLowerCase() === cleanBlock;
    return samePlot && sameSector && sameBlock;
  });

  return { isDuplicate: !!found, duplicateProperty: found };
}

/**
 * Calculate dealer commission based on agreement
 * - Fixed: fixed amount (e.g. 20,000 PKR from handwritten note example)
 * - Percentage: rate % of total deal amount
 * - Custom: directly provided rate
 */
export function calculateCommission(
  dealAmountOrType: number | string,
  typeOrRate?: string | number,
  rateOrAmount?: number
): number {
  let dealAmount = 0;
  let type = 'Fixed';
  let rate = 0;

  // Support both calling conventions:
  // calculateCommission(dealAmount, type, rate)
  // calculateCommission(type, rate, dealAmount)
  if (typeof dealAmountOrType === 'number') {
    dealAmount = Number.isFinite(dealAmountOrType) ? Math.max(0, dealAmountOrType) : 0;
    type = (typeOrRate as string) || 'Fixed';
    rate = rateOrAmount ?? 0;
  } else {
    type = dealAmountOrType || 'Fixed';
    rate = (typeOrRate as number) ?? 0;
    dealAmount = Number.isFinite(rateOrAmount as number) ? Math.max(0, rateOrAmount as number) : 0;
    // In (type, rate, dealAmount) form, rateOrAmount is actually dealAmount — handled above by re-reading:
    if (typeof rateOrAmount === 'number' && typeof typeOrRate === 'number') {
      // (type, rate, dealAmount)
      dealAmount = Math.max(0, rateOrAmount);
      rate = (typeOrRate as number) ?? 0;
    }
  }

  // Normalise: if called as calculateCommission(type, rate, dealAmount) with first arg string
  // we already set dealAmount from the third arg. If called as calculateCommission(dealAmount, type, rate)
  // dealAmount set from first arg. Disambiguate by checking the third arg presence for percentage.
  if (!Number.isFinite(rate)) rate = 0;
  rate = Math.max(0, rate);

  const normalizedType = String(type).toLowerCase();
  if (normalizedType === 'percentage') {
    if (!Number.isFinite(dealAmount) || dealAmount <= 0) return 0;
    const pct = Math.max(0, rate);
    return Math.round((dealAmount * pct) / 100);
  }
  if (normalizedType === 'custom') {
    return Math.round(rate);
  }
  // Fixed (and any unknown type) returns the fixed amount
  return Math.round(rate);
}

/**
 * Calculate aggregated ERP metrics from the transaction + ledger state (P1-5).
 * Delegates to the domain module (ledger-backed money math) and keeps the
 * legacy alias field names older UI still reads.
 */
export function calculateERPFinancials(
  transactions: Transaction[],
  payments: import('../types').PaymentEntry[] = [],
  commissions: import('../types').CommissionEntry[] = []
) {
  const f = calculateFinancialsDomain(transactions, payments, commissions);
  return {
    ...f,
    totalDealVolume: f.totalSalesVolume,
    totalCollected: f.totalCollected,
    totalAdvancesCollected: f.totalCollected,
    totalPendingReceivables: f.totalPendingReceivables,
    pendingReceivables: f.totalPendingReceivables,
    outstandingReceivables: f.totalPendingReceivables,
    totalCommissionEarned: f.totalCommissionAccrued,
    commissionEarned: f.totalCommissionAccrued,
    totalCommissionPaid: f.totalCommissionPaid,
    commissionPaid: f.totalCommissionPaid,
    totalCommissionsPayable: f.totalCommissionPayable,
    totalCommissionPayable: f.totalCommissionPayable,
    commissionPayable: f.totalCommissionPayable,
    commissionOutstanding: f.totalCommissionPayable,
    pendingDealsCount: f.activeDealsCount,
    totalDealsCount: f.activeDealsCount,
    totalTransactionsCount: transactions.length,
  };
}

/**
 * Check dynamic usage of a media file across properties, projects, and documents
 */
export function calculateMediaUsageCount(
  mediaUrl: string,
  properties: Property[],
  projects: Project[],
  documents: PropertyFileItem[] = []
): { count: number; referencingTitles: string[] } {
  const titles: string[] = [];

  for (const p of properties) {
    if (p.deletedAt) continue;
    if (p.images.includes(mediaUrl)) {
      titles.push(p.title);
    }
  }

  for (const pr of projects) {
    if (pr.deletedAt) continue;
    if (pr.coverImage === mediaUrl || pr.galleryImages.includes(mediaUrl)) {
      titles.push(pr.title);
    }
  }

  for (const doc of documents) {
    if (doc.url === mediaUrl || doc.documentUrl === mediaUrl) {
      titles.push(doc.title);
    }
  }

  return { count: titles.length, referencingTitles: titles };
}

/**
 * Role-Based Permissions Checker (centralised).
 * Delegates to the canonical domain matrix (src/domain/permissions.ts) and
 * accepts both the canonical role ids and the legacy display spellings, so
 * older callsites keep working until they are migrated.
 */
export const ROLE_PERMISSIONS: Record<string, string[]> = Object.fromEntries(
  Object.entries(DOMAIN_ROLE_PERMISSIONS).map(([role, perms]) => [role, [...perms]]),
);

export function hasPermission(role: UserRole | string, permission: string): boolean {
  return roleHasPermission(role, [permission as Permission]);
}

export function hasAnyPermission(role: UserRole | string, permissions: string[]): boolean {
  return permissions.some((p) => roleHasPermission(role, [p as Permission]));
}

/**
 * Central permission check that also accepts an AuthUser with explicit permissions.
 */
export function userHasPermission(user: { role: UserRole | string; permissions?: string[] } | null | undefined, permission: string): boolean {
  if (!user) return false;
  if (user.permissions && user.permissions.includes('all')) return true;
  if (user.permissions && user.permissions.includes(permission)) return true;
  return hasPermission(user.role, permission);
}
/**
 * Public serializers — ALLOWLIST builders.
 *
 * Only explicitly-listed public fields are copied into the public view model.
 * This is stronger than a denylist (delete-list): future private fields added
 * to `Property` can never leak to the public site by accident, and removing
 * fields from this list is enough to revoke public visibility.
 *
 * Keep in sync with the `PublicProperty` / `Project` types and with
 * scripts/sensitive-strings.json + scripts/check-bundle.mjs.
 */
const PUBLIC_PROPERTY_KEYS = [
  'id', 'title', 'description', 'pricePKR', 'formattedPrice', 'location',
  'phase', 'sector', 'block', 'streetNumber', 'plotNumber', 'fileNumber',
  'sizeSqYards', 'sizeUnit', 'frontFeet', 'frontMeasurementFt', 'depthFeet',
  'depthMeasurementFt', 'roadWidthFeet', 'roadWidthFt', 'isRoadFacing',
  'is100ftRoadFacing', 'type', 'status', 'isFeatured', 'images', 'videoUrl',
  'features', 'bedrooms', 'bathrooms', 'possessionReady', 'mdaApproved',
  'cornerPlot', 'westOpen', 'parkFacing', 'mainBoulevard', 'createdDate',
  'contactPerson', 'contactPhone', 'contactWhatsApp',
] as const;

const PUBLIC_PROJECT_KEYS = [
  'id', 'title', 'tagline', 'category', 'location', 'sector', 'description',
  'status', 'isFeatured', 'coverImage', 'galleryImages', 'videoUrl',
  'facilities', 'documents', 'startingPricePKR', 'plotSizes',
  'masterPlanImage', 'completionYear', 'developerName', 'createdDate',
] as const;

function pick<T extends object, K extends readonly (keyof T)[]>(obj: T | undefined | null, keys: K): Pick<T, K[number]> | undefined {
  if (!obj) return undefined;
  const out = {} as Pick<T, K[number]>;
  for (const k of keys) {
    if (k in obj) out[k] = obj[k];
  }
  return out;
}

export function toPublicProperty(p: Property): PublicProperty {
  const out = pick(p, PUBLIC_PROPERTY_KEYS) as PublicProperty;
  return out;
}

export function toPublicProperties(list: Property[]): PublicProperty[] {
  return (list || []).map(toPublicProperty);
}

export function toPublicProject(p: Project): Project {
  // Projects currently carry no ERP-private fields, but the allowlist guards
  // against future additions leaking (e.g. internal margin notes).
  return (pick(p, PUBLIC_PROJECT_KEYS) ?? p) as Project;
}

export function toPublicProjects(list: Project[]): Project[] {
  return (list || []).map(toPublicProject);
}

/**
 * Validate a non-negative finite number.
 */
export function isFiniteNonNegative(n: unknown): n is number {
  return typeof n === 'number' && Number.isFinite(n) && n >= 0;
}

/**
 * Media sale economics (dealer/agency file attribution).
 *
 * Profit = selling price - cost price. When the asset has no recorded cost
 * (e.g. agency-owned stock), a default 30% commissionable margin is assumed
 * so the sale still produces an auditable profit figure instead of zero.
 */
export function calculateMediaSaleProfit(
  sellingPricePKR: unknown,
  costPricePKR?: unknown
): { ok: boolean; error?: string; sellingPrice: number; costPrice: number; profit: number; marginPct: number } {
  if (!isFiniteNonNegative(sellingPricePKR) || (sellingPricePKR as number) <= 0) {
    return { ok: false, error: 'Selling price must be a positive amount in PKR.', sellingPrice: 0, costPrice: 0, profit: 0, marginPct: 0 };
  }
  const sellingPrice = Math.round(sellingPricePKR as number);
  let costPrice: number;
  if (costPricePKR === undefined || costPricePKR === null || costPricePKR === '') {
    costPrice = Math.round(sellingPrice * 0.7); // default 30% agency margin
  } else if (isFiniteNonNegative(costPricePKR)) {
    costPrice = Math.round(costPricePKR as number);
  } else {
    return { ok: false, error: 'Cost price must be a non-negative amount in PKR.', sellingPrice, costPrice: 0, profit: 0, marginPct: 0 };
  }
  if (costPrice > sellingPrice) {
    return { ok: false, error: 'Cost price cannot exceed the selling price.', sellingPrice, costPrice, profit: 0, marginPct: 0 };
  }
  const profit = sellingPrice - costPrice;
  const marginPct = sellingPrice > 0 ? Math.round((profit / sellingPrice) * 1000) / 10 : 0;
  return { ok: true, sellingPrice, costPrice, profit, marginPct };
}

/**
 * Aggregate sold media assets into a per-seller attribution summary
 * (who sold what, how many files, and how much profit was earned).
 */
export function summarizeMediaSales(
  items: Array<Pick<MediaItemSaleFields, 'soldBy' | 'soldByRole' | 'profitPKR' | 'soldPricePKR'>>
): Array<{ seller: string; role: string; filesSold: number; totalSaleValue: number; totalProfit: number }> {
  const bySeller = new Map<string, { seller: string; role: string; filesSold: number; totalSaleValue: number; totalProfit: number }>();
  for (const item of items || []) {
    if (!item?.soldBy) continue;
    const key = item.soldBy;
    const entry = bySeller.get(key) ?? { seller: item.soldBy, role: item.soldByRole ?? '', filesSold: 0, totalSaleValue: 0, totalProfit: 0 };
    entry.filesSold += 1;
    entry.totalSaleValue += isFiniteNonNegative(item.soldPricePKR) ? (item.soldPricePKR as number) : 0;
    entry.totalProfit += isFiniteNonNegative(item.profitPKR) ? (item.profitPKR as number) : 0;
    bySeller.set(key, entry);
  }
  return [...bySeller.values()].sort((a, b) => b.totalProfit - a.totalProfit);
}

/** Structural subset used by summarizeMediaSales (avoids importing full type). */
export interface MediaItemSaleFields {
  soldBy?: string;
  soldByRole?: string;
  profitPKR?: number;
  soldPricePKR?: number;
}
