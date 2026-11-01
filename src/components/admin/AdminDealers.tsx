import React, { useState } from 'react';
import { useApp } from '../../context/AppContext';
import {
  Briefcase,
  Plus,
  Edit2,
  Trash2,
  Phone,
  CreditCard,
  Building,
  CheckCircle2,
  Clock,
  DollarSign,
  Search,
  Wallet,
  ArrowUpRight,
  Receipt
} from 'lucide-react';
import { Dealer, CommissionType } from '../../types';
import { maskCNIC, formatPKRCurrency, hasPermission } from '../../utils/logic';
import { AgencyDetailsModal } from '../common/AgencyDetailsModal';

export const AdminDealers: React.FC = () => {
  const { dealers, properties, addDealer, updateDealer, deleteDealer, payDealerCommission, transactions, commissionEntries, showToast, currentUser } = useApp();

  const [searchQuery, setSearchQuery] = useState('');
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingDealerId, setEditingDealerId] = useState<string | null>(null);

  // Agency files modal
  const [selectedDealerForFiles, setSelectedDealerForFiles] = useState<Dealer | null>(null);

  // Payout settlement modal
  const [isPayoutModalOpen, setIsPayoutModalOpen] = useState(false);
  const [selectedDealerForPayout, setSelectedDealerForPayout] = useState<Dealer | null>(null);
  const [payoutAmount, setPayoutAmount] = useState<number>(20000);
  const [ledgerEarned, setLedgerEarned] = useState<number>(0);
  const [ledgerPaid, setLedgerPaid] = useState<number>(0);
  const [ledgerPayable, setLedgerPayable] = useState<number>(0);

  const [formData, setFormData] = useState({
    fullName: '',
    cnic: '',
    phone: '',
    agencyOrBranch: 'TaisarTownHub Direct Sales',
    commissionType: 'fixed' as CommissionType,
    commissionValue: 20000,
    status: 'Active' as 'Active' | 'Inactive'
  });

  const filteredDealers = dealers.filter((d) => !d.deletedAt && (
    d.fullName.toLowerCase().includes(searchQuery.toLowerCase()) ||
    d.phone.includes(searchQuery) ||
    (d.agencyOrBranch || d.agencyAffiliation || '').toLowerCase().includes(searchQuery.toLowerCase())
  ));

  const handleOpenAdd = () => {
    setEditingDealerId(null);
    setFormData({
      fullName: '',
      cnic: '',
      phone: '',
      agencyOrBranch: 'TaisarTownHub Direct Sales',
      commissionType: 'fixed',
      commissionValue: 20000,
      status: 'Active'
    });
    setIsModalOpen(true);
  };

  const handleOpenEdit = (dealer: Dealer) => {
    setEditingDealerId(dealer.id);
    setFormData({
      fullName: dealer.fullName,
      cnic: dealer.cnic || '',
      phone: dealer.phone,
      agencyOrBranch: dealer.agencyOrBranch || dealer.agencyAffiliation || 'TaisarTownHub Direct Sales',
      commissionType: dealer.commissionType,
      commissionValue: dealer.commissionValue ?? dealer.commissionRate ?? 20000,
      status: dealer.status
    });
    setIsModalOpen(true);
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.fullName.trim() || !formData.phone.trim()) {
      showToast('error', 'Please fill in dealer name and phone.');
      return;
    }

    if (editingDealerId) {
      updateDealer(editingDealerId, {
        fullName: formData.fullName,
        cnic: formData.cnic,
        cnicMasked: maskCNIC(formData.cnic),
        phone: formData.phone,
        whatsApp: formData.phone,
        email: `${formData.fullName.toLowerCase().replace(/\s+/g, '.')}@taisartownhub.pk`,
        agencyAffiliation: formData.agencyOrBranch,
        agencyOrBranch: formData.agencyOrBranch,
        commissionType: formData.commissionType,
        commissionRate: Number(formData.commissionValue),
        commissionValue: Number(formData.commissionValue),
        status: formData.status
      });
      showToast('success', 'Dealer terms updated.');
    } else {
      addDealer({
        fullName: formData.fullName,
        cnic: formData.cnic,
        cnicMasked: maskCNIC(formData.cnic),
        phone: formData.phone,
        whatsApp: formData.phone,
        email: `${formData.fullName.toLowerCase().replace(/\s+/g, '.')}@taisartownhub.pk`,
        agencyAffiliation: formData.agencyOrBranch,
        agencyOrBranch: formData.agencyOrBranch,
        commissionType: formData.commissionType,
        commissionRate: Number(formData.commissionValue),
        commissionValue: Number(formData.commissionValue),
        status: formData.status,
        role: 'Real Estate Consultant',
        totalCommissionEarnedPKR: 0,
        totalCommissionPaidPKR: 0,
        assignedPropertiesCount: 0,
        dealsClosed: 0,
        completedDealsCount: 0,
        avatar: 'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&w=200&q=80'
      });
      showToast('success', 'New dealer registered in agency ERP.');
    }
    setIsModalOpen(false);
  };

  const handleOpenPayout = (dealer: Dealer) => {
    // Ledger-derived aggregates (single source of truth — P1-3).
    const entries = commissionEntries.filter((c) => c.dealerId === dealer.id && !c.status.startsWith('Cancel'));
    const earned = entries.reduce((sum, c) => sum + c.amountPKR, 0);
    const paid = entries.reduce((sum, c) => sum + c.paidPKR, 0);
    const payable = entries
      .filter((c) => c.status === 'Accrued' || c.status === 'Partially Paid')
      .reduce((sum, c) => sum + Math.max(0, c.amountPKR - c.paidPKR), 0);
    setSelectedDealerForPayout(dealer);
    setPayoutAmount(payable > 0 ? payable : 0);
    // store ledger-derived values on the modal via dealer override (modal reads dealer.*)
    // We'll pass them through a ref-like state instead — set a temp field.
    setLedgerEarned(earned);
    setLedgerPaid(paid);
    setLedgerPayable(payable);
    setIsPayoutModalOpen(true);
  };

  const handleExecutePayout = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedDealerForPayout) return;
    if (payoutAmount <= 0) {
      showToast('error', 'Please enter a valid payout amount.');
      return;
    }

    payDealerCommission(selectedDealerForPayout.id, payoutAmount);
    showToast('success', `Recorded PKR ${payoutAmount.toLocaleString()} commission payment to ${selectedDealerForPayout.fullName}.`);
    setIsPayoutModalOpen(false);
  };

  const handleDelete = (id: string, name: string) => {
    if (confirm(`Remove dealer record "${name}"?`)) {
      deleteDealer(id);
      showToast('info', 'Dealer removed.');
    }
  };

  const totalAgencyCommissionEarned = dealers.reduce(
    (acc, d) => acc + (d.totalCommissionEarnedPKR ?? d.totalEarnedPKR ?? 0),
    0
  );
  const totalAgencyCommissionPaid = dealers.reduce(
    (acc, d) => acc + (d.totalCommissionPaidPKR ?? 0),
    0
  );
  const totalAgencyCommissionPending = totalAgencyCommissionEarned - totalAgencyCommissionPaid;

  return (
    <div className="space-y-6 animate-fadeIn">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-6 rounded-2xl bg-gradient-to-r from-[#101C2C] to-[#1A2638] border border-[#C5A46D]/30 shadow-xl">
        <div>
          <div className="flex items-center gap-2">
            <h2 className="font-serif-luxury text-2xl font-bold text-white">
              Dealer & Agent Commission Ledger
            </h2>
            <span className="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-[#C5A46D]/20 text-[#C5A46D] border border-[#C5A46D]/40">
              Agency Accounts
            </span>
          </div>
          <p className="text-xs text-slate-300 mt-1">
            Commission tracking for internal sales associates, external real estate brokers, and deal facilitators (e.g. Saleem Siddiqui).
          </p>
        </div>

        <button
          onClick={handleOpenAdd}
          className="px-4 py-2.5 rounded-xl bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C] font-bold text-xs flex items-center justify-center gap-2 cursor-pointer shadow-lg shadow-[#C5A46D]/20"
        >
          <Plus className="w-4 h-4" />
          <span>Add New Dealer</span>
        </button>
      </div>

      {/* 3 Metric Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-6">
        <div className="bg-[#101C2C] p-5 rounded-xl border border-slate-800 shadow">
          <div className="text-xs text-slate-400 font-semibold uppercase flex items-center justify-between">
            <span>Total Commission Accrued</span>
            <DollarSign className="w-4 h-4 text-[#C5A46D]" />
          </div>
          <div className="text-2xl font-bold font-serif-luxury text-white mt-2">
            {formatPKRCurrency(totalAgencyCommissionEarned)}
          </div>
          <div className="text-[11px] text-slate-400 mt-1">Across all closed transactions</div>
        </div>

        <div className="bg-[#101C2C] p-5 rounded-xl border border-slate-800 shadow">
          <div className="text-xs text-slate-400 font-semibold uppercase flex items-center justify-between">
            <span>Commission Settled / Paid</span>
            <CheckCircle2 className="w-4 h-4 text-emerald-400" />
          </div>
          <div className="text-2xl font-bold font-serif-luxury text-emerald-400 mt-2">
            {formatPKRCurrency(totalAgencyCommissionPaid)}
          </div>
          <div className="text-[11px] text-emerald-400/80 mt-1">Disbursed to agents via cash/transfer</div>
        </div>

        <div className="bg-[#101C2C] p-5 rounded-xl border border-slate-800 shadow">
          <div className="text-xs text-slate-400 font-semibold uppercase flex items-center justify-between">
            <span>Pending Payouts</span>
            <Clock className="w-4 h-4 text-amber-400" />
          </div>
          <div className="text-2xl font-bold font-serif-luxury text-amber-400 mt-2">
            {formatPKRCurrency(totalAgencyCommissionPending)}
          </div>
          <div className="text-[11px] text-amber-400/80 mt-1">Due upon registry and possession transfer</div>
        </div>
      </div>

      {/* Filter and Search */}
      <div className="flex flex-col sm:flex-row items-center justify-between gap-4 bg-[#101C2C] p-4 rounded-xl border border-slate-800">
        <div className="relative w-full sm:w-80">
          <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            placeholder="Search dealer by name, agency, phone..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="w-full bg-[#0B1220] text-white text-xs pl-9 pr-4 py-2.5 rounded-lg border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
          />
        </div>
        <span className="text-xs text-slate-400">
          Showing <strong>{filteredDealers.length}</strong> agents & facilitators
        </span>
      </div>

      {/* Dealers Cards Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {filteredDealers.map((dealer) => {
          const earned = dealer.totalCommissionEarnedPKR ?? dealer.totalEarnedPKR ?? 0;
          const paid = dealer.totalCommissionPaidPKR ?? 0;
          const pendingBalance = dealer.pendingCommissionPKR ?? (earned - paid);
          const commVal = dealer.commissionValue ?? dealer.commissionRate ?? 20000;
          const cnicVal = dealer.cnic || dealer.cnicMasked;
          const agencyVal = dealer.agencyOrBranch || dealer.agencyAffiliation || 'TaisarTownHub Direct Sales';
          const dealsCount = dealer.dealsClosed ?? dealer.completedDealsCount ?? 0;
          const dealerDeals = transactions.filter((t) => t.dealerId === dealer.id);

          return (
            <div
              key={dealer.id}
              className="bg-[#101C2C] rounded-2xl border border-slate-800 p-6 flex flex-col justify-between hover:border-[#C5A46D]/50 transition-all shadow-lg"
            >
              <div className="space-y-4">
                <div className="flex items-start justify-between">
                  <div className="flex items-center gap-3">
                    <div className="w-11 h-11 rounded-xl bg-gradient-to-br from-[#C5A46D]/20 to-[#101C2C] border border-[#C5A46D]/40 flex items-center justify-center font-serif-luxury font-bold text-[#F9E7BA] text-lg shadow">
                      {dealer.fullName.charAt(0)}
                    </div>
                    <div>
                      <h3 className="font-bold text-white text-sm">
                        {dealer.fullName}
                      </h3>
                      <span className="text-[11px] text-[#C5A46D] block">
                        {agencyVal}
                      </span>
                    </div>
                  </div>
                  <div className="flex items-center gap-1.5">
                    <button
                      onClick={() => handleOpenEdit(dealer)}
                      className="p-1.5 rounded-lg bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 transition cursor-pointer"
                      title="Edit Dealer"
                    >
                      <Edit2 className="w-3.5 h-3.5" />
                    </button>
                    <button
                      onClick={() => handleDelete(dealer.id, dealer.fullName)}
                      className="p-1.5 rounded-lg bg-slate-800 text-slate-400 hover:text-rose-400 hover:bg-rose-950/30 transition cursor-pointer"
                      title="Delete Dealer"
                    >
                      <Trash2 className="w-3.5 h-3.5" />
                    </button>
                  </div>
                </div>

                {/* Commission Structure Badge */}
                <div className="p-3 rounded-xl bg-[#0B1220] border border-slate-800 space-y-2 text-xs">
                  <div className="flex items-center justify-between">
                    <span className="text-slate-400">Commission Rule:</span>
                    <span className="font-bold text-[#C5A46D] capitalize">
                      {dealer.commissionType?.toLowerCase() === 'fixed'
                        ? `PKR ${commVal.toLocaleString()} Fixed / Deal`
                        : dealer.commissionType?.toLowerCase() === 'percentage'
                        ? `${commVal}% of Deal Value`
                        : 'Custom Agreement'}
                    </span>
                  </div>

                  <div className="flex items-center justify-between">
                    <span className="text-slate-400">Phone / WhatsApp:</span>
                    <span className="font-mono text-white">{dealer.phone}</span>
                  </div>

                  <div className="flex items-center justify-between">
                    <span className="text-slate-400">CNIC:</span>
                    <span className="font-mono text-slate-300">
                      {hasPermission(currentUser.role, 'view_sensitive_cnic')
                        ? (dealer.cnic ?? dealer.cnicMasked ?? 'N/A')
                        : (dealer.cnicMasked || maskCNIC(dealer.cnic ?? ''))}
                    </span>
                  </div>
                </div>

                {/* Financial Summary */}
                <div className="grid grid-cols-2 gap-2 text-center text-xs">
                  <div className="p-2.5 rounded-lg bg-slate-900/80 border border-slate-800">
                    <span className="text-[10px] text-slate-400 uppercase block">Deals Closed</span>
                    <span className="font-bold text-white text-sm">{dealsCount}</span>
                  </div>
                  <div className="p-2.5 rounded-lg bg-slate-900/80 border border-slate-800">
                    <span className="text-[10px] text-slate-400 uppercase block">Earned</span>
                    <span className="font-bold text-emerald-400 text-xs">
                      {formatPKRCurrency(earned)}
                    </span>
                  </div>
                </div>

                {/* Pending vs Settled */}
                <div className="p-3 rounded-xl bg-[#0B1220] border border-slate-800/80 space-y-1.5 text-xs">
                  <div className="flex justify-between items-center">
                    <span className="text-slate-400">Paid to date:</span>
                    <span className="text-slate-200 font-mono">{formatPKRCurrency(paid)}</span>
                  </div>
                  <div className="flex justify-between items-center pt-1 border-t border-slate-800">
                    <span className="font-semibold text-slate-300">Pending Payable:</span>
                    <span className={`font-mono font-bold ${pendingBalance > 0 ? 'text-amber-400' : 'text-slate-400'}`}>
                      {formatPKRCurrency(pendingBalance)}
                    </span>
                  </div>
                </div>

                {/* Deals reference */}
                {dealerDeals.length > 0 && (
                  <div className="text-[11px] text-slate-400">
                    <span className="font-semibold text-slate-300">Active Deal Associated: </span>
                    {dealerDeals.map((d) => (
                      <span key={d.id} className="text-[#C5A46D] font-mono">
                        {d.dealNumber} ({d.status})
                      </span>
                    ))}
                  </div>
                )}
              </div>

              {/* Action: Settle Payout & View Stock Files */}
              <div className="pt-4 mt-4 border-t border-slate-800 flex flex-wrap items-center justify-between gap-2">
                <button
                  type="button"
                  onClick={() => setSelectedDealerForFiles(dealer)}
                  className="px-2.5 py-1.5 rounded-lg bg-[#0B1220] hover:bg-[#C5A46D]/20 text-[#C5A46D] text-[11px] font-semibold transition flex items-center gap-1.5 cursor-pointer border border-[#C5A46D]/30"
                  title="Inspect Dealer Inventory Files"
                >
                  <Building className="w-3.5 h-3.5" />
                  <span>Stock Files ({properties.filter((p) => p.assignedDealerId === dealer.id || p.contactPerson === dealer.fullName).length})</span>
                </button>

                <button
                  type="button"
                  onClick={() => handleOpenPayout(dealer)}
                  className="px-3 py-1.5 rounded-lg bg-[#C5A46D]/20 hover:bg-[#C5A46D] text-[#C5A46D] hover:text-[#101C2C] text-xs font-bold transition flex items-center gap-1.5 cursor-pointer border border-[#C5A46D]/40"
                  disabled={!hasPermission(currentUser.role, 'pay_commissions')}
                  title={
                    hasPermission(currentUser.role, 'pay_commissions')
                      ? 'Settle pending commission'
                      : 'Only Super Admin can execute commission payouts'
                  }
                >
                  <Wallet className="w-3.5 h-3.5" />
                  <span>Settle Commission</span>
                </button>
              </div>
            </div>
          );
        })}
      </div>

      {/* Settle Commission Payout Modal */}
      {isPayoutModalOpen && selectedDealerForPayout && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fadeIn">
          <div className="relative w-full max-w-md bg-[#101C2C] border border-[#C5A46D]/40 rounded-2xl p-6 sm:p-8 shadow-2xl">
            <h3 className="font-serif-luxury text-xl font-bold text-white mb-1">
              Disburse Commission Payout
            </h3>
            <p className="text-xs text-slate-400 mb-4">
              Recording payment to <strong>{selectedDealerForPayout.fullName}</strong>.
            </p>

            <form onSubmit={handleExecutePayout} className="space-y-4 text-xs">
              <div className="p-3 rounded-xl bg-[#0B1220] border border-slate-800 space-y-1">
                <div className="flex justify-between text-slate-400">
                  <span>Total Earned (ledger):</span>
                  <span className="text-white font-mono">{formatPKRCurrency(ledgerEarned)}</span>
                </div>
                <div className="flex justify-between text-slate-400">
                  <span>Already Paid:</span>
                  <span className="text-emerald-400 font-mono">{formatPKRCurrency(ledgerPaid)}</span>
                </div>
                <div className="flex justify-between font-bold text-amber-400 pt-1 border-t border-slate-800">
                  <span>Payable (accrued):</span>
                  <span>{formatPKRCurrency(ledgerPayable)}</span>
                </div>
              </div>

              <div>
                <label className="block text-slate-300 font-semibold mb-1">
                  Payment Amount (PKR) *
                </label>
                <input
                  type="number"
                  required
                  value={payoutAmount}
                  onChange={(e) => setPayoutAmount(Number(e.target.value))}
                  className="w-full bg-[#0B1220] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#C5A46D] focus:outline-none font-mono text-sm"
                />
              </div>

              <div className="flex items-center justify-end gap-3 pt-3">
                <button
                  type="button"
                  onClick={() => setIsPayoutModalOpen(false)}
                  className="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 rounded-lg bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C] font-bold cursor-pointer shadow"
                >
                  Confirm Payout
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Add / Edit Dealer Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fadeIn">
          <div className="relative w-full max-w-md bg-[#101C2C] border border-[#C5A46D]/40 rounded-2xl p-6 sm:p-8 shadow-2xl">
            <h3 className="font-serif-luxury text-xl font-bold text-white mb-1">
              {editingDealerId ? 'Edit Dealer Details' : 'Register New Real Estate Dealer'}
            </h3>
            <p className="text-xs text-slate-400 mb-6">
              Specify broker details, commission rate, and agency affiliation.
            </p>

            <form onSubmit={handleSubmit} className="space-y-4 text-xs">
              <div>
                <label className="block text-slate-300 font-semibold mb-1">Agent Full Name *</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Saleem Siddiqui"
                  value={formData.fullName}
                  onChange={(e) => setFormData({ ...formData, fullName: e.target.value })}
                  className="w-full bg-[#0B1220] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-slate-300 font-semibold mb-1">CNIC Number</label>
                  <input
                    type="text"
                    placeholder="42101-9876543-1"
                    value={formData.cnic}
                    onChange={(e) => setFormData({ ...formData, cnic: e.target.value })}
                    className="w-full bg-[#0B1220] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#C5A46D] focus:outline-none font-mono"
                  />
                </div>
                <div>
                  <label className="block text-slate-300 font-semibold mb-1">Phone / WhatsApp *</label>
                  <input
                    type="text"
                    required
                    placeholder="0321-9876543"
                    value={formData.phone}
                    onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                    className="w-full bg-[#0B1220] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#C5A46D] focus:outline-none font-mono"
                  />
                </div>
              </div>

              <div>
                <label className="block text-slate-300 font-semibold mb-1">Affiliated Agency / Office</label>
                <input
                  type="text"
                  placeholder="e.g. Al-Madina Real Estate / Taiser Town Desk"
                  value={formData.agencyOrBranch}
                  onChange={(e) => setFormData({ ...formData, agencyOrBranch: e.target.value })}
                  className="w-full bg-[#0B1220] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-slate-300 font-semibold mb-1">Commission Type</label>
                  <select
                    value={formData.commissionType}
                    onChange={(e) => setFormData({ ...formData, commissionType: e.target.value as CommissionType })}
                    className="w-full bg-[#0B1220] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                  >
                    <option value="fixed">Fixed Amount (PKR)</option>
                    <option value="percentage">Percentage (%)</option>
                    <option value="custom">Custom Deal by Deal</option>
                  </select>
                </div>
                <div>
                  <label className="block text-slate-300 font-semibold mb-1">
                    {formData.commissionType === 'percentage' ? 'Percentage Rate (%)' : 'Amount in PKR'}
                  </label>
                  <input
                    type="number"
                    value={formData.commissionValue}
                    onChange={(e) => setFormData({ ...formData, commissionValue: Number(e.target.value) })}
                    className="w-full bg-[#0B1220] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#C5A46D] focus:outline-none font-mono"
                  />
                </div>
              </div>

              <div className="flex items-center justify-end gap-3 pt-3">
                <button
                  type="button"
                  onClick={() => setIsModalOpen(false)}
                  className="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 rounded-lg bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C] font-bold cursor-pointer shadow"
                >
                  {editingDealerId ? 'Save Terms' : 'Register Dealer'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Agency Stock & Files Modal */}
      <AgencyDetailsModal
        dealer={selectedDealerForFiles}
        isOpen={!!selectedDealerForFiles}
        onClose={() => setSelectedDealerForFiles(null)}
      />
    </div>
  );
};
