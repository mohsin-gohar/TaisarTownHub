import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

/**
 * Regression guards for the agency video tour flows.
 *
 * The public gallery's "Add Agency Video Tour" used to append a local-only
 * video and immediately show a success toast — a fake success that lost the
 * recording on refresh and never hit the authenticated Laravel endpoint.
 * Publishing must now persist through the backend and surface honest errors.
 */

const read = (relativePath: string): string =>
  readFileSync(new URL(relativePath, import.meta.url), 'utf8');

test('agency video publish persists through the backend create endpoint', () => {
  const context = read('../../context/AppContext.tsx');
  const addBlock = context.split('const addAgencyVideo = (')[1]?.split('const addInquiry = (')[0] || '';
  assert.equal(addBlock.includes('createAgencyVideo'), true, 'context must call createAgencyVideo');

  const api = read('../../services/api.ts');
  assert.equal(api.includes("apiFetch('/videos'"), true, 'api service must POST /api/videos');
});

test('agency video publish only shows success once persistence succeeds', () => {
  const context = read('../../context/AppContext.tsx');
  const addBlock = context.split('const addAgencyVideo = (')[1]?.split('const addInquiry = (')[0] || '';
  const successToast = addBlock.indexOf("showToast('success', 'New agency video walkthrough added successfully!')");
  const createCall = addBlock.indexOf('createAgencyVideo');
  assert.ok(successToast > -1, 'success toast must exist');
  assert.ok(createCall > -1, 'backend create call must exist');
  assert.ok(successToast > createCall, 'success toast must fire after the persistence call, not before');
});

test('agency video like only fires a backend increment when a like is added', () => {
  const context = read('../../context/AppContext.tsx');
  const likeBlock = context.split('const likeAgencyVideo = (')[1]?.split('const addAgencyVideoComment = (')[0] || '';
  assert.equal(likeBlock.includes('api.likeVideo(videoId)'), true, 'backend like call must exist');
  const likeBranch = likeBlock.split('if (alreadyLiked)')[1] || '';
  const likeCallIndex = likeBranch.indexOf('api.likeVideo(videoId)');
  const unLikeMarker = likeBranch.indexOf('localStorage.removeItem(likedKey)');
  assert.ok(likeCallIndex > -1, 'like branch must keep the backend like call');
  assert.ok(unLikeMarker > -1, 'unlike branch must remove local state first');
  assert.ok(unLikeMarker < likeCallIndex, 'backend like must fire only after confirming a new like');
});

test('video modal closes on Escape key', () => {
  const modal = read('../common/VideoModal.tsx');
  assert.equal(modal.includes('Escape'), true, 'video modal must listen for Escape');
  assert.equal(modal.includes('onClose'), true);
});

test('public video walkthrough cards open the playable video modal', () => {
  const section = read('./VideoWalkthroughSection.tsx');
  assert.equal(section.includes("import { VideoModal }"), true);
  assert.equal(section.includes('onClick={() => setSelectedVideo(video)}'), true);
  assert.equal(section.includes('<VideoModal'), true);
  assert.equal(section.includes('videoUrl={selectedVideo?.videoUrl}'), true);
  assert.equal(section.includes("aria-label={'Play video: ' + video.title}"), true);
});
