import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = (relativePath: string): string =>
  readFileSync(new URL(relativePath, import.meta.url), 'utf8');

/**
 * Regression guard for the public hero slideshow.
 *
 * The home header rotates through 5 premium property visuals every 6–8
 * seconds, starting on the uploaded brand shot, then other premium scenes.
 */
test('hero has a slideshow with four to five slides', () => {
  const source = read('./HeroSection.tsx');
  const slideBlocks = source.split('id: ').length - 1;
  assert.ok(slideBlocks >= 4 && slideBlocks <= 6, `unexpected slide count: ${slideBlocks}`);
  assert.equal(source.includes('HERO_SLIDES'), true);
  assert.equal(source.includes('HERO_SLIDE_INTERVAL_MS'), true);
});

test('hero slideshow starts on the uploaded image', () => {
  const source = read('./HeroSection.tsx');
  assert.ok(source.includes("background: '/image.png'"), 'first slide must use the uploaded image');
  const interval = Number(source.match(/HERO_SLIDE_INTERVAL_MS = (\d+)/)?.[1] ?? 0);
  assert.ok(interval >= 6000 && interval <= 8000, `interval out of 6-8s range: ${interval}`);
  assert.equal(source.includes('setInterval'), true);
});

test('hero slideshow honours reduced motion', () => {
  const source = read('./HeroSection.tsx');
  assert.ok(source.includes('if (reduceMotion) return;'), 'reduced-motion guard missing');
});

test('hero background image is responsive', () => {
  const source = read('./HeroSection.tsx');
  assert.ok(source.includes('object-cover'), 'background must use object-cover');
  assert.ok(source.includes('object-center'), 'background must be centered');
});