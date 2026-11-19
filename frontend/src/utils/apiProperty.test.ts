import test from 'node:test';
import assert from 'node:assert/strict';
import {
  mapApiProperty,
  normalizePropertyPhase,
  normalizePropertyStatus,
  normalizePropertyType,
} from './apiProperty';

test('normalizes governed API phase values into public phase labels', () => {
  assert.equal(normalizePropertyPhase('1'), 'Phase 1');
  assert.equal(normalizePropertyPhase('Phase 2'), 'Phase 2');
  assert.equal(normalizePropertyPhase('3'), 'Phase 3');
  assert.equal(normalizePropertyPhase(null), undefined);
  assert.equal(normalizePropertyPhase('unknown'), undefined);
});

test('normalizes listing types and statuses to the public unions', () => {
  assert.equal(normalizePropertyType('Residential Plot'), 'Residential Plot');
  assert.equal(normalizePropertyType('commercial plot'), 'Commercial Plot');
  assert.equal(normalizePropertyType('Retail Shop'), 'Commercial Shop');
  assert.equal(normalizePropertyType('Modern Duplex Villa'), 'House / Villa');
  assert.equal(normalizePropertyType(''), 'Residential Plot');

  assert.equal(normalizePropertyStatus('Hot Deal'), 'Hot Deal');
  assert.equal(normalizePropertyStatus('unexpected'), 'Available');
});

test('maps a governed property payload without leaking unknown fields', () => {
  const property = mapApiProperty({
    id: 'prop-1',
    title: '120 Sq. Yd. Plot',
    description: 'Verified file',
    pricePKR: 3600000,
    formattedPrice: null,
    phase: '1',
    sector: 'Sector 70-D',
    location: null,
    sizeSqYards: '120',
    type: 'Residential Plot',
    status: 'Hot Deal',
    images: ['https://example.test/one.jpg', null, ''],
    features: ['West Open'],
    isFeatured: 1,
    possessionReady: true,
    mdaApproved: true,
    created_at: '2026-11-14T10:00:00.000000Z',
    // Private/ERP-only keys must not be copied to the public view model.
    purchasePricePKR: 100,
    internalNotes: 'secret',
  });

  assert.equal(property.id, 'prop-1');
  assert.equal(property.pricePKR, 3600000);
  assert.equal(property.formattedPrice, 'PKR 36 Lacs');
  assert.equal(property.phase, 'Phase 1');
  assert.equal(property.location, 'Sector 70-D, Taiser Town Scheme 45, Karachi');
  assert.equal(property.sizeSqYards, 120);
  assert.deepEqual(property.images, ['https://example.test/one.jpg']);
  assert.deepEqual(property.features, ['West Open']);
  assert.equal(property.isFeatured, true);
  assert.equal(property.createdDate, '2026-11-14');
  assert.equal(property.status, 'Hot Deal');
  assert.equal('purchasePricePKR' in property, false);
  assert.equal('internalNotes' in property, false);
});
