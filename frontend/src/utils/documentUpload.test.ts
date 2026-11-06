import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
  DOCUMENT_UPLOAD_MAX_BYTES,
  formatFileSize,
  hasViewableDocument,
  isPdfDocument,
  validateDocumentFile,
} from './documentUpload';

const file = (name: string, type: string, size: number) => ({ name, type, size });

test('accepts PDF and image scans within the size limit', () => {
  const pdf = validateDocumentFile(file('allotment-order.pdf', 'application/pdf', 1024 * 900));
  assert.equal(pdf.ok, true);
  assert.equal(pdf.fileType, 'pdf');
  assert.equal(pdf.fileSize, '900 KB');

  const photo = validateDocumentFile(file('cnic-front.JPG', 'image/jpeg', 1024 * 1024 * 3));
  assert.equal(photo.ok, true);
  assert.equal(photo.fileType, 'image');
  assert.equal(photo.fileSize, '3.0 MB');
});

test('falls back to the file extension when the browser omits the MIME type', () => {
  const result = validateDocumentFile(file('transfer-deed.PDF', '', 2048));
  assert.equal(result.ok, true);
  assert.equal(result.fileType, 'pdf');

  const webp = validateDocumentFile(file('scan.webp', '', 4096));
  assert.equal(webp.ok, true);
  assert.equal(webp.fileType, 'image');
});

test('rejects unsupported formats, empty files and oversized scans', () => {
  const gif = validateDocumentFile(file('animation.gif', 'image/gif', 2048));
  assert.equal(gif.ok, false);
  assert.match(gif.error ?? '', /Unsupported file type/);

  const text = validateDocumentFile(file('notes.txt', 'text/plain', 2048));
  assert.equal(text.ok, false);

  const empty = validateDocumentFile(file('empty.pdf', 'application/pdf', 0));
  assert.equal(empty.ok, false);
  assert.match(empty.error ?? '', /empty/);

  const oversized = validateDocumentFile(
    file('huge.pdf', 'application/pdf', DOCUMENT_UPLOAD_MAX_BYTES + 1)
  );
  assert.equal(oversized.ok, false);
  assert.match(oversized.error ?? '', /5 MB limit/);
});

test('formats file sizes for the verification desk', () => {
  assert.equal(formatFileSize(0), '0 KB');
  assert.equal(formatFileSize(512), '512 B');
  assert.equal(formatFileSize(1536), '2 KB');
  assert.equal(formatFileSize(5 * 1024 * 1024), '5.0 MB');
});

test('detects PDF previews from file type, data URLs and .pdf links', () => {
  assert.equal(isPdfDocument('pdf', undefined), true);
  assert.equal(isPdfDocument(undefined, 'data:application/pdf;base64,AAAA'), true);
  assert.equal(isPdfDocument(undefined, 'https://cdn.example.com/deed.pdf?token=1#page=2'), true);
  assert.equal(isPdfDocument('image', 'https://cdn.example.com/deed.pdf'), true);
  assert.equal(isPdfDocument(undefined, 'https://cdn.example.com/deed.jpg'), false);
  assert.equal(isPdfDocument('image', 'data:image/png;base64,AAAA'), false);
  // Demo/seed docs sometimes store an image preview against a PDF label.
  assert.equal(isPdfDocument('pdf', 'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?w=1200'), false);
});

test('legacy "#" placeholders are not treated as viewable documents', () => {
  assert.equal(hasViewableDocument('#'), false);
  assert.equal(hasViewableDocument(''), false);
  assert.equal(hasViewableDocument('   '), false);
  assert.equal(hasViewableDocument('https://cdn.example.com/deed.pdf'), true);
  assert.equal(hasViewableDocument('data:image/png;base64,AAAA'), true);
});
