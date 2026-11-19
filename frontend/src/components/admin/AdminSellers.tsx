import React, { useState } from 'react';
import { useApp } from '../../context/AppContext';
import {
  Users,
  Plus,
  Edit2,
  Trash2,
  Phone,
  CreditCard,
  MapPin,
  Building,
  FileText,
  Search,
  Eye,
  EyeOff,
  CheckCircle2,
  AlertCircle
} from 'lucide-react';
import { Seller } from '../../types';
import { maskCNIC, maskAccountNumber, formatPKRCurrency, hasPermission } from '../../utils/logic';

export const AdminSellers: React.FC = () => {
  const { sellers, addSeller, updateSeller, deleteSeller, properties, showToast, activeRole, currentUser, confirmAction } = useApp();

  const [searchQuery, setSearchQuery] = useState('');
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingSellerId, setEditingSellerId] = useState<string | null>(null);
  const [unmaskedCnicIds, setUnmaskedCnicIds] = useState<Record<string, boolean>>({});

  // P0-5: full CNIC / account numbers are only for staff holding view_sensitive_cnic.
  const canViewSensitive = hasPermission(currentUser.role, 'view_sensitive_cnic');

  const [formData, setFormData] = useState({
    fullName: '',
    cnic: '',
    phone: '',
    address: '',
    notes: '',
    bankAccountTitle: '',
    bankName: '',
    accountNumber: ''
  });

  const toggleCnicMask = (id: string) => {
    setUnmaskedCnicIds((prev) => ({ ...prev, [id]: !prev[id] }));
  };

  const filteredSellers = (sellers || []).filter((s) => !s.deletedAt && (
    s.fullName.toLowerCase().includes(searchQuery.toLowerCase()) ||
    (s.cnic || s.cnicMasked || '').includes(searchQuery) ||
    s.phone.includes(searchQuery)
  ));

  const handleOpenAdd = () => {
    setEditingSellerId(null);
    setFormData({
      fullName: '',
      cnic: '',
      phone: '',
      address: '',
      notes: '',
      bankAccountTitle: '',
      bankName: '',
      accountNumber: ''
    });
    setIsModalOpen(true);
  };

  const handleOpenEdit = (seller: Seller) => {
    setEditingSellerId(seller.id);
    // Privacy prefill: without view_sensitive_cnic the form shows masked values only.
    setFormData({
      fullName: seller.fullName,
      cnic: canViewSensitive ? (seller.cnic || seller.cnicMasked || '') : (seller.cnicMasked || ''),
      phone: seller.phone,
      address: seller.address || '',
      notes: seller.notes || '',
      bankAccountTitle: seller.bankAccountTitle || '',
      bankName: seller.bankName || '',
      accountNumber: canViewSensitive ? (seller.accountNumber || '') : (seller.accountNumber ? maskAccountNumber(seller.accountNumber) : '')
    });
    setIsModalOpen(true);
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.fullName.trim() || !formData.cnic.trim()) {
      showToast('error', 'Please fill in seller full name and CNIC.');
      return;
    }

    if (editingSellerId) {
      const original = sellers.find((s) => s.id === editingSellerId);
      updateSeller(editingSellerId, {
        fullName: formData.fullName,
        // P0-5: staff without view_sensitive_cnic see masked values in the form and
        // can never overwrite the originals with masked garbage.
        cnic: canViewSensitive ? formData.cnic : (original?.cnic ?? ''),
        cnicMasked: canViewSensitive ? maskCNIC(formData.cnic) : (original?.cnicMasked || maskCNIC(original?.cnic ?? '')),
        phone: formData.phone,
        whatsApp: formData.phone,
        address: formData.address,
        notes: formData.notes,
        bankAccountTitle: formData.bankAccountTitle,
        bankName: formData.bankName,
        accountNumber: canViewSensitive ? formData.accountNumber : (original?.accountNumber || '')
      });
      showToast('success', 'Seller details updated successfully.');
    } else {
      addSeller({
        fullName: formData.fullName,
        cnic: formData.cnic,
        cnicMasked: maskCNIC(formData.cnic),
        phone: formData.phone,
        whatsApp: formData.phone,
        address: formData.address,
        notes: formData.notes,
        bankAccountTitle: formData.bankAccountTitle,
        bankName: formData.bankName,
        accountNumber: formData.accountNumber,
        ownedProperties: [],
        ownedPropertyIds: [],
        fileNumbers: [],
        documentCount: 0,
        totalPayoutReceivedPKR: 0
      });
      showToast('success', 'New seller record registered.');
    }
    setIsModalOpen(false);
  };

  const handleDelete = async (id: string, name: string) => {
    const ok = await confirmAction(`Are you sure you want to delete seller record "${name}"?`, {
      title: 'Delete seller record',
      confirmLabel: 'Delete'
    });
    if (ok) {
      deleteSeller(id);
      showToast('info', 'Seller record deleted.');
    }
  };

  return (
    <div className="space-y-6 animate-fadeIn">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-6 rounded-2xl bg-gradient-to-r from-[#0B1220] to-[#1A2638] border border-[#E7C878]/30 shadow-xl">
        <div>
          <div className="flex items-center gap-2">
            <h2 className="font-serif-luxury text-2xl font-bold text-white">
              Seller & Property Owner Directory
            </h2>
            <span className="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-[#E7C878]/20 text-[#E7C878] border border-[#E7C878]/40">
              ERP Registry
            </span>
          </div>
          <p className="text-xs text-slate-300 mt-1">
            Registered landowners, allottees, verified CNIC records, and property portfolios in Taiser Town Scheme 45.
          </p>
        </div>

        <button
          onClick={handleOpenAdd}
          className="px-4 py-2.5 rounded-xl bg-[#E7C878] hover:bg-[#F3C464] text-[#0B1220] font-bold text-xs flex items-center justify-center gap-2 cursor-pointer shadow-lg shadow-[#E7C878]/20"
        >
          <Plus className="w-4 h-4" />
          <span>Add New Seller</span>
        </button>
      </div>

      {/* Filter and Search */}
      <div className="flex flex-col sm:flex-row items-center justify-between gap-4 bg-[#0B1220] p-4 rounded-xl border border-slate-800">
        <div className="relative w-full sm:w-80">
          <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            placeholder="Search by name, CNIC or phone..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="w-full bg-[#07141E] text-white text-xs pl-9 pr-4 py-2.5 rounded-lg border border-slate-700 focus:border-[#E7C878] focus:outline-none"
          />
        </div>
        <div className="text-xs text-slate-400 flex items-center gap-2">
          <span>Showing <strong>{filteredSellers.length}</strong> sellers</span>
          <span className="text-slate-600">|</span>
          <span className="text-amber-400 font-medium">Confidential Financial Data Protected</span>
        </div>
      </div>

      {/* Sellers Grid / Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {filteredSellers.map((seller) => {
          const isUnmasked = !!unmaskedCnicIds[seller.id];
          const ownedList = seller.ownedProperties || seller.ownedPropertyIds || [];
          const sellerProperties = properties.filter(
            (p) => ownedList.includes(p.id) || p.sellerId === seller.id
          );

          return (
            <div
              key={seller.id}
              className="bg-[#0B1220] rounded-2xl border border-slate-800 p-6 flex flex-col justify-between hover:border-[#E7C878]/50 transition-all shadow-lg"
            >
              <div className="space-y-4">
                <div className="flex items-start justify-between">
                  <div className="flex items-center gap-3">
                    <div className="w-11 h-11 rounded-xl bg-gradient-to-br from-[#E7C878]/20 to-[#0B1220] border border-[#E7C878]/40 flex items-center justify-center font-serif-luxury font-bold text-[#F6E3B4] text-lg shadow">
                      {seller.fullName.charAt(0)}
                    </div>
                    <div>
                      <h3 className="font-bold text-white text-sm">
                        {seller.fullName}
                      </h3>
                      <span className="text-[11px] text-[#E7C878] font-mono">
                        ID: {seller.id}
                      </span>
                    </div>
                  </div>
                  <div className="flex items-center gap-1.5">
                    <button
                      onClick={() => handleOpenEdit(seller)}
                      className="p-1.5 rounded-lg bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 transition cursor-pointer"
                      title="Edit Seller"
                    >
                      <Edit2 className="w-3.5 h-3.5" />
                    </button>
                    <button
                      onClick={() => handleDelete(seller.id, seller.fullName)}
                      className="p-1.5 rounded-lg bg-slate-800 text-slate-400 hover:text-white hover:bg-slate-700 transition cursor-pointer"
                      title="Delete Seller"
                    >
                      <Trash2 className="w-3.5 h-3.5" />
                    </button>
                  </div>
                </div>

                {/* Seller Data Fields */}
                <div className="space-y-2 text-xs pt-2 border-t border-slate-800/80">
                  <div className="flex items-center justify-between bg-[#07141E] p-2 rounded-lg">
                    <span className="text-slate-400 flex items-center gap-1.5">
                      <CreditCard className="w-3.5 h-3.5 text-slate-500" />
                      CNIC:
                    </span>
                    <div className="flex items-center gap-2">
                      <span className="font-mono text-white font-medium">
                        {hasPermission(currentUser.role, 'view_sensitive_cnic')
                          ? (isUnmasked ? (seller.cnic ?? seller.cnicMasked ?? 'N/A') : (seller.cnicMasked || maskCNIC(seller.cnic ?? '')))
                          : (seller.cnicMasked || maskCNIC(seller.cnic ?? ''))}
                      </span>
                      {hasPermission(currentUser.role, 'view_sensitive_cnic') && (
                        <button
                          type="button"
                          onClick={() => toggleCnicMask(seller.id)}
                          className="text-slate-400 hover:text-[#E7C878] cursor-pointer"
                          title={isUnmasked ? 'Mask CNIC' : 'Reveal CNIC'}
                        >
                          {isUnmasked ? <EyeOff className="w-3.5 h-3.5" /> : <Eye className="w-3.5 h-3.5" />}
                        </button>
                      )}
                    </div>
                  </div>

                  <div className="flex items-center justify-between text-slate-300 px-1">
                    <span className="text-slate-400 flex items-center gap-1.5">
                      <Phone className="w-3.5 h-3.5 text-slate-500" />
                      Phone:
                    </span>
                    <span className="font-mono">{seller.phone}</span>
                  </div>

                  {seller.address && (
                    <div className="flex items-start justify-between text-slate-300 px-1">
                      <span className="text-slate-400 flex items-center gap-1.5 shrink-0">
                        <MapPin className="w-3.5 h-3.5 text-slate-500" />
                        Address:
                      </span>
                      <span className="text-right text-[11px] truncate max-w-[160px] text-slate-300">
                        {seller.address}
                      </span>
                    </div>
                  )}

                  {seller.bankName && (
                    <div className="p-2.5 rounded-lg bg-[#07141E] border border-[#E7C878]/30 text-[11px] text-slate-300 space-y-0.5">
                      <div className="font-semibold text-[#E7C878] flex items-center gap-1">
                        <CheckCircle2 className="w-3 h-3 text-[#E7C878]" />
                        Bank Settlement Record
                      </div>
                      <div>{seller.bankName} - {seller.bankAccountTitle}</div>
                      <div className="font-mono text-[10px] text-[#F6E3B4]">
                        {canViewSensitive ? seller.accountNumber : maskAccountNumber(seller.accountNumber)}
                      </div>
                    </div>
                  )}
                </div>

                {/* Owned properties list */}
                <div className="pt-2">
                  <div className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-2 flex items-center justify-between">
                    <span>Owned Properties ({sellerProperties.length})</span>
                    <span className="text-[10px] text-slate-500">{seller.documentCount} docs</span>
                  </div>
                  {sellerProperties.length > 0 ? (
                    <div className="space-y-1.5 max-h-28 overflow-y-auto pr-1">
                      {sellerProperties.map((p) => (
                        <div
                          key={p.id}
                          className="p-2 rounded-lg bg-[#07141E] border border-slate-800 text-[11px] flex items-center justify-between"
                        >
                          <div>
                            <span className="font-bold text-white block">
                              {p.plotNumber ? `Plot ${p.plotNumber}` : p.title}
                            </span>
                            <span className="text-[10px] text-[#E7C878]">{p.sector}</span>
                          </div>
                          <span className="font-bold text-[#E7C878] text-xs">
                            {formatPKRCurrency(p.pricePKR)}
                          </span>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <p className="text-[11px] text-slate-500 italic">No assigned properties yet.</p>
                  )}
                </div>
              </div>

              {/* Card Footer */}
              <div className="pt-4 mt-4 border-t border-slate-800 flex items-center justify-between text-xs">
                <span className="text-slate-400 text-[11px]">Payout Received:</span>
                <span className="font-bold text-white font-mono">
                  {(seller.totalPayoutReceivedPKR || 0) > 0
                    ? formatPKRCurrency(seller.totalPayoutReceivedPKR || 0)
                    : 'PKR 0'}
                </span>
              </div>
            </div>
          );
        })}
      </div>

      {/* Add / Edit Seller Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fadeIn">
          <div className="relative w-full max-w-lg bg-[#0B1220] border border-[#E7C878]/40 rounded-2xl p-6 sm:p-8 shadow-2xl max-h-[90vh] overflow-y-auto">
            <h3 className="font-serif-luxury text-xl font-bold text-white mb-2">
              {editingSellerId ? 'Edit Seller Details' : 'Register New Property Seller'}
            </h3>
            <p className="text-xs text-slate-400 mb-6">
              Enter official details matching MDA Allotment or NADRA CNIC documentation.
            </p>

            <form onSubmit={handleSubmit} className="space-y-4 text-xs">
              <div>
                <label className="block text-slate-300 font-semibold mb-1">
                  Full Legal Name *
                </label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Haji Abdul Rasheed"
                  value={formData.fullName}
                  onChange={(e) => setFormData({ ...formData, fullName: e.target.value })}
                  className="w-full bg-[#07141E] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#E7C878] focus:outline-none"
                />
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-slate-300 font-semibold mb-1">
                    NADRA CNIC Number *
                  </label>
                  <input
                    type="text"
                    required
                    placeholder="42101-1234567-3"
                    value={formData.cnic}
                    onChange={(e) => setFormData({ ...formData, cnic: e.target.value })}
                    className="w-full bg-[#07141E] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#E7C878] focus:outline-none font-mono"
                  />
                </div>
                <div>
                  <label className="block text-slate-300 font-semibold mb-1">
                    Phone / WhatsApp *
                  </label>
                  <input
                    type="text"
                    required
                    placeholder="0300-1234567"
                    value={formData.phone}
                    onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                    className="w-full bg-[#07141E] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#E7C878] focus:outline-none font-mono"
                  />
                </div>
              </div>

              <div>
                <label className="block text-slate-300 font-semibold mb-1">
                  Permanent / Residential Address
                </label>
                <input
                  type="text"
                  placeholder="Street / Area, Karachi"
                  value={formData.address}
                  onChange={(e) => setFormData({ ...formData, address: e.target.value })}
                  className="w-full bg-[#07141E] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#E7C878] focus:outline-none"
                />
              </div>

              {/* Bank Settlement Section */}
              <div className="p-3.5 rounded-xl bg-[#07141E] border border-slate-800 space-y-3">
                <span className="font-bold text-slate-200 block text-[11px] uppercase tracking-wider text-[#E7C878]">
                  Bank Account for Sale Proceeds (Optional)
                </span>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                  <div>
                    <label className="block text-slate-400 text-[10px] mb-1">Bank Name</label>
                    <input
                      type="text"
                      placeholder="Meezan Bank"
                      value={formData.bankName}
                      onChange={(e) => setFormData({ ...formData, bankName: e.target.value })}
                      className="w-full bg-slate-900 text-white p-2 rounded border border-slate-700 focus:border-[#E7C878] focus:outline-none text-xs"
                    />
                  </div>
                  <div>
                    <label className="block text-slate-400 text-[10px] mb-1">Account Title</label>
                    <input
                      type="text"
                      placeholder="Haji Abdul Rasheed"
                      value={formData.bankAccountTitle}
                      onChange={(e) => setFormData({ ...formData, bankAccountTitle: e.target.value })}
                      className="w-full bg-slate-900 text-white p-2 rounded border border-slate-700 focus:border-[#E7C878] focus:outline-none text-xs"
                    />
                  </div>
                  <div>
                    <label className="block text-slate-400 text-[10px] mb-1">IBAN / Account #</label>
                    <input
                      type="text"
                      placeholder="PK00MEZN00..."
                      value={formData.accountNumber}
                      onChange={(e) => setFormData({ ...formData, accountNumber: e.target.value })}
                      className="w-full bg-slate-900 text-white p-2 rounded border border-slate-700 focus:border-[#E7C878] focus:outline-none text-xs font-mono"
                    />
                  </div>
                </div>
              </div>

              <div>
                <label className="block text-slate-300 font-semibold mb-1">
                  Private Office Notes
                </label>
                <textarea
                  rows={2}
                  placeholder="Notes regarding seller, power of attorney, original file status..."
                  value={formData.notes}
                  onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                  className="w-full bg-[#07141E] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#E7C878] focus:outline-none"
                />
              </div>

              <div className="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                <button
                  type="button"
                  onClick={() => setIsModalOpen(false)}
                  className="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 rounded-lg bg-[#E7C878] hover:bg-[#F3C464] text-[#0B1220] font-bold cursor-pointer shadow"
                >
                  {editingSellerId ? 'Save Changes' : 'Register Seller'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
