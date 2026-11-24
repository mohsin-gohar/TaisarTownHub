import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { isDemoVideoUrl, DEMO_VIDEO_URL, isStaticCatalogBuild } from './demoContent';

/**
 * Honest-content regression guards.
 *
 * The public bundle ships placeholder media (one shared /sample-video.mp4 and
 * stock photography). These guards ensure placeholder video surfaces stay
 * labeled as demo content and that the demo catalog notice is wired into the
 * public listing surfaces, so no fake media can pose as real Scheme 45
 * inventory.
 */

const read = (relativePath: string): string =>
  readFileSync(new URL(relativePath, import.meta.url), 'utf8');

test('isDemoVideoUrl recognises every placeholder sample video form', () => {
  assert.equal(isDemoVideoUrl(DEMO_VIDEO_URL), true, 'relative path must match');
  assert.equal(isDemoVideoUrl('/sample-video.mp4?v=2'), true, 'query string must be ignored');
  assert.equal(isDemoVideoUrl('https://cdn.example.com/sample-video.mp4'), true, 'absolute URL must match');
  assert.equal(isDemoVideoUrl('http://localhost:3000/sample-video.mp4#t=1'), true, 'hash must be ignored');
});

test('isDemoVideoUrl rejects real footage and empty values', () => {
  assert.equal(isDemoVideoUrl('/videos/phase1-real-tour.mp4'), false);
  assert.equal(isDemoVideoUrl('https://example.com/walkthrough.mp4'), false);
  assert.equal(isDemoVideoUrl(''), false);
  assert.equal(isDemoVideoUrl(null), false);
  assert.equal(isDemoVideoUrl(undefined), false);
  assert.equal(isDemoVideoUrl('sample-video.mp4.bak'), false);
});

test('isStaticCatalogBuild reflects the absence of a configured API origin', () => {
  // In the test environment no VITE_API_URL is set, so the bundle behaves
  // like a static frontend-only deployment and must be flagged as demo.
  assert.equal(isStaticCatalogBuild, true);
});

test('public gallery labels placeholder tours with the demo badge', () => {
  const gallery = read('../components/public/MediaGallerySection.tsx');
  assert.equal(gallery.includes('isDemoVideoUrl(video.videoUrl)'), true, 'video cards must gate the badge on the placeholder check');
  assert.equal(gallery.includes('<DemoBadge'), true, 'video cards must render the DemoBadge');
  assert.equal(gallery.includes('<DemoCatalogNotice />'), true, 'gallery must render the demo catalog notice');
});

test('property detail page labels the placeholder walkthrough video', () => {
  const page = read('../components/public/PropertyDetailPage.tsx');
  assert.equal(page.includes('isDemoVideoUrl(property.videoUrl)'), true, 'video surface must gate the badge');
  assert.equal(page.includes('<DemoBadge'), true, 'video surface must render the badge');
});

test('project detail page labels the placeholder architectural tour', () => {
  const page = read('../components/public/ProjectDetailPage.tsx');
  assert.equal(page.includes('isDemoVideoUrl(project.videoUrl)'), true, 'tour button must gate the badge');
  assert.equal(page.includes('<DemoBadge'), true, 'tour button must render the badge');
});

test('public listings surface the demo catalog notice', () => {
  const page = read('../components/public/PropertiesListingPage.tsx');
  assert.equal(page.includes('<DemoCatalogNotice />'), true, 'listings must render the demo catalog notice');
});

test('demo catalog notice derives demo state honestly', () => {
  const notice = read('../components/common/DemoCatalogNotice.tsx');
  assert.equal(notice.includes('isStaticCatalogBuild || (!backendConnected && properties.length > 0)'), true, 'notice must combine the static build flag with the unreachable-API fallback');
  assert.equal(notice.includes('backendConnected'), true, 'notice must read live backend connectivity from the app context');
});
