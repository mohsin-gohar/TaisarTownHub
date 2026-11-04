import test from 'node:test';
import assert from 'node:assert/strict';
import {
  activeMediaReferenceTitles,
  clearSoftDelete,
  decideMediaDeletion,
  stampSoftDelete,
} from './mediaPolicy';

const url = 'https://example.com/plot.jpg';

test('activeMediaReferenceTitles ignores trashed listings and matches document URLs', () => {
  const titles = activeMediaReferenceTitles(
    url,
    [
      { title: 'Live plot', images: [url] },
      { title: 'Trashed plot', images: [url], deletedAt: '2026-11-01' },
    ],
    [
      { title: 'Live project', coverImage: 'other.jpg', galleryImages: [url] },
      { title: 'Trashed project', coverImage: url, galleryImages: [], deletedAt: '2026-11-01' },
    ],
    [{ title: 'Allotment', documentUrl: url }],
  );

  assert.deepEqual(titles, ['Live plot', 'Live project', 'Allotment']);
});

test('decideMediaDeletion blocks an asset that is still in use', () => {
  const decision = decideMediaDeletion(
    { title: 'Hero', url },
    [{ title: 'Plot R-66', images: [url] }],
    [],
    [],
  );
  assert.equal(decision.ok, false);
  if (!decision.ok) {
    assert.equal(decision.code, 'in_use');
    assert.match(decision.message, /Plot R-66/);
  }
});

test('decideMediaDeletion allows a soft delete when nothing references the asset', () => {
  const decision = decideMediaDeletion(
    { title: 'Unused', url },
    [{ title: 'Other', images: ['https://example.com/other.jpg'] }],
    [{ title: 'Scheme', coverImage: 'https://example.com/cover.jpg', galleryImages: [] }],
    [],
  );
  assert.deepEqual(decision, { ok: true, code: 'soft_delete' });
});

test('decideMediaDeletion treats an already trashed asset as missing', () => {
  const decision = decideMediaDeletion(
    { title: 'Gone', url, deletedAt: '2026-11-03' },
    [],
    [],
  );
  assert.equal(decision.ok, false);
  if (!decision.ok) assert.equal(decision.code, 'not_found');
});

test('stampSoftDelete and clearSoftDelete round-trip a record', () => {
  const record: { id: string; title: string; deletedAt?: string; deletedBy?: string } = {
    id: 'med-1',
    title: 'Banner',
  };
  const stamped = stampSoftDelete(record, 'Ayesha', '2026-11-03');
  assert.equal(stamped.deletedAt, '2026-11-03');
  assert.equal(stamped.deletedBy, 'Ayesha');
  const restored = clearSoftDelete(stamped);
  assert.equal(restored.deletedAt, undefined);
  assert.equal(restored.deletedBy, undefined);
  assert.equal(restored.title, 'Banner');
});
