import test from 'node:test';
import assert from 'node:assert/strict';
import {
  COMPARE_STORAGE_KEY,
  MAX_COMPARE_ITEMS,
  buildCompareShareUrl,
  parseCompareIdsFromSearch,
  readCompareIds,
  sanitizeCompareIds,
  toggleCompareId,
  writeCompareIds,
} from './propertyCompare';
import {
  describeListingFilters,
  listingStateFromSavedSearch,
  savedSearchFiltersFromState,
} from './savedSearchFilters';

test('compare selection dedupes, trims and caps at four items', () => {
  assert.deepEqual(sanitizeCompareIds(['a', 'a', ' b ', '', 42, null]), ['a', 'b']);
  assert.equal(MAX_COMPARE_ITEMS, 4);
  assert.deepEqual(sanitizeCompareIds(['1', '2', '3', '4', '5']), ['1', '2', '3', '4']);
});

test('toggling compare ids adds and removes without exceeding the limit', () => {
  assert.deepEqual(toggleCompareId([], 'p1'), { ids: ['p1'], added: true, limitReached: false });
  assert.deepEqual(toggleCompareId(['p1'], 'p1'), { ids: [], added: false, limitReached: false });

  const full = ['p1', 'p2', 'p3', 'p4'];
  const overflow = toggleCompareId(full, 'p5');
  assert.deepEqual(overflow.ids, full);
  assert.equal(overflow.added, false);
  assert.equal(overflow.limitReached, true);
});

test('compare share links round-trip through the URL', () => {
  const url = buildCompareShareUrl('/properties?phase=Phase%201', ['p1', 'p 2']);
  assert.equal(url, '/properties?phase=Phase%201&compare=p1%2Cp%202');
  assert.deepEqual(parseCompareIdsFromSearch('?compare=p1%2Cp%202'), ['p1', 'p 2']);
  assert.deepEqual(parseCompareIdsFromSearch('compare=p1,p2,p3,p4,p5'), ['p1', 'p2', 'p3', 'p4']);
  assert.deepEqual(parseCompareIdsFromSearch('?compare='), []);
});

test('compare storage is defensive against malformed localStorage values', () => {
  const memory = new Map<string, string>();
  const storage = {
    getItem: (key: string) => memory.get(key) ?? null,
    setItem: (key: string, value: string) => {
      memory.set(key, value);
    },
  };

  assert.deepEqual(readCompareIds(storage), []);
  memory.set(COMPARE_STORAGE_KEY, '{bad json');
  assert.deepEqual(readCompareIds(storage), []);
  writeCompareIds(['p1', 'p1', 'p2'], storage);
  assert.equal(memory.get(COMPARE_STORAGE_KEY), JSON.stringify(['p1', 'p2']));
  assert.deepEqual(readCompareIds(storage), ['p1', 'p2']);
});

test('listing filter state maps to the allow-listed saved-search payload', () => {
  const filters = savedSearchFiltersFromState({
    searchTerm: '  corner  ',
    type: 'Residential Plot',
    phase: 'Phase 2',
    sector: 'Sector 63',
    sizeSqYards: '120',
    possessionOnly: true,
    minPrice: 2500000,
    maxPrice: 10000000,
  });

  assert.deepEqual(filters, {
    search: 'corner',
    type: 'Residential Plot',
    phase: 'Phase 2',
    sector: 'Sector 63',
    sizeSqYards: 120,
    possessionOnly: true,
    minPrice: 2500000,
    maxPrice: 10000000,
  });

  assert.deepEqual(
    savedSearchFiltersFromState({
      searchTerm: '',
      type: 'All',
      phase: 'All',
      sector: 'All',
      sizeSqYards: 'All',
      possessionOnly: false,
      minPrice: 0,
      maxPrice: 0,
    }),
    {}
  );
});

test('saved search filters rehydrate the listing UI safely', () => {
  const state = listingStateFromSavedSearch({
    search: 'plot',
    type: 'Commercial Plot',
    phase: 'Phase 1',
    sector: 'Sector 50',
    sizeSqYards: '240',
    possessionOnly: true,
    minPrice: '5000000',
    maxPrice: 'bad',
  });

  assert.deepEqual(state, {
    searchTerm: 'plot',
    type: 'Commercial Plot',
    phase: 'Phase 1',
    sector: 'Sector 50',
    sizeSqYards: '240',
    possessionOnly: true,
    minPrice: 5000000,
    maxPrice: 0,
  });

  assert.equal(
    describeListingFilters({
      searchTerm: '',
      type: 'All',
      phase: 'All',
      sector: 'All',
      sizeSqYards: 'All',
      possessionOnly: false,
      minPrice: 0,
      maxPrice: 0,
    }),
    'All published listings'
  );
});
