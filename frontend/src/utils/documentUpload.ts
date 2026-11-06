/**
 * Document upload rules for the admin File Verification desk.
 *
 * Staff upload scanned property documents (PDF or a phone photo of the
 * original). Keeping validation in one pure module makes the limits
 * testable without a DOM and keeps the UI and any future uploader aligned.
 */

export const DOCUMENT_UPLOAD_MAX_BYTES = 5 * 1024 * 1024;

export const DOCUMENT_UPLOAD_ACCEPT =
  'application/pdf,image/jpeg,image/png,image/webp,.pdf,.jpg,.jpeg,.png,.webp';

export type DocumentFileKind = 'pdf' | 'image';

export interface UploadFileLike {
  name: string;
  type: string;
  size: number;
}

export interface DocumentValidationResult {
  ok: boolean;
  error?: string;
  kind?: DocumentFileKind;
  /** Value stored on PropertyFileItem.fileType. */
  fileType?: 'pdf' | 'image';
  /** Human-readable size stored on PropertyFileItem.fileSize. */
  fileSize?: string;
}

const MIME_TO_KIND: Record<string, DocumentFileKind> = {
  'application/pdf': 'pdf',
  'image/jpeg': 'image',
  'image/jpg': 'image',
  'image/png': 'image',
  'image/webp': 'image',
};

const EXTENSION_TO_KIND: Record<string, DocumentFileKind> = {
  pdf: 'pdf',
  jpg: 'image',
  jpeg: 'image',
  png: 'image',
  webp: 'image',
};

/** File size in bytes → compact label such as "820 KB" or "3.4 MB". */
export function formatFileSize(bytes: number): string {
  if (!Number.isFinite(bytes) || bytes <= 0) return '0 KB';
  if (bytes < 1024) return `${Math.round(bytes)} B`;
  const kb = bytes / 1024;
  if (kb < 1024) return `${Math.round(kb)} KB`;
  return `${(kb / 1024).toFixed(1)} MB`;
}

/**
 * Validate a selected document. Browsers occasionally leave `type` empty
 * (older Android pickers), so the extension is used as a fallback.
 */
export function validateDocumentFile(file: UploadFileLike): DocumentValidationResult {
  const extension = file.name.split('.').pop()?.toLowerCase() ?? '';
  const kind = MIME_TO_KIND[file.type.toLowerCase()] ?? EXTENSION_TO_KIND[extension];

  if (!kind) {
    return {
      ok: false,
      error: 'Unsupported file type. Upload a PDF, JPG, PNG, or WebP scan.',
    };
  }

  if (!file.size) {
    return { ok: false, error: 'The selected file is empty. Please choose another scan.' };
  }

  if (file.size > DOCUMENT_UPLOAD_MAX_BYTES) {
    return {
      ok: false,
      error: 'File exceeds the 5 MB limit. Compress the scan or choose a smaller file.',
    };
  }

  return {
    ok: true,
    kind,
    fileType: kind,
    fileSize: formatFileSize(file.size),
  };
}

/**
 * Decide whether the preview should be an embedded PDF viewer or an image.
 * Handles uploaded data URLs, hosted `.pdf` links, and the fileType recorded
 * at upload time.
 */
export function isPdfDocument(fileType?: string, url?: string): boolean {
  if (!url) return fileType === 'pdf';

  const lower = url.toLowerCase();
  if (lower.startsWith('data:application/pdf')) return true;

  // Seed/demo records can carry a PDF fileType but point at a hosted preview
  // image. A clear image URL always renders as an image.
  if (lower.startsWith('data:image/')) return false;
  if (lower.includes('images.unsplash.com') || lower.includes('images.pexels.com')) return false;
  const withoutQuery = lower.split('#')[0].split('?')[0];
  if (/\.(jpe?g|png|webp|gif|avif)$/.test(withoutQuery)) return false;

  if (fileType === 'pdf') return true;
  return withoutQuery.endsWith('.pdf');
}

/** URL is actually viewable (not the legacy placeholder "#"). */
export function hasViewableDocument(url?: string): boolean {
  const value = (url ?? '').trim();
  return value !== '' && value !== '#';
}
