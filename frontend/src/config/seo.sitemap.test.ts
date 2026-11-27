import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import test from 'node:test';
import { SEO_ROUTES } from './seo';

const PRODUCTION_ORIGIN = 'https://taisartownhub.com';

test('public sitemap lists every indexable route on the production origin', () => {
  const xml = readFileSync(join(process.cwd(), 'public', 'sitemap.xml'), 'utf8');
  assert.equal(xml.includes('vercel.app'), false);
  assert.equal(xml.includes('localhost'), false);

  const locs = [...xml.matchAll(/<loc>([^<]+)<\/loc>/g)].map((match) => match[1]);
  const expected = SEO_ROUTES.filter((route) => route.inSitemap).map(
    (route) => `${PRODUCTION_ORIGIN}${route.path === '/' ? '/' : route.path}`,
  );

  assert.deepEqual(locs, expected);
});
