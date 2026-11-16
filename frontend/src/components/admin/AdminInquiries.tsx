import React, { useState } from 'react';
import { useApp } from '../../context/AppContext';
import {
  Inbox,
  Search,
  MessageCircle,
  Phone,
  Mail,
  Calendar,
  CheckCircle2,
  Clock,
  Trash2,
  FileEdit,
  Save,
  Filter,
  AlertCircle
} from 'lucide-react';
import { Inquiry, InquiryStatus } from '../../types';
import { generateWhatsAppLink } from '../../utils/logic';
import { EmptyState } from '../common/EmptyState';

export const AdminInquiries: React.FC = () => {
  const {
    inquiries,
    updateInquiryStatus,
    updateInquiryNotes,
    deleteInquiry,
    confirmAction,
    showToast
  } = useApp();

  const [activeFilter, setActiveFilter] = useState<string>('All');
  const [searchQuery, setSearchQuery] = useState('');
  const [editingNotesId, setEditingNotesId] = useState<string | null>(null);
  const [tempNotes, setTempNotes] = useState('');

  const statuses: Array<InquiryStatus | 'All'> = [
    'All',
    'Pending',
    'Contacted',
    'In Progress',
    'Resolved'
  ];

  const filteredInquiries = inquiries.filter((inq) => {
    if (activeFilter !== 'All' && inq.status !== activeFilter) return false;
    if (searchQuery.trim() !== '') {
      const q = searchQuery.toLowerCase();
      return (
        inq.fullName.toLowerCase().includes(q) ||
        inq.phone.includes(q) ||
        inq.subject.toLowerCase().includes(q) ||
        (inq.preferredPropertyOrProject && inq.preferredPropertyOrProject.toLowerCase().includes(q))
      );
    }
    return true;
  });

  const handleStartEditNotes = (inq: Inquiry) => {
    setEditingNotesId(inq.id);
    setTempNotes(inq.adminNotes || '');
  };

  const handleSaveNotes = (id: string) => {
    updateInquiryNotes(id, tempNotes);
    setEditingNotesId(null);
  };

  const handleDelete = async (id: string, name: string) => {
    const ok = await confirmAction(`Delete inquiry from ${name}?`, {
      title: 'Delete inquiry',
      confirmLabel: 'Delete'
    });
    if (ok) {
      deleteInquiry(id);
    }
  };

  return (
    <div className="space-y-6 animate-fadeIn">
      {/* Header bar */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-6 rounded-2xl bg-[#101C2C] border border-slate-800 shadow-xl">
        <div>
          <h2 className="font-serif-luxury text-2xl font-bold text-white flex items-center gap-2">
            <span>Client Inquiry CRM & Lead Stream</span>
            <span className="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-[#C5A46D]/15 text-[#F9E7BA] border border-[#C5A46D]/30">
              {inquiries.length} Total Leads
            </span>
          </h2>
          <p className="text-xs text-slate-400">
            Real-time inquiries received via property search, project detail pages, and direct consultation forms.
          </p>
        </div>

        <div className="text-xs text-slate-400 bg-[#0B1220] px-4 py-2 rounded-xl border border-slate-800">
          <span>Pending Follow-up: </span>
          <strong className="text-[#C5A46D] font-bold">
            {inquiries.filter((i) => i.status === 'Pending').length}
          </strong>
        </div>
      </div>

      {/* Filter and Search Bar */}
      <div className="flex flex-col md:flex-row items-center justify-between gap-4 p-4 rounded-2xl bg-[#101C2C] border border-slate-800">
        <div className="flex flex-wrap items-center gap-1.5 w-full md:w-auto">
          {statuses.map((status) => {
            const isActive = activeFilter === status;
            const count = status === 'All' ? inquiries.length : inquiries.filter((i) => i.status === status).length;

            return (
              <button
                key={status}
                onClick={() => setActiveFilter(status)}
                className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all cursor-pointer ${
                  isActive
                    ? 'bg-[#C5A46D] text-[#101C2C] font-bold shadow'
                    : 'text-slate-300 hover:text-white hover:bg-slate-800'
                }`}
              >
                <span>{status}</span>
                <span className="text-[10px] ml-1 opacity-75">({count})</span>
              </button>
            );
          })}
        </div>

        <div className="relative w-full md:w-72">
          <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            placeholder="Search leads by name or phone..."
            className="w-full bg-[#0B1220] text-white text-xs rounded-lg pl-9 pr-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
          />
        </div>
      </div>

      {/* Inquiries List */}
      <div className="space-y-4">
        {filteredInquiries.length === 0 ? (
          <EmptyState
            icon={<Inbox className="w-9 h-9" />}
            chip="Inbox Zero"
            title="No Open Inquiries"
            description="When a new lead arrives from the public site (search, project or property forms), it will appear here with instant WhatsApp reply tools."
            actionLabel="Reset Filters"
            onAction={() => {
              setSearchQuery('');
              setActiveFilter('All');
            }}
          />
        ) : (
          filteredInquiries.map((inq) => {
            const whatsAppReply = generateWhatsAppLink(
              inq.phone,
              `Salam ${inq.fullName}! This is TaisarTownHub Real Estate Advisory. We received your inquiry regarding "${inq.preferredPropertyOrProject || inq.subject}". How can we best assist you with file verification and site inspection?`
            );

            return (
              <div
                key={inq.id}
                className="bg-[#101C2C] rounded-2xl border border-slate-800 hover:border-[#C5A46D]/40 p-6 transition-all shadow-xl space-y-4"
              >
                {/* Top Row: Client Info, Date, Status */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-800">
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-full bg-slate-800 text-[#C5A46D] font-bold flex items-center justify-center border border-slate-700">
                      {inq.fullName.split(' ').map(n => n[0]).join('')}
                    </div>
                    <div>
                      <h4 className="font-bold text-white text-sm">
                        {inq.fullName}
                      </h4>
                      <div className="flex items-center gap-3 text-xs text-slate-400">
                        <span className="text-[#C5A46D] font-mono">{inq.phone}</span>
                        {inq.email && <span>• {inq.email}</span>}
                        <span className="hidden sm:inline">• {inq.submittedAt}</span>
                      </div>
                    </div>
                  </div>

                  <div className="flex items-center gap-3">
                    {/* Status Dropdown */}
                    <select
                      value={inq.status}
                      onChange={(e) => updateInquiryStatus(inq.id, e.target.value as InquiryStatus)}
                      className={`text-xs font-bold rounded-lg px-2.5 py-1 border cursor-pointer ${
                        inq.status === 'Pending' ? 'bg-[#0B1220] text-[#C5A46D] border-[#C5A46D]/30' :
                        inq.status === 'Contacted' ? 'bg-[#101C2C] text-[#E0C79A] border-[#C5A46D]/40' :
                        inq.status === 'In Progress' ? 'bg-[#101C2C] text-[#F9E7BA] border-[#C5A46D]/60' :
                        'bg-[#0B1220] text-white border-[#C5A46D]/50'
                      }`}
                    >
                      <option value="Pending">Pending</option>
                      <option value="Contacted">Contacted</option>
                      <option value="In Progress">In Progress</option>
                      <option value="Resolved">Resolved</option>
                    </select>

                    <button
                      onClick={() => handleDelete(inq.id, inq.fullName)}
                      className="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition-colors"
                      title="Delete inquiry"
                    >
                      <Trash2 className="w-4 h-4" />
                    </button>
                  </div>
                </div>

                {/* Message Body */}
                <div className="grid grid-cols-1 md:grid-cols-12 gap-6">
                  <div className="md:col-span-8 space-y-2">
                    <div className="flex items-center gap-2 text-xs">
                      <span className="text-slate-400">Property / Sector:</span>
                      <span className="font-bold text-white bg-[#0B1220] px-2 py-0.5 rounded border border-slate-800">
                        {inq.preferredPropertyOrProject || inq.subject}
                      </span>
                      {inq.budgetRange && (
                        <span className="text-[#C5A46D] font-semibold text-xs ml-2">
                          Budget: {inq.budgetRange}
                        </span>
                      )}
                    </div>

                    <p className="text-xs text-slate-300 leading-relaxed bg-[#0B1220] p-3.5 rounded-xl border border-slate-800">
                      "{inq.message}"
                    </p>
                  </div>

                  {/* Admin Notes & Quick WhatsApp Actions (4 cols) */}
                  <div className="md:col-span-4 space-y-3">
                    <div className="flex items-center justify-between text-xs text-slate-400">
                      <span className="font-semibold text-white">Internal Agent Notes</span>
                      {editingNotesId !== inq.id && (
                        <button
                          onClick={() => handleStartEditNotes(inq)}
                          className="text-[#C5A46D] hover:underline flex items-center gap-1 cursor-pointer text-[11px]"
                        >
                          <FileEdit className="w-3 h-3" />
                          <span>Edit</span>
                        </button>
                      )}
                    </div>

                    {editingNotesId === inq.id ? (
                      <div className="space-y-2">
                        <textarea
                          rows={2}
                          value={tempNotes}
                          onChange={(e) => setTempNotes(e.target.value)}
                          className="w-full bg-[#0B1220] text-white text-xs rounded-lg p-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                        />
                        <button
                          onClick={() => handleSaveNotes(inq.id)}
                          className="px-3 py-1 rounded bg-[#C5A46D] text-[#101C2C] font-bold text-xs flex items-center gap-1"
                        >
                          <Save className="w-3 h-3" />
                          <span>Save Note</span>
                        </button>
                      </div>
                    ) : (
                      <div className="text-[11px] text-slate-400 italic bg-[#0B1220] p-2.5 rounded-lg border border-slate-800">
                        {inq.adminNotes || 'No notes added yet.'}
                      </div>
                    )}

                    {/* Quick WhatsApp Action Button */}
                    <a
                      href={whatsAppReply}
                      target="_blank" rel="noopener noreferrer"
                      className="w-full py-2 px-3 rounded-lg bg-[#C5A46D]/15 hover:bg-[#C5A46D] text-[#F9E7BA] hover:text-[#101C2C] transition-colors border border-[#C5A46D]/40 font-bold text-xs flex items-center justify-center gap-2"
                    >
                      <MessageCircle className="w-3.5 h-3.5" />
                      <span>WhatsApp Client</span>
                    </a>
                  </div>
                </div>
              </div>
            );
          })
        )}
      </div>
    </div>
  );
};



