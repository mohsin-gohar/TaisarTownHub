import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

/**
 * Regression guards for crawlability of dynamic detail pages.
 *
 * Property and project detail pages are excluded from sitemap.xml on purpose,
 * so they can only be discovered by crawlers through real anchors on the
 * listing pages. A refactor back to <button onClick> silently removes them
 * from search indexes, so it is pinned here.
 *
 * Source-level assertions only — no application context, no backend calls.
 */
const read = (path: string) => readFileSync(new URL(path, import.meta.url), 'utf8');

const listing = read('./PropertiesListingPage.tsx');
const featured = read('./FeaturedProperties.tsx');
const projects = read('./ProjectsListingPage.tsx');
const notFound = read('./NotFoundPage.tsx');

test('property cards expose a crawlable href, not just a click handler', () => {
  assert.match(listing, /const propertyHref = \(id: string\) => `\/properties\?property=\$\{encodeURIComponent\(id\)\}`/);
  assert.match(listing, /href=\{propertyHref\(prop\.id\)\}/);
  // The card must no longer rely on an onClick-only <h3>.
  assert.doesNotMatch(listing, /<h3\s*\n\s*onClick=\{\(\) => handleOpenProperty/);
});

test('property and project links keep the SPA transition for plain left-clicks', () => {
  for (const source of [listing, featured]) {
    assert.match(source, /if \(e\.metaKey \|\| e\.ctrlKey \|\| e\.shiftKey \|\| e\.altKey \|\| e\.button !== 0\) return;/);
    assert.match(source, /e\.preventDefault\(\);/);
    assert.match(source, /href=\{propertyHref\(prop\.id\)\}/);
  }
  assert.match(projects, /const projectHref = \(id: string\) => `\/projects\?project=\$\{encodeURIComponent\(id\)\}`/);
  assert.match(projects, /href=\{projectHref\(project\.id\)\}/);
});

test('featured and listing detail CTAs are anchors (middle-click / new tab)', () => {
  assert.match(featured, /<a\s*\n\s*href=\{propertyHref\(prop\.id\)\}/);
  assert.match(listing, /<a\s*\n\s*href=\{propertyHref\(prop\.id\)\}/);
  assert.match(projects, /<a\s*\n\s*href=\{projectHref\(project\.id\)\}/);
});

test('404 page offers a real recovery path for crawlers and keyboard users', () => {
  assert.match(notFound, /href="\/"/);
  assert.match(notFound, /href="\/properties"/);
  assert.match(notFound, /href="\/contact"/);
  // Recovery actions must be anchors — an inert button is a dead end.
  assert.doesNotMatch(notFound, /<button\s*\n\s*onClick=\{\(\) => setCurrentView\('home'\)\}/);
});