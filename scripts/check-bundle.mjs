#!/usr/bin/env node
/**
 * check-bundle.mjs — fails the build if any chunk that is reachable STATICALLY
 * from the entry (i.e. downloaded by any public visitor just by loading the home
 * page) contains ERP-private field names or demo credentials.
 *
 * Strategy:
 *   1. Read dist/index.html → entry scripts + modulepreload links.
 *   2. Expand the static import graph (esbuild/rollup output) — dynamic
 *      import() expressions are treated as admin/staff-only boundaries and
 *      are NOT expanded, because public pages never execute them.
 *   3. Scan every file in that closure for banned strings.
 */
import { readFileSync, existsSync } from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const dist = path.join(root, 'dist');
const indexHtml = path.join(dist, 'index.html');

if (!existsSync(indexHtml)) {
  console.error('[check:bundle] dist/index.html not found. Run `npm run build` first.');
  process.exit(1);
}

const banned = JSON.parse(readFileSync(path.join(root, 'scripts', 'sensitive-strings.json'), 'utf8'));
/**
 * fieldNames: matched as standalone JS tokens (word-boundary, case-SENSITIVE).
 *   - lowercase property names like .cnic / cnic: are caught
 *   - uppercase UI copy ("CNIC Copy") and permission names (view_sensitive_cnic)
 *     are NOT false positives
 * passwords: matched case-insensitively as plain substrings (they are distinctive).
 */
function escapeRe(s) {
  return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}
const forbidden = [
  ...banned.fieldNames.map((s) => ({
    re: new RegExp(`(?<![A-Za-z0-9_$])${escapeRe(s)}(?![A-Za-z0-9_$])`),
    label: s,
  })),
  ...banned.passwords.map((s) => ({
    re: new RegExp(escapeRe(s), 'i'),
    label: s,
  })),
];

// Asset name from href/src like /assets/index-XYZ.js
function assetPath(url) {
  const clean = url.replace(/^['"\s]+|['"\s]+$/g, '');
  const file = clean.split('?')[0].replace(/^\//, '');
  return path.join(dist, file);
}

const html = readFileSync(indexHtml, 'utf8');
const seeds = new Set();
for (const m of html.matchAll(/<script[^>]+type="module"[^>]+src="([^"]+)"/g)) seeds.add(assetPath(m[1]));
for (const m of html.matchAll(/<link[^>]+rel="modulepreload"[^>]+href="([^"]+)"/g)) seeds.add(assetPath(m[1]));
for (const m of html.matchAll(/<link[^>]+href="([^"]+\.css)"/g)) seeds.add(assetPath(m[1]));

if (seeds.size === 0) {
  console.error('[check:bundle] no entry assets found in dist/index.html');
  process.exit(1);
}

// Expand static import graph from the seed files.
const publicFiles = new Set();
const queue = [...seeds];
const importRe = /(?:import[\s\S]{0,400}?|export[\s\S]{0,200}?)from\s*"(\.\/[^"]+\.js)"/g;
while (queue.length) {
  const file = queue.pop();
  if (publicFiles.has(file) || !existsSync(file)) continue;
  publicFiles.add(file);
  const code = readFileSync(file, 'utf8');
  for (const m of code.matchAll(importRe)) {
    queue.push(path.resolve(path.dirname(file), m[1].replace(/^\.\//, '')));
  }
}

// Note: `import("...")` dynamic boundaries are intentionally ignored.

let failed = [];
for (const file of publicFiles) {
  if (!file.endsWith('.js')) continue;
  const code = readFileSync(file, 'utf8');
  for (const { re, label } of forbidden) {
    if (re.test(code)) {
      failed.push(`${path.relative(dist, file)} contains "${label}"`);
    }
  }
}

console.log(`[check:bundle] scanned ${publicFiles.size} public chunk(s):`);
for (const f of publicFiles) console.log(`  - ${path.relative(dist, f)} (${(readFileSync(f).length / 1024).toFixed(1)} KB)`);

if (failed.length) {
  console.error('\n[check:bundle] FAILED — private/demo data leaked into public chunks:');
  for (const f of failed) console.error(`  ✗ ${f}`);
  process.exit(1);
}

console.log('\n[check:bundle] OK — no private ERP data or demo credentials in public chunks.');
