import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

/**
 * Regression guard for the File Verification desk.
 *
 * Earlier builds only accepted a pasted image URL, so agency staff could not
 * actually upload a scanned PDF from the dashboard, and the preview blindly
 * rendered every document as an <img>. These assertions keep the real file
 * picker, data-URL storage, PDF preview and upload reset behaviour wired up.
 */
const read = (relativePath: string): string =>
  readFileSync(new URL(relativePath, import.meta.url), 'utf8');

test('file verification desk offers a real local document picker', () => {
  const source = read('./AdminFileManagement.tsx');
  assert.equal(source.includes('type="file"'), true);
  assert.equal(source.includes('DOCUMENT_UPLOAD_ACCEPT'), true);
  assert.equal(source.includes('handleDocumentFileChange'), true);
  assert.equal(source.includes('validateDocumentFile'), true);
  assert.equal(source.includes('readAsDataURL'), true);
  assert.equal(source.includes('fileType: documentFileType'), true);
  assert.equal(source.includes('fileSize: documentFileSize'), true);
});

test('document preview supports PDFs and empty placeholders', () => {
  const source = read('./AdminFileManagement.tsx');
  assert.equal(source.includes('<iframe'), true);
  assert.equal(source.includes('previewIsPdf'), true);
  assert.equal(source.includes('No scanned copy attached'), true);
  assert.equal(source.includes('hasViewableDocument'), true);
});

test('upload modal resets draft state on close and submit', () => {
  const source = read('./AdminFileManagement.tsx');
  assert.equal(source.includes('resetUploadForm'), true);
  assert.equal(source.includes('closeUploadModal();'), true);
  assert.equal(source.includes('setDocumentFileName(\'\')'), true);
});

test('file verification desk exposes an audited delete action', () => {
  const source = read('./AdminFileManagement.tsx');
  assert.equal(source.includes('handleDeleteDoc'), true);
  assert.equal(source.includes('deletePropertyFile'), true);
  assert.equal(source.includes('Delete property document'), true);
});

test('document deletion is a restorable soft delete', () => {
  const context = read('../../context/AppContext.tsx');
  assert.equal(context.includes('soft_delete_document'), true);
  assert.equal(context.includes('restorePropertyFile'), true);
  assert.equal(context.includes('Document moved to trash'), true);

  const audit = read('./AdminAudit.tsx');
  assert.equal(audit.includes('Property Documents'), true);
  assert.equal(audit.includes('restorePropertyFile'), true);

  const desk = read('./AdminFileManagement.tsx');
  assert.equal(desk.includes('doc.deletedAt'), true);
});

test('media library uses the brand gold colour for its submit button', () => {
  const source = read('./AdminMediaLibrary.tsx');
  assert.equal(source.includes('bg-[#C5A4D]'), false);
  assert.equal(source.includes('bg-[#C5A46D]'), true);
});
