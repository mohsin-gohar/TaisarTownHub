import React, { useCallback, useEffect, useRef, useState } from 'react';
import {
  Download,
  Eye,
  FileLock2,
  FileText,
  FolderPlus,
  RefreshCw,
  ShieldAlert,
  Trash2,
  Upload,
} from 'lucide-react';
import { api } from '../../services/api';
import { useApp } from '../../context/AppContext';
import {
  EnterpriseBadge,
  EnterpriseButton,
  EnterpriseCard,
  EnterpriseEmpty,
  EnterpriseNote,
  EnterprisePage,
  EnterpriseSpinner,
  enterpriseInputClass,
  formatBytes,
  formatDate,
  titleCase,
} from './AdminEnterpriseUi';

interface DocumentVaultProps {
  canUpload: boolean;
  canDelete: boolean;
}

const CATEGORIES = [
  'cnic', 'registry', 'allotment_letter', 'transfer_letter', 'ownership',
  'sale_agreement', 'payment_receipt', 'booking_form', 'map_site_plan',
  'image', 'video', 'pdf', 'other',
];

export const AdminDocumentVault: React.FC<DocumentVaultProps> = ({ canUpload, canDelete }) => {
  const { confirmAction } = useApp();
  const [documents, setDocuments] = useState<any[]>([]);
  const [folders, setFolders] = useState<any[]>([]);
  const [pagination, setPagination] = useState<any>(null);
  const [canUploadServer, setCanUploadServer] = useState(canUpload);
  const [canSeeSensitive, setCanSeeSensitive] = useState(false);

  const [page, setPage] = useState(1);
  const [folderFilter, setFolderFilter] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('');
  const [search, setSearch] = useState('');

  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState('');
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const [file, setFile] = useState<File | null>(null);
  const [title, setTitle] = useState('');
  const [category, setCategory] = useState('other');
  const [folderId, setFolderId] = useState('');
  const [tags, setTags] = useState('');

  const [newFolderName, setNewFolderName] = useState('');
  const fileInputRef = useRef<HTMLInputElement | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    const [documentsBody, foldersBody] = await Promise.all([
      api.getAgencyDocuments({
        page,
        folder_id: folderFilter || undefined,
        category: categoryFilter || undefined,
        search: search.trim() || undefined,
      }),
      api.getDocumentFolders(),
    ]);

    if (!documentsBody || documentsBody.success === false) {
      setError(documentsBody?.message || 'Could not load the document vault. An agency membership is required.');
      setDocuments([]);
      setPagination(null);
    } else {
      setDocuments(Array.isArray(documentsBody.documents) ? documentsBody.documents : []);
      setPagination(documentsBody.pagination ?? null);
      setCanUploadServer(Boolean(documentsBody.can_upload ?? canUpload));
      setCanSeeSensitive(Boolean(documentsBody.can_see_sensitive));
    }
    setFolders(Array.isArray(foldersBody) ? foldersBody : []);
    setLoading(false);
  }, [page, folderFilter, categoryFilter, search, canUpload]);

  useEffect(() => {
    void load();
  }, [load]);

  const handleUpload = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!file) {
      setError('Choose a file to upload.');
      return;
    }
    setBusy('upload');
    setError('');
    setNotice('');
    const formData = new FormData();
    formData.append('file', file);
    if (title.trim()) formData.append('title', title.trim());
    formData.append('category', category);
    if (folderId) formData.append('folder_id', folderId);
    const parsedTags = tags.split(',').map((t) => t.trim()).filter(Boolean).slice(0, 20);
    parsedTags.forEach((tag) => formData.append('tags[]', tag));

    const result = await api.uploadAgencyDocument(formData);
    setBusy('');
    if (!result.ok) {
      setError(result.body?.message || 'Upload failed. Check the file type and size limit.');
      return;
    }
    setFile(null);
    setTitle('');
    setTags('');
    if (fileInputRef.current) fileInputRef.current.value = '';
    setNotice('Document stored in the private vault. Bytes are only streamed to authorised users.');
    await load();
  };

  const handleCreateFolder = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!newFolderName.trim()) return;
    setBusy('folder');
    setError('');
    const result = await api.createDocumentFolder({ name: newFolderName.trim() });
    setBusy('');
    if (!result.ok) {
      setError(result.body?.message || 'Folder could not be created.');
      return;
    }
    setNewFolderName('');
    setNotice('Folder created.');
    await load();
  };

  const handleDeleteFolder = async (folder: any) => {
    const confirmed = await confirmAction(
      `Delete folder "${folder.name}"? Only empty folders can be deleted.`,
      { title: 'Delete folder', confirmLabel: 'Delete folder' },
    );
    if (!confirmed) return;
    setBusy(`folder-${folder.id}`);
    setNotice('');
    const ok = await api.deleteDocumentFolder(String(folder.id));
    setBusy('');
    if (!ok) {
      setError('Folder could not be deleted. Remove its documents and sub-folders first.');
      return;
    }
    setNotice('Folder deleted.');
    await load();
  };

  const handleDownload = async (document: any) => {
    setBusy(`download-${document.id}`);
    setError('');
    const result = await api.downloadAgencyDocument(String(document.id));
    setBusy('');
    if (!result) {
      setError('Download failed or you do not have access to this document.');
      return;
    }
    const url = URL.createObjectURL(result.blob);
    const anchor = window.document.createElement('a');
    anchor.href = url;
    anchor.download = result.filename || document.original_name || 'document';
    anchor.click();
    URL.revokeObjectURL(url);
    setNotice('Authenticated download started.');
  };

  const handlePreview = async (document: any) => {
    setBusy(`preview-${document.id}`);
    setError('');
    // Open the tab synchronously so the browser keeps the user gesture;
    // navigating it after the authenticated fetch avoids popup blocking.
    const previewWindow = window.open('', '_blank');
    const blob = await api.previewAgencyDocument(String(document.id));
    setBusy('');
    if (!blob) {
      previewWindow?.close();
      setError('Preview is only available for viewable file types.');
      return;
    }
    const url = URL.createObjectURL(blob);
    if (previewWindow) {
      previewWindow.location.href = url;
    } else {
      window.open(url, '_blank', 'noopener,noreferrer');
    }
    // Give the new tab time to read the object URL before revoking it.
    window.setTimeout(() => URL.revokeObjectURL(url), 60_000);
  };

  const handleDelete = async (document: any) => {
    const confirmed = await confirmAction(
      `Soft-delete "${document.title}"? The file is retained for audit and can be restored by an administrator.`,
      { title: 'Soft-delete document', confirmLabel: 'Soft-delete' },
    );
    if (!confirmed) return;
    setBusy(`delete-${document.id}`);
    setError('');
    const ok = await api.deleteAgencyDocument(String(document.id));
    setBusy('');
    if (!ok) {
      setError('Document could not be deleted.');
      return;
    }
    setNotice('Document soft-deleted.');
    await load();
  };

  const totalPages = Number(pagination?.last_page ?? 1);

  return (
    <EnterprisePage
      title="Document Vault"
      subtitle="Private files are stored off the public web root and streamed only after policy checks."
      actions={
        <EnterpriseButton variant="secondary" onClick={() => void load()}>
          <RefreshCw className={`w-3.5 h-3.5 ${loading ? 'animate-spin' : ''}`} /> Refresh
        </EnterpriseButton>
      }
    >
      {error && <EnterpriseNote tone="error">{error}</EnterpriseNote>}
      {notice && <EnterpriseNote tone="success">{notice}</EnterpriseNote>}

      <div className="grid grid-cols-1 xl:grid-cols-12 gap-6">
        <div className="xl:col-span-8 space-y-6">
          <EnterpriseCard
            title="Vault Contents"
            subtitle={`${pagination?.total ?? documents.length} document(s) · sensitive files are hidden unless cleared`}
            actions={
              canSeeSensitive ? <EnterpriseBadge tone="gold">Sensitive clearance active</EnterpriseBadge> : undefined
            }
          >
            <div className="grid sm:grid-cols-3 gap-2 mb-4">
              <input
                className={enterpriseInputClass}
                placeholder="Search title or filename…"
                value={search}
                onChange={(e) => { setSearch(e.target.value); setPage(1); }}
              />
              <select
                className={enterpriseInputClass}
                value={folderFilter}
                onChange={(e) => { setFolderFilter(e.target.value); setPage(1); }}
              >
                <option value="">All folders</option>
                {folders.map((folder) => (
                  <option key={folder.id} value={folder.id}>{folder.path || folder.name}</option>
                ))}
              </select>
              <select
                className={enterpriseInputClass}
                value={categoryFilter}
                onChange={(e) => { setCategoryFilter(e.target.value); setPage(1); }}
              >
                <option value="">All categories</option>
                {CATEGORIES.map((key) => (
                  <option key={key} value={key}>{titleCase(key)}</option>
                ))}
              </select>
            </div>

            {loading ? (
              <EnterpriseSpinner label="Opening the vault…" />
            ) : documents.length === 0 ? (
              <EnterpriseEmpty
                title="No documents match"
                hint="Upload a file or clear the filters to see the full vault."
              />
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-left text-xs">
                  <thead className="bg-[#0B1220] text-slate-400 uppercase tracking-wider text-[10px]">
                    <tr>
                      <th className="py-2.5 px-3 rounded-l-lg">Document</th>
                      <th className="py-2.5 px-3">Category</th>
                      <th className="py-2.5 px-3">Size</th>
                      <th className="py-2.5 px-3">Uploaded</th>
                      <th className="py-2.5 px-3 rounded-r-lg text-right">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-800/80">
                    {documents.map((document) => (
                      <tr key={document.id} className="hover:bg-slate-800/30 align-middle">
                        <td className="py-3 px-3 max-w-[260px]">
                          <div className="flex items-start gap-2">
                            <FileText className="w-4 h-4 text-[#C5A46D] shrink-0 mt-0.5" />
                            <div className="min-w-0">
                              <div className="font-semibold text-white truncate">{document.title || document.original_name}</div>
                              <div className="text-[10px] text-slate-500 truncate">
                                {document.folder?.path ? `${document.folder.path}/` : ''}{document.original_name || '—'}
                              </div>
                              {document.is_sensitive && (
                                <span className="inline-flex items-center gap-1 mt-1 text-[9px] text-amber-300">
                                  <ShieldAlert className="w-3 h-3" /> Sensitive
                                </span>
                              )}
                            </div>
                          </div>
                        </td>
                        <td className="py-3 px-3 text-slate-300">{titleCase(document.category || 'other')}</td>
                        <td className="py-3 px-3 text-slate-400">{formatBytes(document.size_bytes)}</td>
                        <td className="py-3 px-3 text-slate-400">{formatDate(document.created_at)}</td>
                        <td className="py-3 px-3">
                          <div className="flex items-center justify-end gap-1.5">
                            <EnterpriseButton
                              variant="secondary"
                              disabled={busy === `preview-${document.id}`}
                              onClick={() => void handlePreview(document)}
                              title="Preview"
                            >
                              <Eye className="w-3.5 h-3.5" />
                            </EnterpriseButton>
                            <EnterpriseButton
                              variant="secondary"
                              disabled={busy === `download-${document.id}`}
                              onClick={() => void handleDownload(document)}
                              title="Download"
                            >
                              <Download className="w-3.5 h-3.5" />
                            </EnterpriseButton>
                            {canDelete && (
                              <EnterpriseButton
                                variant="danger"
                                disabled={busy === `delete-${document.id}`}
                                onClick={() => void handleDelete(document)}
                                title="Soft delete"
                              >
                                <Trash2 className="w-3.5 h-3.5" />
                              </EnterpriseButton>
                            )}
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}

            {totalPages > 1 && (
              <div className="flex items-center justify-between mt-4 text-[11px] text-slate-400">
                <span>Page {pagination?.current_page ?? page} of {totalPages}</span>
                <div className="flex gap-2">
                  <EnterpriseButton variant="secondary" disabled={page <= 1} onClick={() => setPage((p) => Math.max(1, p - 1))}>
                    Previous
                  </EnterpriseButton>
                  <EnterpriseButton variant="secondary" disabled={page >= totalPages} onClick={() => setPage((p) => p + 1)}>
                    Next
                  </EnterpriseButton>
                </div>
              </div>
            )}
          </EnterpriseCard>
        </div>

        <div className="xl:col-span-4 space-y-6">
          <EnterpriseCard title="Upload Document" subtitle="Multipart upload straight into private storage">
            {!canUploadServer ? (
              <EnterpriseNote tone="info">`documents.upload` is required to add files.</EnterpriseNote>
            ) : (
              <form onSubmit={handleUpload} className="space-y-3">
                <div>
                  <label className="block text-[10px] text-slate-400 mb-1">File</label>
                  <input
                    ref={fileInputRef}
                    type="file"
                    onChange={(e) => setFile(e.target.files?.[0] ?? null)}
                    className="block w-full text-[11px] text-slate-300 file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-[#C5A46D] file:text-[#101C2C] file:font-bold file:cursor-pointer"
                    required
                  />
                  <p className="text-[10px] text-slate-500 mt-1">
                    PDF, Office docs, images and video. Sensitive categories require clearance.
                  </p>
                </div>
                <div>
                  <label className="block text-[10px] text-slate-400 mb-1">Title (optional)</label>
                  <input
                    className={enterpriseInputClass}
                    value={title}
                    onChange={(e) => setTitle(e.target.value)}
                    placeholder="Defaults to the original filename"
                  />
                </div>
                <div className="grid grid-cols-2 gap-2">
                  <div>
                    <label className="block text-[10px] text-slate-400 mb-1">Category</label>
                    <select className={enterpriseInputClass} value={category} onChange={(e) => setCategory(e.target.value)}>
                      {CATEGORIES.map((key) => (
                        <option key={key} value={key}>{titleCase(key)}</option>
                      ))}
                    </select>
                  </div>
                  <div>
                    <label className="block text-[10px] text-slate-400 mb-1">Folder</label>
                    <select className={enterpriseInputClass} value={folderId} onChange={(e) => setFolderId(e.target.value)}>
                      <option value="">No folder</option>
                      {folders.map((folder) => (
                        <option key={folder.id} value={folder.id}>{folder.path || folder.name}</option>
                      ))}
                    </select>
                  </div>
                </div>
                <div>
                  <label className="block text-[10px] text-slate-400 mb-1">Tags (comma separated)</label>
                  <input
                    className={enterpriseInputClass}
                    value={tags}
                    onChange={(e) => setTags(e.target.value)}
                    placeholder="registry, 2026, sector-a"
                  />
                </div>
                <EnterpriseButton type="submit" disabled={busy === 'upload'} className="w-full">
                  <Upload className="w-3.5 h-3.5" /> {busy === 'upload' ? 'Uploading…' : 'Upload to vault'}
                </EnterpriseButton>
              </form>
            )}
          </EnterpriseCard>

          <EnterpriseCard title="Folders" subtitle="Materialised paths, unique per agency">
            {folders.length === 0 ? (
              <EnterpriseEmpty title="No folders yet" />
            ) : (
              <div className="space-y-1.5 mb-3 max-h-52 overflow-y-auto">
                {folders.map((folder) => (
                  <div key={folder.id} className="flex items-center justify-between gap-2 px-2.5 py-2 rounded-lg bg-[#0B1220] border border-slate-800">
                    <div className="min-w-0">
                      <div className="text-[11px] font-semibold text-white truncate">{folder.name}</div>
                      <div className="text-[9px] text-slate-500 truncate">{folder.path} · {folder.document_count ?? 0} file(s)</div>
                    </div>
                    {canDelete && (
                      <button
                        onClick={() => void handleDeleteFolder(folder)}
                        disabled={busy === `folder-${folder.id}`}
                        className="text-rose-300 hover:text-rose-200 cursor-pointer disabled:opacity-50"
                        title="Delete empty folder"
                      >
                        <Trash2 className="w-3.5 h-3.5" />
                      </button>
                    )}
                  </div>
                ))}
              </div>
            )}

            {canUploadServer && (
              <form onSubmit={handleCreateFolder} className="flex gap-2 border-t border-slate-800 pt-3">
                <input
                  className={enterpriseInputClass}
                  value={newFolderName}
                  onChange={(e) => setNewFolderName(e.target.value)}
                  placeholder="New folder name"
                />
                <EnterpriseButton type="submit" variant="secondary" disabled={busy === 'folder'}>
                  <FolderPlus className="w-3.5 h-3.5" />
                </EnterpriseButton>
              </form>
            )}
          </EnterpriseCard>

          <EnterpriseCard title="Private by design">
            <div className="flex items-start gap-2 text-[11px] text-slate-400">
              <FileLock2 className="w-4 h-4 text-[#C5A46D] shrink-0 mt-0.5" />
              <p>
                Files live on the private `documents` disk. Downloads and previews always pass through the
                authenticated API, and every access is written to the audit log.
              </p>
            </div>
          </EnterpriseCard>
        </div>
      </div>
    </EnterprisePage>
  );
};
