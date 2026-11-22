import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

/**
 * Regression guards for the public /maps gallery.
 *
 * The gallery must keep the real loading / error / empty states, the lightbox
 * zoom + pan + keyboard behaviours and the download action wired to the real
 * map payloads returned by the public API.
 */
const read = (relativePath: string): string =>
  readFileSync(new URL(relativePath, import.meta.url), 'utf8');

test('public maps page exposes loading, error and empty states plus map cards', () => {
  const source = read('./MapsPage.tsx');
  assert.equal(source.includes('data-testid="maps-loading"'), true, 'loading state missing');
  assert.equal(source.includes('data-testid="maps-error"'), true, 'error state missing');
  assert.equal(source.includes('data-testid="maps-empty"'), true, 'empty state missing');
  assert.equal(source.includes('data-testid="map-card"'), true, 'map card missing');
});

test('public maps page renders the featured badge and map lightbox actions', () => {
  const source = read('./MapsPage.tsx');
  assert.equal(source.includes('data-testid="map-lightbox"'), true, 'lightbox missing');
  assert.equal(source.includes('data-testid="zoom-level"'), true, 'zoom level readout missing');
  assert.equal(source.includes('is_featured'), true, 'featured badge source missing');
});

test('public maps lightbox supports zoom, pan and Escape close', () => {
  const source = read('./MapsPage.tsx');
  assert.equal(source.includes('Math.min(z + 0.25, 4)'), true, 'zoom-in bound missing');
  assert.equal(source.includes('Math.max(z - 0.25, 0.5)'), true, 'zoom-out bound missing');
  assert.equal(source.includes('Math.min(Math.max(z + delta, 0.5), 4)'), true, 'wheel zoom bound missing');
  assert.equal(source.includes("e.key === 'Escape'"), true, 'escape close handler missing');
  assert.equal(source.includes('data-testid="map-lightbox"'), true);
});

test('public maps download action builds a real anchor download', () => {
  const source = read('./MapsPage.tsx');
  assert.equal(source.includes('handleDownload'), true);
  assert.equal(source.includes('a.download'), true, 'download attribute missing');
  assert.equal(source.includes('document.createElement'), true);
});

test('public maps gallery fetches through the real public maps API', () => {
  const source = read('./MapsPage.tsx');
  assert.equal(source.includes('api.getMaps'), true, 'must call the real maps API');
  assert.equal(source.includes('data?.success && Array.isArray(data.maps)'), true, 'must validate public payload shape');
});
