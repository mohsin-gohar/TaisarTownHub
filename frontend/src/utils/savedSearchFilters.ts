/**
 * Mapping between the marketplace listing UI state and the saved-search
 * filter contract accepted by the Laravel API.
 *
 * The API only stores allow-listed keys. Empty / "All" values are omitted so
 * a saved search never encodes a meaningless filter.
 */

export interface ListingFilterState {
  searchTerm: string;
  type: string;
  phase: string;
  sector: string;
  sizeSqYards: string;
  possessionOnly: boolean;
  minPrice: number;
  maxPrice: number;
}

export function savedSearchFiltersFromState(state: ListingFilterState): Record<string, string | number | boolean> {
  const filters: Record<string, string | number | boolean> = {};

  if (state.searchTerm.trim()) filters.search = state.searchTerm.trim();
  if (state.type && state.type !== 'All') filters.type = state.type;
  if (state.phase && state.phase !== 'All') filters.phase = state.phase;
  if (state.sector && state.sector !== 'All') filters.sector = state.sector;
  if (state.sizeSqYards && state.sizeSqYards !== 'All') filters.sizeSqYards = Number(state.sizeSqYards);
  if (state.possessionOnly) filters.possessionOnly = true;
  if (state.minPrice > 0) filters.minPrice = Math.round(state.minPrice);
  if (state.maxPrice > 0) filters.maxPrice = Math.round(state.maxPrice);

  return filters;
}

export interface SavedSearchFilterState {
  searchTerm: string;
  type: string;
  phase: string;
  sector: string;
  sizeSqYards: string;
  possessionOnly: boolean;
  minPrice: number;
  maxPrice: number;
}

export function listingStateFromSavedSearch(filters: Record<string, unknown> | null | undefined): SavedSearchFilterState {
  const f = filters ?? {};
  const asString = (value: unknown, fallback: string) =>
    typeof value === 'string' && value.trim() ? value : fallback;
  const asNumber = (value: unknown) => {
    const parsed = Number(value);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
  };

  return {
    searchTerm: asString(f.search, ''),
    type: asString(f.type, 'All'),
    phase: asString(f.phase, 'All'),
    sector: asString(f.sector, 'All'),
    sizeSqYards: f.sizeSqYards ? String(f.sizeSqYards) : 'All',
    possessionOnly: Boolean(f.possessionOnly),
    minPrice: asNumber(f.minPrice),
    maxPrice: asNumber(f.maxPrice),
  };
}

export function describeListingFilters(state: ListingFilterState): string {
  const parts: string[] = [];
  if (state.sector && state.sector !== 'All') parts.push(state.sector);
  if (state.phase && state.phase !== 'All') parts.push(state.phase);
  if (state.type && state.type !== 'All') parts.push(state.type);
  if (state.sizeSqYards && state.sizeSqYards !== 'All') parts.push(`${state.sizeSqYards} Sq. Yd.`);
  if (state.searchTerm.trim()) parts.push(`"${state.searchTerm.trim()}"`);
  if (state.maxPrice > 0) parts.push(`up to PKR ${Math.round(state.maxPrice).toLocaleString()}`);
  if (state.possessionOnly) parts.push('possession ready');
  return parts.length ? parts.join(' · ') : 'All published listings';
}
