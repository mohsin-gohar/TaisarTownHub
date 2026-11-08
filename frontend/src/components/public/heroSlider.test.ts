import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = (relativePath: string): string =>
  readFileSync(new URL(relativePath, import.meta.url), 'utf8');

/**
 * Regression guard for the public hero background.
 *
 * The hero must render a SINGLE responsive background image (no multi-slide
 * cross-fade flash that made several images appear at once), honour reduced
 * motion, and keep the uploaded full-moon shot as the primary visual.
 */
test('hero uses a single background image (no multi-slide flash)', () => {
  const source = read('./HeroSection.tsx');
  // No slideshow array / interval anymore.
  assert.equal(source.includes('HERO_SLIDES'), false, 'multi-slide array must be removed');
  assert.equal(source.includes('HERO_SLIDE_INTERVAL_MS'), false, 'slide interval must be removed');
  assert.equal(source.includes('setInterval'), false, 'autoplay interval must be removed');
  // Single image source.
  assert.ok(source.includes('src="/image.png"'), 'primary hero image must be /image.png');
});

test('hero background image is responsive', () => {
  const source = read('./HeroSection.tsx');
  assert.ok(source.includes('object-cover'), 'background must use object-cover');
  assert.ok(source.includes('object-center'), 'background must be centered');
  assert.ok(source.includes('w-full h-full'), 'background must fill its container');
});

test('hero still honours reduced motion for parallax', () => {
  const source = read('./HeroSection.tsx');
  assert.ok(source.includes('if (reduceMotion) return;'), 'reduced-motion guard missing');
});