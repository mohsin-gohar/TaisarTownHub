// Cross-platform test discovery for the Node test runner via tsx.
// A quoted "src/**/*.test.ts" glob is not expanded by every shell (notably
// Windows PowerShell and some CI shells), which made `npm run verify` fail on
// a fresh checkout even though the same command worked interactively.
// This script discovers *.test.ts files with fs and passes explicit paths.

import { spawnSync } from 'node:child_process';
import { readdirSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('..', import.meta.url));
const src = join(root, 'src');

function findTests(dir) {
  const out = [];
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry);
    if (statSync(full).isDirectory()) {
      out.push(...findTests(full));
    } else if (entry.endsWith('.test.ts')) {
      out.push(relative(root, full));
    }
  }
  return out;
}

const tests = findTests(src).sort();
if (tests.length === 0) {
  console.error('run-tests: no *.test.ts files found under src/');
  process.exit(1);
}

const tsxBin = join(root, 'node_modules', '.bin', process.platform === 'win32' ? 'tsx.cmd' : 'tsx');
const result = spawnSync(tsxBin, ['--test', ...tests], {
  cwd: root,
  stdio: 'inherit',
  shell: process.platform === 'win32',
});
process.exit(result.status ?? 1);
