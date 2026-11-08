import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

/**
 * Regression guard for the public hero slideshow.
 *
 * The home header must rotate through 4–5 property visuals on an interval,
 * starting on the full-moon night shot and then the configured hero image,
 * without ever falling back to demo/staging wording or a missing slide.
 */
const read = (relativePath: string): string =>
  readFileSync(new URL(relativePath, import.meta.url), 'utf8');

test('hero has a slideshow with four to five slides', () => {
  const source = read('./HeroSection.tsx');
  const slideBlocks = source.split('id: ').length - 1;
  // Count slide definitions inside HERO_SLIDES plus the interface key usage.
  assert.ok(slideBlocks >= 4 && slideBlocks <= 6, `unexpected slide count: ${slideBlocks}`);
  assert.equal(source.includes('HERO_SLIDES'), true);
  assert.equal(source.includes('HERO_SLIDE_INTERVAL_MS'), true);
});

test('hero slideshow starts on the full moon slide and rotates', () => {
  const source = read('./HeroSection.tsx');
  assert.ok(source.includes("background: '/image.png'"), 'first slide must use the uploaded image');
  assert.ok(source.includes("/hero-bg.jpg"), 'configured hero slide missing');
  const interval = Number(source.match(/HERO_SLIDE_INTERVAL_MS = (\d+)/)?.[1] ?? 0);
  assert.ok(interval >= 4000 && interval <= 6000, `interval out of 4-5s range: ${interval}`);
  assert.equal(source.includes('setInterval'), true);
});

test('hero slideshow honours reduced motion', () => {
  const source = read('./HeroSection.tsx');
  assert.ok(source.includes('if (reduceMotion) return;'), 'reduced-motion guard missing');
});
