import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  Eye,
  EyeOff,
  FileText,
  Image as ImageIcon,
  Link2,
  RefreshCw,
  Trash2,
  Upload,
  Video,
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

interface MediaStudioProps {
  canUpload: boolean;
}

const MEDIA_TYPES = ['image', 'video', 'document', 'banner', 'brochure', 'floor_plan'] as const;

const MediaPreview: React.FC<{ item: any }> = ({ item }) => {
  const [url, setUrl] = useState('');
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    let active = true;
    let objectUrl = '';
    if (!item.id || (item.type !== 'image' && item.type !== 'video' && item.type !== 'banner')) {
      return () => { active = false; };
    }
    void api.fetchAgencyMediaBlob(String(item.id)).then((blob) => {
      if (!active || !blob) {
        if (active && !blob) setFailed(true);
        return;
      }
      objectUrl = URL.createObjectURL(blob);
      setUrl(objectUrl);
    });
    return () => {
      active = false;
      if (objectUrl) URL.revokeObjectURL(objectUrl);
    };
  }, [item.id, item.type]);

  if (url && item.type === 'video') {
    return <video src={url} controls className="w-full h-36 object-cover rounded-lg bg-black" />;
  }
  if (url) {
    return <img src={url} alt={item.title || 'Media'} className="w-full h-36 object-cover rounded-lg bg-slate-900" />;
  }
  return (
    <div className="w-full h-36 rounded-lg bg-[#07141E] border border-slate-800 flex flex-col items-center justify-center gap-1 text-slate-500">
      {item.type === 'video' ? <Video className="w-6 h-6" /> : failed ? <FileText className="w-6 h-6" /> : <ImageIcon className="w-6 h-6" />}
      <span className="text-[9px]">{failed ? 'Preview unavailable' : 'Loading private preview…'}</span>
    </div>
  );
};

export const AdminMediaStudio: React.FC<MediaStudioProps> = ({ canUpload }) => {
  const { confirmAction } = useApp();
  const [media, setMedia] = useState<any[]>([]);
  const [pagination, setPagination] = useState<any>(null);

  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState('');
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const [typeFilter, setTypeFilter] = useState('');
  const [visibilityFilter, setVisibilityFilter] = useState('');
  const [search, setSearch] = useState('');

  const [mode, setMode] = useState<'file' | 'url'>('file');
  const [file, setFile] = useState<File | null>(null);
  const [externalUrl, setExternalUrl] = useState('');
  const [title, setTitle] = useState('');
  const [folder, setFolder] = useState('General');
  const [type, setType] = useState('');
  const [visibility, setVisibility] = useState<'private' | 'public'>('private');
  const [description, setDescription] = useState('');
  const fileInputRef = useRef<HTMLInputElement | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    const body = await api.getMediaItems();
    if (!body || body.success === false) {
      setError(body?.message || 'Could not load the media library. An agency membership is required.');
      setMedia([]);
      setPagination(null);
    } else {
      setMedia(Array.isArray(body.media) ? body.media : []);
      setPagination(body.pagination ?? null);
    }
    setLoading(false);
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const visibleMedia = useMemo(() => {
    const query = search.trim().toLowerCase();
    return media.filter((item) => {
      if (typeFilter && item.type !== typeFilter) return false;
      if (visibilityFilter && item.visibility !== visibilityFilter) return false;
      if (!query) return true;
      return [item.title, item.folder, item.original_name]
        .filter(Boolean)
        .some((value) => String(value).toLowerCase().includes(query));
    });
  }, [media, typeFilter, visibilityFilter, search]);

  const resetForm = () => {
    setFile(null);
    setExternalUrl('');
    setTitle('');
    setType('');
    setDescription('');
    if (fileInputRef.current) fileInputRef.current.value = '';
  };

  const handleUpload = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!title.trim()) {
      setError('A title is required.');
      return;
    }
    if (mode === 'file' && !file) {
      setError('Choose a file to upload.');
      return;
    }
    if (mode === 'url' && !externalUrl.trim()) {
      setError('Enter the external media URL.');
      return;
    }

    setBusy('upload');
    setError('');
    setNotice('');
    let result: { ok: boolean; status: number; body: any };

    if (mode === 'file' && file) {
      const formData = new FormData();
      formData.append('file', file);
      formData.append('title', title.trim());
      formData.append('folder', folder.trim() || 'General');
      if (type) formData.append('type', type);
      formData.append('visibility', visibility);
      if (description.trim()) formData.append('description', description.trim());
      result = await api.uploadAgencyMedia(formData);
    } else {
      result = await api.createMediaItem({
        url: externalUrl.trim(),
        title: title.trim(),
        folder: folder.trim() || 'General',
        type: type || 'image',
        visibility,
        description: description.trim() || undefined,
      }).then((body) => ({ ok: Boolean(body?.success), status: body?.success ? 200 : 400, body: body ?? {} }));
    }

    setBusy('');
    if (!result.ok) {
      setError(result.body?.message || 'Media could not be saved.');
      return;
    }
    resetForm();
    setNotice(
      visibility === 'public'
        ? 'Media saved. Public media is served from the public media endpoint.'
        : 'Media saved to private storage. Preview and delivery require authentication.'
    );
    await load();
  };

  const handleVisibility = async (item: any) => {
    const next = item.visibility === 'public' ? 'private' : 'public';
    setBusy(`visibility-${item.id}`);
    setError('');
    setNotice('');
    const result = await api.updateMediaItem(String(item.id), { visibility: next });
    setBusy('');
    if (!result || result.success === false) {
      setError(result?.message || 'Visibility could not be updated.');
      return;
    }
    setNotice(`"${item.title}" is now ${next}.`);
    await load();
  };

  const handleDelete = async (item: any) => {
    const confirmed = await confirmAction(
      `Delete "${item.title}"? This is a soft delete and the file is retained for audit.`,
      { title: 'Soft-delete media', confirmLabel: 'Soft-delete' },
    );
    if (!confirmed) return;
    setBusy(`delete-${item.id}`);
    setError('');
    const ok = await api.deleteMediaItem(String(item.id));
    setBusy('');
    if (!ok) {
      setError('Media could not be deleted.');
      return;
    }
    setNotice('Media soft-deleted.');
    await load();
  };

  return (
    <EnterprisePage
      title="Media Studio"
      subtitle="Upload files or register external URLs; private media is streamed only through authenticated endpoints."
      actions={
        <EnterpriseButton variant="secondary" onClick={() => void load()}>
          <RefreshCw className={`w-3.5 h-3.5 ${loading ? 'animate-spin' : ''}`} /> Refresh
        </EnterpriseButton>
      }
    >
      {error && <EnterpriseNote tone="error">{error}</EnterpriseNote>}
      {notice && <EnterpriseNote tone="success">{notice}</EnterpriseNote>}

      <div className="grid grid-cols-1 xl:grid-cols-12 gap-6">
        <div className="xl:col-span-8 space-y-4">
          <div className="grid sm:grid-cols-3 gap-2">
            <input
              className={enterpriseInputClass}
              placeholder="Search title, folder or filename…"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
            <select className={enterpriseInputClass} value={typeFilter} onChange={(e) => setTypeFilter(e.target.value)}>
              <option value="">All types</option>
              {MEDIA_TYPES.map((key) => (
                <option key={key} value={key}>{titleCase(key)}</option>
              ))}
            </select>
            <select className={enterpriseInputClass} value={visibilityFilter} onChange={(e) => setVisibilityFilter(e.target.value)}>
              <option value="">All visibility</option>
              <option value="public">Public</option>
              <option value="private">Private</option>
            </select>
          </div>

          {loading ? (
            <EnterpriseSpinner label="Loading media library…" />
          ) : visibleMedia.length === 0 ? (
            <EnterpriseCard>
              <EnterpriseEmpty title="No media matches" hint="Upload an image, video or document, or adjust the filters." />
            </EnterpriseCard>
          ) : (
            <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
              {visibleMedia.map((item) => (
                <EnterpriseCard key={item.id} className="!p-3">
                  <MediaPreview item={item} />
                  <div className="mt-2.5 space-y-1">
                    <div className="flex items-start justify-between gap-2">
                      <h3 className="text-xs font-bold text-white truncate">{item.title}</h3>
                      <EnterpriseBadge tone={item.visibility === 'public' ? 'green' : 'slate'}>
                        {titleCase(item.visibility || 'private')}
                      </EnterpriseBadge>
                    </div>
                    <div className="flex flex-wrap items-center gap-x-2 text-[10px] text-slate-500">
                      <span>{titleCase(item.type || 'file')}</span>
                      <span>·</span>
                      <span>{formatBytes(item.size_bytes)}</span>
                      {item.dimensions && <><span>·</span><span>{item.dimensions}</span></>}
                    </div>
                    <div className="text-[9px] text-slate-500">
                      {item.folder || 'General'} · {formatDate(item.created_at)}
                    </div>
                    {canUpload && (
                      <div className="flex items-center gap-1.5 pt-1">
                        <EnterpriseButton
                          variant="secondary"
                          className="flex-1"
                          disabled={busy === `visibility-${item.id}`}
                          onClick={() => void handleVisibility(item)}
                        >
                          {item.visibility === 'public' ? (
                            <><EyeOff className="w-3 h-3" /> Make private</>
                          ) : (
                            <><Eye className="w-3 h-3" /> Publish</>
                          )}
                        </EnterpriseButton>
                        <EnterpriseButton
                          variant="danger"
                          disabled={busy === `delete-${item.id}`}
                          onClick={() => void handleDelete(item)}
                        >
                          <Trash2 className="w-3.5 h-3.5" />
                        </EnterpriseButton>
                      </div>
                    )}
                  </div>
                </EnterpriseCard>
              ))}
            </div>
          )}

          {pagination && pagination.last_page > 1 && (
            <p className="text-[11px] text-slate-500">
              Showing the first {media.length} of {pagination.total} assets. Use filters to narrow the library.
            </p>
          )}
        </div>

        <div className="xl:col-span-4 space-y-6">
          <EnterpriseCard title="Add Media" subtitle="Multipart upload or external URL registration">
            {!canUpload ? (
              <EnterpriseNote tone="info">`upload_media` or `manage_media` is required to add media.</EnterpriseNote>
            ) : (
              <form onSubmit={handleUpload} className="space-y-2.5">
                <div className="grid grid-cols-2 gap-2 p-1 rounded-lg bg-[#07141E] border border-slate-800">
                  <button
                    type="button"
                    onClick={() => setMode('file')}
                    className={`px-2 py-1.5 rounded-md text-[10px] font-bold cursor-pointer ${
                      mode === 'file' ? 'bg-[#E7C878] text-[#0B1220]' : 'text-slate-400'
                    }`}
                  >
                    <Upload className="w-3 h-3 inline mr-1" /> File
                  </button>
                  <button
                    type="button"
                    onClick={() => setMode('url')}
                    className={`px-2 py-1.5 rounded-md text-[10px] font-bold cursor-pointer ${
                      mode === 'url' ? 'bg-[#E7C878] text-[#0B1220]' : 'text-slate-400'
                    }`}
                  >
                    <Link2 className="w-3 h-3 inline mr-1" /> URL
                  </button>
                </div>

                {mode === 'file' ? (
                  <input
                    ref={fileInputRef}
                    type="file"
                    accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx"
                    onChange={(e) => setFile(e.target.files?.[0] ?? null)}
                    className="block w-full text-[11px] text-slate-300 file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-[#E7C878] file:text-[#0B1220] file:font-bold file:cursor-pointer"
                  />
                ) : (
                  <input
                    className={enterpriseInputClass}
                    placeholder="https://example.com/image.jpg"
                    value={externalUrl}
                    onChange={(e) => setExternalUrl(e.target.value)}
                  />
                )}

                <input
                  className={enterpriseInputClass}
                  placeholder="Title *"
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  required
                />
                <div className="grid grid-cols-2 gap-2">
                  <input
                    className={enterpriseInputClass}
                    placeholder="Folder"
                    value={folder}
                    onChange={(e) => setFolder(e.target.value)}
                  />
                  <select className={enterpriseInputClass} value={type} onChange={(e) => setType(e.target.value)}>
                    <option value="">Auto-detect</option>
                    {MEDIA_TYPES.map((key) => (
                      <option key={key} value={key}>{titleCase(key)}</option>
                    ))}
                  </select>
                </div>
                <select
                  className={enterpriseInputClass}
                  value={visibility}
                  onChange={(e) => setVisibility(e.target.value as 'private' | 'public')}
                >
                  <option value="private">Private (authenticated delivery)</option>
                  <option value="public">Public (marketing pages)</option>
                </select>
                <textarea
                  className={`${enterpriseInputClass} min-h-[60px]`}
                  placeholder="Description"
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                />
                <EnterpriseButton
                  type="submit"
                  disabled={busy === 'upload'}
                  className="w-full"
                >
                  <Upload className="w-3.5 h-3.5" /> {busy === 'upload' ? 'Saving…' : 'Save media'}
                </EnterpriseButton>
              </form>
            )}
          </EnterpriseCard>

          <EnterpriseCard title="Delivery rules">
            <ul className="space-y-2 text-[11px] text-slate-400 list-disc pl-4">
              <li>Private files never receive a public URL; previews use authenticated blob reads.</li>
              <li>Public files are served only when `visibility = public`.</li>
              <li>Deletion is soft — bytes stay on the private disk for audit and recovery.</li>
              <li>Every upload, update and delete is attributed to your account in the audit log.</li>
            </ul>
          </EnterpriseCard>
        </div>
      </div>
    </EnterprisePage>
  );
};
