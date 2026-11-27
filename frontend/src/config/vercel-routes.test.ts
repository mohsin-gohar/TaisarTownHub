import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';

const configs = [
  resolve(process.cwd(), 'vercel.json'),
  resolve(process.cwd(), '..', 'vercel.json'),
];

test('both Vercel project roots redirect the legacy video walkthrough URL', () => {
  for (const path of configs) {
    const config = JSON.parse(readFileSync(path, 'utf8'));
    assert.ok(
      config.redirects?.some((rule) =>
        rule.source === '/video-walkthroughs' &&
        rule.destination === '/gallery' &&
        rule.permanent === true
      ),
      `Missing permanent /video-walkthroughs → /gallery redirect in ${path}`,
    );
  }
});
