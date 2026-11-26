import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { allowsPublicSeedFallback, resolveModeFromEnv } from './appMode';

describe('resolveModeFromEnv - Deployment Safety', () => {
  it('safely defaults to public mode when environment is empty', () => {
    assert.strictEqual(resolveModeFromEnv({}), 'public');
    assert.strictEqual(resolveModeFromEnv({ VITE_APP_MODE: undefined, VITE_DEMO_MODE: undefined }), 'public');
    assert.strictEqual(resolveModeFromEnv({ VITE_APP_MODE: '' }), 'public');
  });

  it('safely defaults to public mode on invalid or unknown mode strings', () => {
    assert.strictEqual(resolveModeFromEnv({ VITE_APP_MODE: 'staging' }), 'public');
    assert.strictEqual(resolveModeFromEnv({ VITE_APP_MODE: 'test' }), 'public');
    assert.strictEqual(resolveModeFromEnv({ VITE_APP_MODE: 'unknown' }), 'public');
  });

  it('resolves demo mode when explicitly configured', () => {
    assert.strictEqual(resolveModeFromEnv({ VITE_APP_MODE: 'demo' }), 'demo');
    assert.strictEqual(resolveModeFromEnv({ VITE_APP_MODE: ' DEMO ' }), 'demo');
    assert.strictEqual(resolveModeFromEnv({ VITE_DEMO_MODE: 'true' }), 'demo');
  });

  it('resolves production mode when explicitly configured', () => {
    assert.strictEqual(resolveModeFromEnv({ VITE_APP_MODE: 'production' }), 'production');
    assert.strictEqual(resolveModeFromEnv({ VITE_APP_MODE: 'Production ' }), 'production');
  });

  it('does not activate demo mode if VITE_DEMO_MODE is not strictly "true"', () => {
    assert.strictEqual(resolveModeFromEnv({ VITE_DEMO_MODE: 'false' }), 'public');
    assert.strictEqual(resolveModeFromEnv({ VITE_DEMO_MODE: '1' }), 'public');
    assert.strictEqual(resolveModeFromEnv({ VITE_DEMO_MODE: 'yes' }), 'public');
  });

  it('prioritizes VITE_APP_MODE over VITE_DEMO_MODE', () => {
    assert.strictEqual(resolveModeFromEnv({ VITE_APP_MODE: 'production', VITE_DEMO_MODE: 'true' }), 'production');
    assert.strictEqual(resolveModeFromEnv({ VITE_APP_MODE: 'public', VITE_DEMO_MODE: 'true' }), 'public');
  });
});


describe('public seed fallback policy', () => {
  it('allows bundled sample catalog only in public and demo modes', () => {
    assert.strictEqual(allowsPublicSeedFallback('public'), true);
    assert.strictEqual(allowsPublicSeedFallback('demo'), true);
    assert.strictEqual(allowsPublicSeedFallback('production'), false);
  });
});
