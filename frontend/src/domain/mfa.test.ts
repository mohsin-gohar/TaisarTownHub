import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
  formatRecoveryCodes,
  isRecoveryCode,
  isTotpCode,
  mfaChallengeMessage,
  mfaPayloadCode,
  normalizeMfaCode,
  normalizeRecoveryCode,
} from './mfa';

test('authenticator codes are exactly six digits', () => {
  assert.equal(isTotpCode('123456'), true);
  assert.equal(isTotpCode(' 123 456 '), true, 'spaces from a paste must be tolerated');
  assert.equal(isTotpCode('12345'), false);
  assert.equal(isTotpCode('1234567'), false);
  assert.equal(isTotpCode('abcdef'), false);
});

test('recovery codes are upper-cased and hyphenated', () => {
  assert.equal(isRecoveryCode('abcde-fghij'), true);
  assert.equal(normalizeRecoveryCode(' abcde-fghij '), 'ABCDE-FGHIJ');
  assert.equal(isRecoveryCode('123456'), false, 'a TOTP code is not a recovery code');
});

test('payload prefers the compact TOTP code and falls back to the recovery code', () => {
  assert.equal(mfaPayloadCode(' 987654 '), '987654');
  assert.equal(mfaPayloadCode('abcde-fghij'), 'ABCDE-FGHIJ');
  assert.equal(normalizeMfaCode(' 12 34 56 '), '123456');
});

test('recovery codes are formatted one per line for the operator', () => {
  assert.equal(formatRecoveryCodes(['abcde-fghij', 'klmno-pqrst']), 'ABCDE-FGHIJ\nKLMNO-PQRST');
});

test('challenge message states the expiry when known', () => {
  assert.match(mfaChallengeMessage(300), /5 minutes/);
  assert.match(mfaChallengeMessage(30), /1 minute\./);
  assert.match(mfaChallengeMessage(), /authenticator app/);
});
