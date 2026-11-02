import React, { useState } from 'react';
import { useApp } from '../../context/AppContext';
import {
  FileCheck,
  Plus,
  Search,
  Filter,
  CheckCircle2,
  XCircle,
  AlertTriangle,
  Clock,
  ExternalLink,
  ShieldCheck,
  FileText,
  Upload,
  Calendar,
  X,
  Eye
} from 'lucide-react';
import { PropertyFileItem, DocumentCategory, DocumentVerificationStatus } from '../../types';
import { hasPermission } from '../../utils/logic';

export const AdminFileManagement: React.FC = () => {
  const { propertyFiles, verifyPropertyFile, addPropertyFile, properties, showToast, currentUser } = useApp();

  // P0-4: verify/reject are verifier actions. Operators (upload-only role) get
  // the upload path but never the decision buttons — and the context action
  // gates enforce this regardless of what the UI renders.
  const canVerify = hasPermission(currentUser.role, 'verify_documents');

  const [searchQuery, setSearchQuery] = useState('');
  const [filterStatus, setFilterStatus] = useState<string>('all');
  const [filterCategory, setFilterCategory] = useState<string>('all');

  const [isUploadModalOpen, setIsUploadModalOpen] = useState(false);
  const [selectedDocPreview, setSelectedDocPreview] = useState<PropertyFileItem | null>(null);

  const [rejectModalDocId, setRejectModalDocId] = useState<string | null>(null);
  const [rejectReason, setRejectReason] = useState('');

  // Verify now requires a genuine reviewer note (was a hard-coded fake line).
  const [verifyModalDocId, setVerifyModalDocId] = useState<string | null>(null);
  const [verifyNote, setVerifyNote] = useState('');

  const [formData, setFormData] = useState({
    title: '',
    category: 'Allotment Order' as DocumentCategory,
    fileNumber: 'TT-63-A-066',
    propertyId: properties[0]?.id || '',
    documentUrl: 'https://images.unsplash.com/photo-1568605117036-5fe5e7bab0b7?auto=format&fit=crop&w=1200&q=80',
    notes: 'Official document copy'
  });

  const filteredDocs = propertyFiles.filter((doc) => {
    const matchesSearch =
      doc.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
      (doc.fileNumber || '').toLowerCase().includes(searchQuery.toLowerCase()) ||
      doc.plotNumber?.toLowerCase().includes(searchQuery.toLowerCase()) ||
      doc.sector?.toLowerCase().includes(searchQuery.toLowerCase());

    const matchesStatus = filterStatus === 'all' || doc.verificationStatus === filterStatus;
    const matchesCategory = filterCategory === 'all' || doc.category === filterCategory;

    return matchesSearch && matchesStatus && matchesCategory;
  });

  const handleOpenVerify = (docId: string) => {
    setVerifyModalDocId(docId);
    setVerifyNote('');
  };

  const handleConfirmVerify = () => {
    if (!verifyModalDocId) return;
    if (!verifyNote.trim()) {
      showToast('error', 'A reviewer note is required to verify a document.');
      return;
    }
    verifyPropertyFile(verifyModalDocId, 'Verified', verifyNote.trim());
    setVerifyModalDocId(null);
  };

  const handleOpenReject = (docId: string) => {
    setRejectModalDocId(docId);
    setRejectReason('Discrepancy with MDA Master Record / Requires Stamp Revision.');
  };

  const handleConfirmReject = () => {
    if (!rejectModalDocId) return;
    verifyPropertyFile(rejectModalDocId, 'Rejected', rejectReason);
    showToast('info', 'Document status marked as Rejected.');
    setRejectModalDocId(null);
  };

  const handleUploadSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.title.trim()) {
      showToast('error', 'Please enter document title.');
      return;
    }

    const linkedProp = properties.find((p) => p.id === formData.propertyId);

    addPropertyFile({
      title: formData.title,
      category: formData.category,
      fileNumber: formData.fileNumber,
      plotNumber: linkedProp?.plotNumber,
      sector: linkedProp?.sector,
      propertyId: formData.propertyId,
      documentUrl: formData.documentUrl,
      verificationStatus: 'Pending Verification',
      verifiedBy: '',
      verifiedAt: '',
      notes: formData.notes
    });

    showToast('success', 'Property document uploaded and queued for verification.');
    setIsUploadModalOpen(false);
  };

  const pendingCount = propertyFiles.filter((d) => d.verificationStatus === 'Pending Verification').length;
  const verifiedCount = propertyFiles.filter((d) => d.verificationStatus === 'Verified').length;
  const rejectedCount = propertyFiles.filter((d) => d.verificationStatus === 'Rejected').length;

  return (
    <div className="space-y-6 animate-fadeIn">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-6 rounded-2xl bg-gradient-to-r from-[#101C2C] to-[#1A2638] border border-[#C5A46D]/30 shadow-xl">
        <div>
          <div className="flex items-center gap-2">
            <h2 className="font-serif-luxury text-2xl font-bold text-white">
              Property File & Legal Document Desk
            </h2>
            <span className="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-[#C5A46D]/20 text-[#C5A46D] border border-[#C5A46D]/40">
              MDA Scheme 45 Compliance
            </span>
          </div>
          <p className="text-xs text-slate-300 mt-1">
            Verification desk for Allotment Orders, Transfer Deeds, NDC Certificates, Site Plans, and Power of Attorney records.
          </p>
        </div>

        <button
          onClick={() => setIsUploadModalOpen(true)}
          className="px-4 py-2.5 rounded-xl bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C] font-bold text-xs flex items-center justify-center gap-2 cursor-pointer shadow-lg shadow-[#C5A46D]/20"
        >
          <Upload className="w-4 h-4" />
          <span>Upload Document</span>
        </button>
      </div>

      {/* 3 Metric Pills */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-6">
        <div className="bg-[#101C2C] p-5 rounded-xl border border-slate-800 shadow">
          <div className="text-xs text-slate-400 font-semibold uppercase flex items-center justify-between">
            <span>Internally Verified Documents</span>
            <CheckCircle2 className="w-4 h-4 text-emerald-400" />
          </div>
          <div className="text-2xl font-bold font-serif-luxury text-emerald-400 mt-2">
            {verifiedCount}
          </div>
          <div className="text-[11px] text-slate-400 mt-1">Legally cleared for immediate sale & transfer</div>
        </div>

        <div className="bg-[#101C2C] p-5 rounded-xl border border-slate-800 shadow">
          <div className="text-xs text-slate-400 font-semibold uppercase flex items-center justify-between">
            <span>Pending Verification</span>
            <Clock className="w-4 h-4 text-amber-400" />
          </div>
          <div className="text-2xl font-bold font-serif-luxury text-amber-400 mt-2">
            {pendingCount}
          </div>
          <div className="text-[11px] text-amber-400/80 mt-1">Awaiting legal scrutiny / MDA validation</div>
        </div>

        <div className="bg-[#101C2C] p-5 rounded-xl border border-slate-800 shadow">
          <div className="text-xs text-slate-400 font-semibold uppercase flex items-center justify-between">
            <span>Rejected / Exceptions</span>
            <XCircle className="w-4 h-4 text-rose-400" />
          </div>
          <div className="text-2xl font-bold font-serif-luxury text-rose-400 mt-2">
            {rejectedCount}
          </div>
          <div className="text-[11px] text-rose-400/80 mt-1">Requires correction or original paper verification</div>
        </div>
      </div>

      {/* Search and Category Filters */}
      <div className="flex flex-col md:flex-row items-center justify-between gap-4 bg-[#101C2C] p-4 rounded-xl border border-slate-800">
        <div className="relative w-full md:w-80">
          <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            placeholder="Search by file #, plot #, title..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="w-full bg-[#0B1220] text-white text-xs pl-9 pr-4 py-2.5 rounded-lg border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
          />
        </div>

        <div className="flex items-center gap-2 w-full md:w-auto overflow-x-auto">
          {['all', 'Pending Verification', 'Verified', 'Rejected'].map((status) => (
            <button
              key={status}
              onClick={() => setFilterStatus(status)}
              className={`px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap cursor-pointer transition ${
                filterStatus === status
                  ? 'bg-[#C5A46D] text-[#101C2C]'
                  : 'bg-[#0B1220] text-slate-400 hover:text-white border border-slate-800'
              }`}
            >
              {status === 'all' ? 'All Status' : status}
            </button>
          ))}
        </div>
      </div>

      {/* Documents Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {filteredDocs.map((doc) => {
          return (
            <div
              key={doc.id}
              className="bg-[#101C2C] rounded-2xl border border-slate-800 p-6 flex flex-col justify-between hover:border-[#C5A46D]/50 transition-all shadow-lg"
            >
              <div className="space-y-4">
                <div className="flex items-start justify-between">
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-xl bg-slate-800 border border-slate-700 flex items-center justify-center text-[#C5A46D]">
                      <FileText className="w-5 h-5" />
                    </div>
                    <div>
                      <h3 className="font-bold text-white text-sm">
                        {doc.title}
                      </h3>
                      <span className="text-[11px] text-[#C5A46D] font-mono">
                        {doc.category}
                      </span>
                    </div>
                  </div>

                  <span
                    className={`px-2.5 py-1 rounded-full text-[10px] font-bold flex items-center gap-1 ${
                      doc.verificationStatus === 'Verified'
                        ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30'
                        : doc.verificationStatus === 'Pending Verification'
                        ? 'bg-amber-500/20 text-amber-400 border border-amber-500/30'
                        : 'bg-rose-500/20 text-rose-400 border border-rose-500/30'
                    }`}
                  >
                    {doc.verificationStatus === 'Verified' && <CheckCircle2 className="w-3 h-3" />}
                    {doc.verificationStatus === 'Pending Verification' && <Clock className="w-3 h-3" />}
                    {doc.verificationStatus === 'Rejected' && <AlertTriangle className="w-3 h-3" />}
                    {doc.verificationStatus === 'Verified' ? 'Internally Verified' : doc.verificationStatus}
                  </span>
                </div>

                {/* Details */}
                <div className="space-y-2 text-xs pt-2 border-t border-slate-800/80">
                  <div className="flex items-center justify-between bg-[#0B1220] p-2 rounded-lg">
                    <span className="text-slate-400">File Reference:</span>
                    <span className="font-mono text-white font-bold">{doc.fileNumber}</span>
                  </div>

                  {doc.plotNumber && (
                    <div className="flex items-center justify-between text-slate-300 px-1">
                      <span className="text-slate-400">Linked Plot:</span>
                      <span className="font-bold text-[#C5A46D]">
                        Plot {doc.plotNumber} ({doc.sector})
                      </span>
                    </div>
                  )}

                  {doc.verifiedBy && (
                    <div className="flex items-center justify-between text-slate-300 px-1">
                      <span className="text-slate-400">Verified By:</span>
                      <span className="text-emerald-400">{doc.verifiedBy}</span>
                    </div>
                  )}

                  {doc.rejectionReason && (
                    <div className="p-2 rounded bg-rose-950/30 border border-rose-500/20 text-[11px] text-rose-300">
                      <strong>Rejection Note:</strong> {doc.rejectionReason}
                    </div>
                  )}

                  {doc.notes && !doc.rejectionReason && (
                    <div className="p-2 rounded bg-slate-900/60 border border-slate-800 text-[11px] text-slate-300">
                      {doc.notes}
                    </div>
                  )}
                </div>
              </div>

              {/* Actions */}
              <div className="pt-4 mt-4 border-t border-slate-800 flex items-center justify-between gap-2">
                <button
                  onClick={() => setSelectedDocPreview(doc)}
                  className="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold flex items-center gap-1.5 cursor-pointer"
                >
                  <Eye className="w-3.5 h-3.5 text-[#C5A46D]" />
                  <span>View Doc</span>
                </button>

                <div className="flex items-center gap-1.5">
                  {canVerify && doc.verificationStatus !== 'Verified' && (
                    <button
                      onClick={() => handleOpenVerify(doc.id)}
                      className="px-2.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition cursor-pointer flex items-center gap-1 shadow"
                    >
                      <CheckCircle2 className="w-3.5 h-3.5" />
                      <span>Verify</span>
                    </button>
                  )}

                  {canVerify && doc.verificationStatus !== 'Rejected' && (
                    <button
                      onClick={() => handleOpenReject(doc.id)}
                      className="px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-rose-900/50 text-slate-300 hover:text-rose-300 text-xs font-semibold transition cursor-pointer"
                    >
                      Reject
                    </button>
                  )}

                  {!canVerify && (
                    <span className="text-[10px] text-slate-500 italic">
                      Awaiting verification
                    </span>
                  )}
                </div>
              </div>
            </div>
          );
        })}
      </div>

      {/* Reject Reason Modal */}
      {rejectModalDocId && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fadeIn"
          onMouseDown={(e) => {
            if (e.target === e.currentTarget) setRejectModalDocId(null);
          }}
        >
          <div className="relative w-full max-w-md bg-[#101C2C] border border-rose-500/40 rounded-2xl p-6 shadow-2xl">
            <h3 className="font-serif-luxury text-lg font-bold text-white mb-2">
              Reject Document Verification
            </h3>
            <p className="text-xs text-slate-400 mb-4">
              Enter reason for rejection to notify the data entry operator and agent.
            </p>

            <textarea
              rows={3}
              value={rejectReason}
              onChange={(e) => setRejectReason(e.target.value)}
              className="w-full bg-[#0B1220] text-white p-2.5 rounded-lg border border-slate-700 focus:border-rose-500 focus:outline-none text-xs"
              placeholder="e.g. Signature mismatch with MDA allotment letter..."
            />

            <div className="flex items-center justify-end gap-3 pt-4">
              <button
                onClick={() => setRejectModalDocId(null)}
                className="px-4 py-2 rounded-lg bg-slate-800 text-slate-300 text-xs font-semibold cursor-pointer"
              >
                Cancel
              </button>
              <button
                onClick={handleConfirmReject}
                className="px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold cursor-pointer"
              >
                Confirm Rejection
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Verify Note Modal — mandatory reviewer note, no auto-generated text */}
      {verifyModalDocId && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fadeIn"
          onMouseDown={(e) => {
            if (e.target === e.currentTarget) setVerifyModalDocId(null);
          }}
        >
          <div className="relative w-full max-w-md bg-[#101C2C] border border-emerald-500/40 rounded-2xl p-6 shadow-2xl">
            <h3 className="font-serif-luxury text-lg font-bold text-white mb-2">
              Verify Document
            </h3>
            <p className="text-xs text-slate-400 mb-4">
              Add a short review note — what was checked against the MDA record. This note is stored with the audit trail and shown to the uploading team.
            </p>

            <textarea
              rows={3}
              value={verifyNote}
              onChange={(e) => setVerifyNote(e.target.value)}
              className="w-full bg-[#0B1220] text-white p-2.5 rounded-lg border border-slate-700 focus:border-emerald-500 focus:outline-none text-xs"
              placeholder="e.g. Allotment order matches MDA ledger; signature and thumb impression confirmed on-site..."
            />

            <div className="flex items-center justify-end gap-3 pt-4">
              <button
                onClick={() => setVerifyModalDocId(null)}
                className="px-4 py-2 rounded-lg bg-slate-800 text-slate-300 text-xs font-semibold cursor-pointer"
              >
                Cancel
              </button>
              <button
                onClick={handleConfirmVerify}
                className="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold cursor-pointer"
              >
                Confirm Verification
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Document Preview Modal */}
      {selectedDocPreview && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fadeIn"
          onMouseDown={(e) => {
            if (e.target === e.currentTarget) setSelectedDocPreview(null);
          }}
        >
          <div className="relative w-full max-w-2xl bg-[#101C2C] border border-[#C5A46D]/40 rounded-2xl p-6 sm:p-8 shadow-2xl max-h-[90vh] overflow-y-auto">
            <button
              onClick={() => setSelectedDocPreview(null)}
              className="absolute top-4 right-4 text-slate-400 hover:text-white p-1 rounded-lg cursor-pointer"
            >
              <X className="w-5 h-5" />
            </button>

            <h3 className="font-serif-luxury text-xl font-bold text-white mb-1">
              {selectedDocPreview.title}
            </h3>
            <span className="text-xs font-mono text-[#C5A46D]">
              {selectedDocPreview.category} • File: {selectedDocPreview.fileNumber}
            </span>

            <div className="my-4 rounded-xl overflow-hidden border border-slate-800 bg-[#0B1220]">
              <img
                src={selectedDocPreview.documentUrl}
                alt={selectedDocPreview.title}
                className="w-full h-80 object-cover"
              />
            </div>

            <div className="p-3 rounded-xl bg-[#0B1220] border border-slate-800 text-xs space-y-1">
              <div className="flex justify-between">
                <span className="text-slate-400">Verification Status:</span>
                <span className="font-bold text-white">{selectedDocPreview.verificationStatus}</span>
              </div>
              {selectedDocPreview.verifiedBy && (
                <div className="flex justify-between">
                  <span className="text-slate-400">Verified By:</span>
                  <span className="text-emerald-400">{selectedDocPreview.verifiedBy}</span>
                </div>
              )}
            </div>

            <div className="flex justify-end gap-3 mt-4">
              <button
                onClick={() => setSelectedDocPreview(null)}
                className="px-4 py-2 rounded-lg bg-slate-800 text-white text-xs font-semibold cursor-pointer"
              >
                Close Preview
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Upload Document Modal */}
      {isUploadModalOpen && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fadeIn"
          onMouseDown={(e) => {
            if (e.target === e.currentTarget) setIsUploadModalOpen(false);
          }}
        >
          <div className="relative w-full max-w-lg bg-[#101C2C] border border-[#C5A46D]/40 rounded-2xl p-6 sm:p-8 shadow-2xl max-h-[90vh] overflow-y-auto">
            <h3 className="font-serif-luxury text-xl font-bold text-white mb-1">
              Upload Official Property Document
            </h3>
            <p className="text-xs text-slate-400 mb-6">
              File documents will be placed in queue for legal verification.
            </p>

            <form onSubmit={handleUploadSubmit} className="space-y-4 text-xs">
              <div>
                <label className="block text-slate-300 font-semibold mb-1">Document Title *</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. MDA Allotment Order - Sector 63 R-66"
                  value={formData.title}
                  onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                  className="w-full bg-[#0B1220] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-slate-300 font-semibold mb-1">Document Category</label>
                  <select
                    value={formData.category}
                    onChange={(e) => setFormData({ ...formData, category: e.target.value as DocumentCategory })}
                    className="w-full bg-[#0B1220] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                  >
                    <option value="Allotment Order">Allotment Order</option>
                    <option value="Possession Letter">Possession Letter</option>
                    <option value="Site Plan">Site Plan</option>
                    <option value="Transfer Order">Transfer Order</option>
                    <option value="NDC Certificate">NDC Certificate</option>
                    <option value="CNIC Copy">CNIC Copy</option>
                    <option value="Sale Agreement">Sale Agreement</option>
                    <option value="Other">Other Document</option>
                  </select>
                </div>
                <div>
                  <label className="block text-slate-300 font-semibold mb-1">File / Chalan Number</label>
                  <input
                    type="text"
                    required
                    value={formData.fileNumber}
                    onChange={(e) => setFormData({ ...formData, fileNumber: e.target.value })}
                    className="w-full bg-[#0B1220] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#C5A46D] focus:outline-none font-mono"
                  />
                </div>
              </div>

              <div>
                <label className="block text-slate-300 font-semibold mb-1">Link to Property</label>
                <select
                  value={formData.propertyId}
                  onChange={(e) => setFormData({ ...formData, propertyId: e.target.value })}
                  className="w-full bg-[#0B1220] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                >
                  {properties.map((p) => (
                    <option key={p.id} value={p.id}>
                      {p.plotNumber ? `Plot ${p.plotNumber}` : p.title} ({p.sector})
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-slate-300 font-semibold mb-1">Document Scan Image / PDF URL</label>
                <input
                  type="text"
                  required
                  value={formData.documentUrl}
                  onChange={(e) => setFormData({ ...formData, documentUrl: e.target.value })}
                  className="w-full bg-[#0B1220] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#C5A46D] focus:outline-none font-mono text-[11px]"
                />
              </div>

              <div>
                <label className="block text-slate-300 font-semibold mb-1">Scrutiny Notes</label>
                <textarea
                  rows={2}
                  placeholder="Official stamps, MDA verification slip reference..."
                  value={formData.notes}
                  onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                  className="w-full bg-[#0B1220] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                />
              </div>

              <div className="flex items-center justify-end gap-3 pt-3">
                <button
                  type="button"
                  onClick={() => setIsUploadModalOpen(false)}
                  className="px-4 py-2 rounded-lg bg-slate-800 text-slate-300 font-semibold cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 rounded-lg bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C] font-bold cursor-pointer shadow"
                >
                  Upload & Queue
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
