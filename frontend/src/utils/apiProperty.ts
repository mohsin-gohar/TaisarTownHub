import type { Property, PropertyStatus, PropertyType } from '../types';
import { formatPKRCurrency } from './logic';

/**
 * Maps a governed `GET /api/properties` public payload (camelCase Laravel
 * attributes) onto the frontend `Property` view model.
 *
 * The API is the source of truth for which listings may appear publicly
 * (agency-owned + approved + public + sale). This mapper only normalises
 * presentation fields and never adds private/ERP data.
 */

const PROPERTY_TYPES: readonly PropertyType[] = [
  'Residential Plot',
  'Commercial Plot',
  'House / Villa',
  'Apartment',
  'Commercial Shop',
  'Farmhouse',
];

const PROPERTY_STATUSES: readonly PropertyStatus[] = [
  'Available',
  'Reserved',
  'Under Negotiation',
  'Under Deal',
  'Sold',
  'Transferred',
  'On Hold',
  'Cancelled',
  'Hot Deal',
  'Under Offer',
];

function text(value: unknown): string {
  return typeof value === 'string' ? value.trim() : '';
}

function optionalText(value: unknown): string | undefined {
  const out = text(value);
  return out === '' ? undefined : out;
}

function numberValue(value: unknown): number {
  const n = typeof value === 'number' ? value : Number(value);
  return Number.isFinite(n) ? n : 0;
}

function optionalNumber(value: unknown): number | undefined {
  if (value === null || value === undefined || value === '') return undefined;
  const n = Number(value);
  return Number.isFinite(n) ? n : undefined;
}

function stringList(value: unknown): string[] {
  if (!Array.isArray(value)) return [];
  return value.filter((item): item is string => typeof item === 'string' && item.trim() !== '');
}

export function normalizePropertyPhase(value: unknown): Property['phase'] {
  const raw = text(value).toLowerCase();
  const digits = raw.replace(/[^0-9]/g, '');
  if (digits === '1') return 'Phase 1';
  if (digits === '2') return 'Phase 2';
  if (digits === '3') return 'Phase 3';
  return undefined;
}

export function normalizePropertyType(value: unknown): PropertyType {
  const raw = text(value);
  if (PROPERTY_TYPES.includes(raw as PropertyType)) return raw as PropertyType;

  const lower = raw.toLowerCase();
  if (lower.includes('shop') || lower.includes('retail')) return 'Commercial Shop';
  if (lower.includes('apartment') || lower.includes('flat')) return 'Apartment';
  if (lower.includes('farm')) return 'Farmhouse';
  if (lower.includes('villa') || lower.includes('house') || lower.includes('duplex') || lower.includes('home')) {
    return 'House / Villa';
  }
  if (lower.includes('commercial')) return 'Commercial Plot';
  return 'Residential Plot';
}

export function normalizePropertyStatus(value: unknown): PropertyStatus {
  const raw = text(value);
  if (PROPERTY_STATUSES.includes(raw as PropertyStatus)) return raw as PropertyStatus;
  return 'Available';
}

export function mapApiProperty(raw: Record<string, unknown>): Property {
  const pricePKR = numberValue(raw.pricePKR);
  const sector = text(raw.sector);
  const location = text(raw.location)
    || [sector, 'Taiser Town Scheme 45', 'Karachi'].filter(Boolean).join(', ');
  const createdDate = text(raw.createdDate) || text(raw.created_at).slice(0, 10);

  return {
    id: text(raw.id),
    title: text(raw.title) || 'Property listing',
    description: text(raw.description),
    pricePKR,
    formattedPrice: text(raw.formattedPrice) || formatPKRCurrency(pricePKR),
    location,
    phase: normalizePropertyPhase(raw.phase),
    sector,
    block: optionalText(raw.block),
    streetNumber: optionalText(raw.streetNumber),
    plotNumber: optionalText(raw.plotNumber),
    fileNumber: optionalText(raw.fileNumber),
    sizeSqYards: numberValue(raw.sizeSqYards),
    sizeUnit: raw.sizeUnit === 'Marla' ? 'Marla' : raw.sizeUnit === 'Sq. Yds' ? 'Sq. Yds' : undefined,
    frontFeet: optionalNumber(raw.frontFeet),
    frontMeasurementFt: optionalNumber(raw.frontMeasurementFt),
    depthFeet: optionalNumber(raw.depthFeet),
    depthMeasurementFt: optionalNumber(raw.depthMeasurementFt),
    roadWidthFeet: optionalNumber(raw.roadWidthFeet),
    roadWidthFt: optionalNumber(raw.roadWidthFt),
    isRoadFacing: raw.isRoadFacing === true || raw.isRoadFacing === 1,
    is100ftRoadFacing: raw.is100ftRoadFacing === true || raw.is100ftRoadFacing === 1,
    type: normalizePropertyType(raw.type),
    status: normalizePropertyStatus(raw.status),
    isFeatured: raw.isFeatured === true || raw.isFeatured === 1,
    images: stringList(raw.images),
    videoUrl: optionalText(raw.videoUrl),
    features: stringList(raw.features),
    bedrooms: optionalNumber(raw.bedrooms),
    bathrooms: optionalNumber(raw.bathrooms),
    possessionReady: raw.possessionReady === true || raw.possessionReady === 1,
    mdaApproved: raw.mdaApproved === true || raw.mdaApproved === 1,
    cornerPlot: raw.cornerPlot === true || raw.cornerPlot === 1,
    westOpen: raw.westOpen === true || raw.westOpen === 1,
    parkFacing: raw.parkFacing === true || raw.parkFacing === 1,
    mainBoulevard: raw.mainBoulevard === true || raw.mainBoulevard === 1,
    createdDate,
    contactPerson: text(raw.contactPerson),
    contactPhone: text(raw.contactPhone),
    contactWhatsApp: text(raw.contactWhatsApp),
    agencyName: optionalText(raw.agencyName),
  };
}
