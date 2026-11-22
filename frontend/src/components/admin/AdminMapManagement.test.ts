import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

/**
 * Regression guards for the Super Admin Map Management desk.
 *
 * The desk must keep the real create / bulk upload / replace / toggle /
 * feature / delete / reorder backend integration and the safe replacement flow.
 */
const read = (relativePath: string): string =>
  readFileSync(new URL(relativePath, import.meta.url), 'utf8');

test('admin map management keeps single upload wired to the create endpoint', () => {
  const source = read('./AdminMapManagement.tsx');
  assert.equal(source.includes('api.createMap'), true);
  assert.equal(source.includes('api.updateMap'), true);
});

test('admin map management keeps bulk upload wired to the bulk endpoint', () => {
  const source = read('./AdminMapManagement.tsx');
  assert.equal(source.includes('api.bulkCreateMaps'), true, 'bulk upload must call the bulk API');
  assert.equal(source.includes('setBulkOpen(true)'), true, 'bulk modal trigger missing');
  assert.equal(source.includes('handleBulkSubmit'), true, 'bulk submit handler missing');
  assert.equal(source.includes('multiple'), true, 'bulk file picker must accept multiple images');
});

test('admin map management keeps replace, toggle, feature, delete and reorder wired', () => {
  const source = read('./AdminMapManagement.tsx');
  assert.equal(source.includes('api.replaceMapImage'), true);
  assert.equal(source.includes('api.toggleMap'), true);
  assert.equal(source.includes('api.featureMap'), true);
  assert.equal(source.includes('api.deleteMap'), true);
  assert.equal(source.includes('api.reorderMaps'), true);
});

test('admin map management keeps search and category filtering', () => {
  const source = read('./AdminMapManagement.tsx');
  assert.equal(source.includes('api.getAdminMaps'), true);
  assert.equal(source.includes('filteredMaps'), true);
});
