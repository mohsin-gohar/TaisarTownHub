/**
 * Media deletion policy (Phase 5).
 *
 * A library asset is never hard-deleted. If an active listing still points at
 * its URL, deletion is refused. Otherwise the caller soft-deletes the row.
 */
import type { MediaItem, Project, Property, PropertyFileItem } from '../types';

type PropertyRef = Pick<Property, 'title' | 'images' | 'deletedAt'>;
type ProjectRef = Pick<Project, 'title' | 'coverImage' | 'galleryImages' | 'deletedAt'>;
type DocumentRef = Pick<PropertyFileItem, 'title' | 'url' | 'documentUrl'>;

export function activeMediaReferenceTitles(
  mediaUrl: string,
  properties: PropertyRef[],
  projects: ProjectRef[],
  documents: DocumentRef[] = [],
): string[] {
  const titles: string[] = [];

  for (const property of properties) {
    if (property.deletedAt) continue;
    if (property.images?.includes(mediaUrl)) titles.push(property.title);
  }

  for (const project of projects) {
    if (project.deletedAt) continue;
    if (project.coverImage === mediaUrl || project.galleryImages?.includes(mediaUrl)) {
      titles.push(project.title);
    }
  }

  for (const document of documents) {
    if (document.url === mediaUrl || document.documentUrl === mediaUrl) {
      titles.push(document.title);
    }
  }

  return titles;
}

export type MediaDeleteDecision =
  | { ok: false; code: 'not_found' | 'in_use'; message: string }
  | { ok: true; code: 'soft_delete' };

export function decideMediaDeletion(
  item: Pick<MediaItem, 'title' | 'url' | 'deletedAt'> | undefined,
  properties: PropertyRef[],
  projects: ProjectRef[],
  documents: DocumentRef[] = [],
): MediaDeleteDecision {
  if (!item || item.deletedAt) {
    return { ok: false, code: 'not_found', message: 'Item not found.' };
  }

  const titles = activeMediaReferenceTitles(item.url, properties, projects, documents);
  if (titles.length > 0) {
    const preview = titles.slice(0, 3).join(', ');
    const extra = titles.length > 3 ? '...' : '';
    return {
      ok: false,
      code: 'in_use',
      message: `Cannot delete: File is currently referenced by ${titles.length} active listing(s): ${preview}${extra}. Please unlink or replace the media reference first.`,
    };
  }

  return { ok: true, code: 'soft_delete' };
}

export function stampSoftDelete<T extends { deletedAt?: string; deletedBy?: string }>(
  record: T,
  actor: string,
  at: string,
): T {
  return { ...record, deletedAt: at, deletedBy: actor };
}

export function clearSoftDelete<T extends { deletedAt?: string; deletedBy?: string }>(record: T): T {
  return { ...record, deletedAt: undefined, deletedBy: undefined };
}
