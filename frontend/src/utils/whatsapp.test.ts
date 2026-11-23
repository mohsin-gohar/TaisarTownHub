import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
  AGENCY_WHATSAPP_MESSAGE,
  buildWhatsAppLink,
  normalizeWhatsAppNumber,
  propertyWhatsAppMessage,
  resolveWhatsAppLink,
} from './whatsapp';

test('normalizes Pakistani local, formatted and international numbers', () => {
  assert.equal(normalizeWhatsAppNumber('03001234567'), '923001234567');
  assert.equal(normalizeWhatsAppNumber('0300-1234567'), '923001234567');
  assert.equal(normalizeWhatsAppNumber('(0300) 1234567'), '923001234567');
  assert.equal(normalizeWhatsAppNumber('+92 300 1234567'), '923001234567');
  assert.equal(normalizeWhatsAppNumber('00923001234567'), '923001234567');
  assert.equal(normalizeWhatsAppNumber('3001234567'), '923001234567');
});

test('does not produce double country codes', () => {
  assert.equal(normalizeWhatsAppNumber('+92 92 300 1234567'), '923001234567');
  assert.equal(normalizeWhatsAppNumber('0092 0300 1234567'), '923001234567');
  assert.equal(normalizeWhatsAppNumber('92 03001234567'), '923001234567');
});

test('keeps valid non-Pakistani international numbers', () => {
  assert.equal(normalizeWhatsAppNumber('+1 415 555 2671'), '14155552671');
  assert.equal(normalizeWhatsAppNumber('+44 20 7946 0958'), '442079460958');
});

test('rejects missing and invalid numbers instead of building a broken link', () => {
  for (const value of ['', '   ', null, undefined, '12345', 'abc', '03001']) {
    assert.equal(normalizeWhatsAppNumber(value), null);
    assert.equal(buildWhatsAppLink(value, 'hello'), null);
  }
});

test('URL-encodes contextual messages', () => {
  const url = buildWhatsAppLink('03001234567', 'Assalam-o-Alaikum & thanks!');
  assert.ok(url?.startsWith('https://wa.me/923001234567?text='));
  assert.ok(url?.includes('Assalam-o-Alaikum%20%26%20thanks!'));
});

test('agency message is the approved Scheme 45 template', () => {
  assert.equal(
    AGENCY_WHATSAPP_MESSAGE,
    'Assalam-o-Alaikum, I found your agency on TaisarTownHub Scheme 45 marketplace. I would like information about your available properties.'
  );
});

test('property message includes the real property title', () => {
  assert.equal(
    propertyWhatsAppMessage('Plot R-66, 120 Sq Yd'),
    'Assalam-o-Alaikum, I am interested in Plot R-66, 120 Sq Yd listed on TaisarTownHub, Scheme 45. Please share details, availability and price.'
  );
  assert.ok(propertyWhatsAppMessage().includes('this property'));
});

test('resolves destination with the required priority', () => {
  const input = {
    propertyWhatsApp: '0300-1111111',
    agentWhatsApp: '+92 300 2222222',
    agencyWhatsApp: '+92 300 3333333',
    agencyPhone: '0300-4444444',
  };
  const property = resolveWhatsAppLink(input, 'hi');
  assert.equal(property?.source, 'property');
  assert.equal(property?.destination, '923001111111');

  assert.equal(resolveWhatsAppLink({ ...input, propertyWhatsApp: 'bad' }, 'hi')?.source, 'agent');
  assert.equal(
    resolveWhatsAppLink({ ...input, propertyWhatsApp: 'bad', agentWhatsApp: '' }, 'hi')?.source,
    'agency'
  );
  assert.equal(
    resolveWhatsAppLink({ propertyWhatsApp: '', agencyWhatsApp: 'bad', agencyPhone: '0300-4444444' }, 'hi')?.source,
    'agency_phone'
  );
});

test('never falls back to a platform number when no agency contact is supplied', () => {
  assert.equal(resolveWhatsAppLink({}, 'hi'), null);
  assert.equal(resolveWhatsAppLink({ agencyWhatsApp: '123' }, 'hi'), null);
});
