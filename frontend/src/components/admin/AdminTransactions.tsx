import React, { useState } from 'react';
import { useApp } from '../../context/AppContext';
import {
  FileText,
  Plus,
  Search,
  Filter,
  CheckCircle2,
  Clock,
  Printer,
  ChevronRight,
  TrendingUp,
  AlertCircle,
  Building,
  User,
  DollarSign,
  Wallet,
  Ban,
  X,
  FileCheck
} from 'lucide-react';
import { Transaction, TransactionStatus, CommissionStatus, PaymentEntry, CommissionEntry } from '../../types';
import { formatPKRCurrency, calculateERPFinancials, calculateCommission, maskCNIC } from '../../utils/logic';
import { authorize, normalizeRole } from '../../domain/permissions';
import { dealMoney } from '../../domain/transactions';
import { EmptyState } from '../common/EmptyState';

export const AdminTransactions: React.FC = () => {
  const {
    transactions,
    addTransaction,
    updateTransaction,
    properties,
    sellers,
    buyers,
    dealers,
    paymentEntries,
    commissionEntries,
    recordPayment,
    recordRefund,
    voidTransaction,
    showToast,
    currentTheme,
    currentUser,
    setAdminTab
  } = useApp();

  const [searchQuery, setSearchQuery] = useState('');
  const [filterStatus, setFilterStatus] = useState<string>('all');
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [selectedTxnForReceipt, setSelectedTxnForReceipt] = useState<Transaction | null>(null);

  // Dealer base accounts are scoped unless their active agency membership grants
  // agency-wide finance visibility (e.g. an agency_owner membership).
  const isDealer = normalizeRole(currentUser.role) === 'dealer'
    && !currentUser.permissions.includes('finance.view');
  const scopedDealerId = isDealer ? currentUser.dealerId : undefined;
  const visibleTransactions = (isDealer
    ? transactions.filter((t) => t.dealerId && t.dealerId === scopedDealerId)
    : transactions
  ).filter((t) => !t.deletedAt);

  const canCreateDeals = authorize(currentUser, 'create_transactions').ok;
  const canUpdateDeals = authorize(currentUser, 'update_transactions').ok;

  const [formData, setFormData] = useState({
    propertyId: properties[0]?.id || '',
    sellerId: sellers[0]?.id || '',
    buyerId: buyers[0]?.id || '',
    dealerId: '',
    dealAmountPKR: properties[0]?.pricePKR || 0,
    advancePKR: 0,
    dealDate: new Date().toISOString().split('T')[0],
    expectedPossessionDate: '2026-04-15',
    status: 'Advance Received' as TransactionStatus,
    paymentMode: 'Bank Pay Order',
    notes: ''
  });

  // Ledger subset visible to the current viewer (dealer => only own deals' money).
  const visibleTxnIds = new Set(visibleTransactions.map((t) => t.id));
  const visiblePayments = paymentEntries.filter((p) => visibleTxnIds.has(p.transactionId));
  const visibleCommissions = commissionEntries.filter((c) => visibleTxnIds.has(c.transactionId));

  const financials = calculateERPFinancials(visibleTransactions, visiblePayments, visibleCommissions);

  // Fast lookup by deal for the table (payments history + commission accrual badge).
  const paymentsByTxId = new Map<string, PaymentEntry[]>();
  for (const p of visiblePayments) {
    const list = paymentsByTxId.get(p.transactionId) || [];
    list.push(p);
    paymentsByTxId.set(p.transactionId, list);
  }
  const commissionByTxId = new Map<string, CommissionEntry[]>();
  for (const commission of visibleCommissions) {
    const entries = commissionByTxId.get(commission.transactionId) || [];
    entries.push(commission);
    commissionByTxId.set(commission.transactionId, entries);
  }

  // P1-2/P1-1 UI gates: recording money and voiding records are privileged ops;
  // the context actions re-check these on every call (UI hiding is cosmetic).
  const canRecordPayments = authorize(currentUser, 'manage_payments').ok;
  const canVoid = authorize(currentUser, 'void_transactions').ok;

  // Record-payment modal state
  const [paymentModalTxn, setPaymentModalTxn] = useState<Transaction | null>(null);
  const [paymentModalMode, setPaymentModalMode] = useState<'payment' | 'refund'>('payment');
  const [paymentAmount, setPaymentAmount] = useState<number>(0);
  const [paymentMethod, setPaymentMethod] = useState<PaymentEntry['method']>('Bank Transfer');
  const [paymentNote, setPaymentNote] = useState('');

  const openPaymentModal = (txn: Transaction) => {
    const money = dealMoney(txn, paymentsByTxId.get(txn.id) || []);
    setPaymentModalTxn(txn);
    setPaymentModalMode(txn.status === 'Cancelled' && money.collected > 0 ? 'refund' : 'payment');
    setPaymentAmount(money.balance > 0 ? money.balance : 0);
    setPaymentMethod('Bank Transfer');
    setPaymentNote('');
  };

  const openRefundModal = (txn: Transaction) => {
    const money = dealMoney(txn, paymentsByTxId.get(txn.id) || []);
    setPaymentModalTxn(txn);
    setPaymentModalMode('refund');
    setPaymentAmount(money.collected > 0 ? money.collected : 0);
    setPaymentMethod('Bank Transfer');
    setPaymentNote('');
  };

  const handleConfirmPayment = (e: React.FormEvent) => {
    e.preventDefault();
    if (!paymentModalTxn) return;
    if (!Number.isSafeInteger(paymentAmount) || paymentAmount <= 0) {
      showToast('error', 'Amount must be a positive whole-rupee PKR value.');
      return;
    }

    const accepted = paymentModalMode === 'refund'
      ? recordRefund(paymentModalTxn.id, paymentAmount, paymentMethod, paymentNote.trim())
      : recordPayment(paymentModalTxn.id, paymentAmount, paymentMethod, paymentNote.trim() || undefined);
    if (accepted) setPaymentModalTxn(null);
  };

  // Void (super-admin, audited) modal state
  const [voidModalTxn, setVoidModalTxn] = useState<Transaction | null>(null);
  const [voidReason, setVoidReason] = useState('');

  const handleConfirmVoid = (e: React.FormEvent) => {
    e.preventDefault();
    if (!voidModalTxn) return;
    if (!voidReason.trim()) {
      showToast('error', 'A reason is required to void a deal (audit trail).');
      return;
    }
    voidTransaction(voidModalTxn.id, voidReason.trim());
    setVoidModalTxn(null);
    setVoidReason('');
  };

  // Cancelling via the status select needs a reason (domain rule) — intercept it.
  const [cancelModalTxnId, setCancelModalTxnId] = useState<string | null>(null);
  const [cancelReason, setCancelReason] = useState('');

  const handleStatusSelect = (txn: Transaction, next: TransactionStatus) => {
    if (next === 'Cancelled') {
      setCancelModalTxnId(txn.id);
      setCancelReason('');
      return;
    }
    updateTransaction(txn.id, { status: next });
  };

  const handleConfirmCancel = (e: React.FormEvent) => {
    e.preventDefault();
    if (!cancelModalTxnId) return;
    if (!cancelReason.trim()) {
      showToast('error', 'A cancellation reason is required.');
      return;
    }
    updateTransaction(cancelModalTxnId, { status: 'Cancelled', notes: cancelReason.trim() });
    setCancelModalTxnId(null);
    setCancelReason('');
  };

  const filteredTxns = visibleTransactions.filter((t) => {
    const dNum = t.dealNumber || t.id;
    const pNum = t.plotNumber || '';
    const sName = t.sellerName || '';
    const bName = t.buyerName || '';
    const sec = t.sector || '';

    const matchesSearch =
      dNum.toLowerCase().includes(searchQuery.toLowerCase()) ||
      pNum.toLowerCase().includes(searchQuery.toLowerCase()) ||
      sName.toLowerCase().includes(searchQuery.toLowerCase()) ||
      bName.toLowerCase().includes(searchQuery.toLowerCase()) ||
      sec.toLowerCase().includes(searchQuery.toLowerCase());

    const matchesStatus = filterStatus === 'all' || t.status === filterStatus;
    return matchesSearch && matchesStatus;
  });

  const handleOpenAdd = () => {
    const firstProp = properties[0];

    setFormData({
      propertyId: firstProp?.id || '',
      sellerId: '',
      buyerId: '',
      dealerId: '',
      dealAmountPKR: firstProp?.pricePKR || 0,
      advancePKR: 0,
      dealDate: new Date().toISOString().split('T')[0],
      expectedPossessionDate: '2026-04-15',
      status: 'Advance Received',
      paymentMode: 'Bank Pay Order',
      notes: ''
    });
    setIsModalOpen(true);
  };

  const handlePropertyChange = (propId: string) => {
    const prop = properties.find((p) => p.id === propId);
    if (prop) {
      setFormData((prev) => ({
        ...prev,
        propertyId: propId,
        dealAmountPKR: prop.pricePKR,
        sellerId: prop.sellerId || prev.sellerId
      }));
    }
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const prop = properties.find((p) => p.id === formData.propertyId);
    const seller = sellers.find((s) => s.id === formData.sellerId);
    const buyer = buyers.find((b) => b.id === formData.buyerId);
    const dealer = dealers.find((d) => d.id === formData.dealerId);

    // ---- Deal form validation (P1 partial: full state machine comes in Phase 3) ----
    if (!prop) {
      showToast('error', 'Please select a valid property for the deal.');
      return;
    }
    if (!seller || !buyer) {
      showToast('error', 'Please select both valid seller and buyer.');
      return;
    }
    if (seller.id === buyer.id) {
      showToast('error', 'Seller and buyer cannot be the same record.');
      return;
    }
    if (!(formData.dealAmountPKR > 0)) {
      showToast('error', 'Agreed deal amount must be a positive number.');
      return;
    }
    if (formData.advancePKR < 0) {
      showToast('error', 'Advance cannot be negative.');
      return;
    }
    if (formData.advancePKR > formData.dealAmountPKR) {
      showToast('error', 'Advance deposit cannot exceed the agreed deal amount.');
      return;
    }

    // No dealer selected => zero commission. Never book a phantom amount.
    const calculatedComm = dealer
      ? calculateCommission(formData.dealAmountPKR, dealer.commissionType, dealer.commissionValue ?? dealer.commissionRate ?? 0)
      : 0;

    const balance = Math.max(0, formData.dealAmountPKR - formData.advancePKR);

    // NOTE (P0-5): we deliberately do NOT copy seller/buyer CNICs into the
    // transaction. Parties are referenced by id; masked values are joined at
    // render time and unmasked values require `view_sensitive_cnic`.
    addTransaction({
      dealNumber: `TXN-2026-00${transactions.length + 1}`,
      propertyId: formData.propertyId,
      propertyTitle: prop.title,
      plotNumber: prop.plotNumber || 'Plot-Unnumbered',
      fileNumber: prop.fileNumber || 'TT-FILE',
      sector: prop.sector,
      sellerId: seller.id,
      sellerName: seller.fullName,
      buyerId: buyer.id,
      buyerName: buyer.fullName,
      dealerId: dealer?.id,
      dealerName: dealer?.fullName,
      agreementAmountPKR: Number(formData.dealAmountPKR),
      purchaseAmountPKR: prop.purchasePricePKR ?? 0,
      finalSellingAmountPKR: Number(formData.dealAmountPKR),
      dealAmountPKR: Number(formData.dealAmountPKR),
      advancePaymentPKR: Number(formData.advancePKR),
      advancePKR: Number(formData.advancePKR),
      remainingBalancePKR: balance,
      balancePKR: balance,
      commissionAmountPKR: calculatedComm,
      commissionPKR: calculatedComm,
      commissionStatus: 'Unpaid',
      status: formData.status,
      transactionDate: formData.dealDate,
      dealDate: formData.dealDate,
      expectedPossessionDate: formData.expectedPossessionDate,
      paymentMethod: 'Pay Order',
      paymentMode: formData.paymentMode,
      internalRemarks: formData.notes,
      notes: formData.notes,
      documents: []
    });

    showToast('success', 'New property deal transaction registered.');
    setIsModalOpen(false);
  };

  return (
    <div className="space-y-6 animate-fadeIn">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-6 rounded-2xl bg-gradient-to-r from-[#0B1220] to-[#1A2638] border border-[#E7C878]/30 shadow-xl">
        <div>
          <div className="flex items-center gap-2">
            <h2 className="font-serif-luxury text-2xl font-bold text-white">
              Deals & Transaction Management
            </h2>
            <span className="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-[#E7C878]/20 text-[#E7C878] border border-[#E7C878]/40">
              Audit-Ready Ledger
            </span>
          </div>
          <p className="text-xs text-slate-300 mt-1">
            Official contract booking, token advances, buyer/seller settlement, and MDA transfer order tracking.
          </p>
        </div>

        {canCreateDeals && (
          <button
            onClick={handleOpenAdd}
            className="px-4 py-2.5 rounded-xl bg-[#E7C878] hover:bg-[#F3C464] text-[#0B1220] font-bold text-xs flex items-center justify-center gap-2 cursor-pointer shadow-lg shadow-[#E7C878]/20"
          >
            <Plus className="w-4 h-4" />
            <span>Execute New Deal</span>
          </button>
        )}
        {isDealer && (
          <span className="px-3 py-1.5 rounded-xl text-[10px] font-bold bg-amber-500/10 text-amber-300 border border-amber-500/30">
            Scoped view: your deals only ({visibleTransactions.length})
          </span>
        )}
      </div>

      {/* Financial Overview Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div className="bg-[#0B1220] p-5 rounded-xl border border-slate-800 shadow">
          <span className="text-xs text-slate-400 font-semibold uppercase block">Gross Sales Value</span>
          <div className="text-2xl font-bold font-serif-luxury text-white mt-1">
            {formatPKRCurrency(financials.totalSalesVolume)}
          </div>
          <div className="text-[11px] text-[#E7C878] mt-1">{financials.totalDealsCount} Total Deals</div>
        </div>

        <div className="bg-[#0B1220] p-5 rounded-xl border border-slate-800 shadow">
          <span className="text-xs text-slate-400 font-semibold uppercase block">Advances Received</span>
          <div className="text-2xl font-bold font-serif-luxury text-[#E7C878] mt-1">
            {formatPKRCurrency(financials.totalAdvancesCollected)}
          </div>
          <div className="text-[11px] text-[#F6E3B4] mt-1">Escrow & token cash/orders</div>
        </div>

        <div className="bg-[#0B1220] p-5 rounded-xl border border-slate-800 shadow">
          <span className="text-xs text-slate-400 font-semibold uppercase block">Pending Receivables</span>
          <div className="text-2xl font-bold font-serif-luxury text-amber-400 mt-1">
            {formatPKRCurrency(financials.pendingReceivables)}
          </div>
          <div className="text-[11px] text-amber-300 mt-1">Due at MDA transfer office</div>
        </div>

        <div className="bg-[#0B1220] p-5 rounded-xl border border-slate-800 shadow">
          <span className="text-xs text-slate-400 font-semibold uppercase block">Total Agent Commissions</span>
          <div className="text-2xl font-bold font-serif-luxury text-[#E7C878] mt-1">
            {formatPKRCurrency(financials.totalCommissionPayable)}
          </div>
          <div className="text-[11px] text-slate-400 mt-1">Facilitation fees booked</div>
        </div>
      </div>

      {/* Filter and Search Bar */}
      <div className="flex flex-col md:flex-row items-center justify-between gap-4 bg-[#0B1220] p-4 rounded-xl border border-slate-800">
        <div className="relative w-full md:w-80">
          <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            placeholder="Search by Deal #, Plot #, Buyer, Seller..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="w-full bg-[#07141E] text-white text-xs pl-9 pr-4 py-2.5 rounded-lg border border-slate-700 focus:border-[#E7C878] focus:outline-none"
          />
        </div>

        <div className="flex items-center gap-2 w-full md:w-auto overflow-x-auto">
          {['all', 'Advance Received', 'Under Transfer', 'Completed', 'Draft'].map((status) => (
            <button
              key={status}
              onClick={() => setFilterStatus(status)}
              className={`px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap cursor-pointer transition ${
                filterStatus === status
                  ? 'bg-[#E7C878] text-[#0B1220]'
                  : 'bg-[#07141E] text-slate-400 hover:text-white border border-slate-800'
              }`}
            >
              {status === 'all' ? 'All Transactions' : status}
            </button>
          ))}
        </div>
      </div>

      {/* Transactions Table */}
      <div className="bg-[#0B1220] rounded-2xl border border-slate-800 shadow-xl overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-[#07141E] text-slate-400 uppercase tracking-wider text-[10px] border-b border-slate-800">
              <tr>
                <th className="py-3.5 px-4">Deal ID & Date</th>
                <th className="py-3.5 px-4">Plot / Property</th>
                <th className="py-3.5 px-4">Seller & Buyer</th>
                <th className="py-3.5 px-4">Deal Amount</th>
                <th className="py-3.5 px-4">Paid / Balance</th>
                <th className="py-3.5 px-4">Commission</th>
                <th className="py-3.5 px-4">Status</th>
                <th className="py-3.5 px-4 text-right">Ledger & Receipt</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800/80">
              {filteredTxns.map((txn) => {
                // Honest lineage flag: only the actual handwritten-record demo
                // deal (Plot R-66), NOT any deal whose number merely contains "001".
                const isHandwrittenDeal = txn.propertyId === 'prop-r66';
                const payRows = paymentsByTxId.get(txn.id) || [];
                const commRows = commissionByTxId.get(txn.id) || [];
                const activeCommissions = commRows.filter((entry) => entry.status !== 'Cancelled');
                const commissionAmount = activeCommissions.length > 0
                  ? activeCommissions.reduce((sum, entry) => sum + entry.amountPKR, 0)
                  : commRows.length > 0 ? 0 : (txn.commissionPKR ?? txn.commissionAmountPKR ?? 0);
                const commissionPaid = activeCommissions.reduce((sum, entry) => sum + (entry.paidPKR ?? 0), 0);
                const commissionStatus = commRows.length === 0
                  ? txn.commissionStatus
                  : activeCommissions.length === 0
                  ? 'Cancelled'
                  : commissionPaid >= commissionAmount && commissionAmount > 0
                  ? 'Paid'
                  : commissionPaid > 0
                  ? 'Partially Paid'
                  : activeCommissions.some((entry) => ['Accrued', 'Partially Paid', 'Paid'].includes(entry.status))
                  ? 'Accrued'
                  : 'Pending Accrual';
                const money = dealMoney(txn, payRows);

                return (
                  <tr
                    key={txn.id}
                    className={`hover:bg-slate-800/40 transition-colors ${
                      isHandwrittenDeal ? 'bg-[#E7C878]/5' : ''
                    } ${txn.voided ? 'opacity-55' : ''}`}
                  >
                    <td className="py-4 px-4">
                      <div className="font-bold text-white font-mono flex items-center gap-1.5">
                        <span>{txn.dealNumber || txn.id}</span>
                        {isHandwrittenDeal && (
                          <span className="px-1.5 py-0.5 rounded text-[9px] font-bold bg-[#E7C878] text-[#0B1220]">
                            Handwritten Note
                          </span>
                        )}
                        {txn.voided && (
                          <span
                            className="px-1.5 py-0.5 rounded text-[9px] font-bold bg-slate-800 text-slate-300 border border-slate-700"
                            title={txn.voidReason ? `Voided by ${txn.voidedBy}: ${txn.voidReason}` : undefined}
                          >
                            VOIDED
                          </span>
                        )}
                      </div>
                      <span className="text-[10px] text-slate-400">{txn.dealDate}</span>
                    </td>

                    <td className="py-4 px-4">
                      <div className="font-bold text-[#E7C878]">
                        Plot {txn.plotNumber}
                      </div>
                      <span className="text-[10px] text-slate-300 block">{txn.sector}</span>
                      <span className="text-[10px] text-slate-500 font-mono">File: {txn.fileNumber}</span>
                    </td>

                    <td className="py-4 px-4">
                      <div className="text-slate-200">
                        <span className="text-slate-400 text-[10px]">Seller:</span> <strong>{txn.sellerName}</strong>
                      </div>
                      <div className="text-slate-200">
                        <span className="text-slate-400 text-[10px]">Buyer:</span> <strong>{txn.buyerName}</strong>
                      </div>
                      {txn.dealerName && (
                        <span className="text-[10px] text-[#E7C878] block mt-0.5">
                          Agent: {txn.dealerName}
                        </span>
                      )}
                    </td>

                    <td className="py-4 px-4 font-bold text-white font-mono text-sm">
                      {formatPKRCurrency(txn.dealAmountPKR ?? txn.finalSellingAmountPKR ?? txn.agreementAmountPKR ?? 0)}
                    </td>

                    <td className="py-4 px-4">
                      <div className="text-white font-mono font-semibold">
                        Paid: {formatPKRCurrency(money.collected)}
                      </div>
                      <div className="text-slate-400 font-mono text-[11px]">
                        Bal: {formatPKRCurrency(money.balance)}
                        {payRows.length > 0 && (
                          <span className="text-slate-500"> · {payRows.length} entr{payRows.length === 1 ? 'y' : 'ies'}</span>
                        )}
                      </div>
                    </td>

                    <td className="py-4 px-4">
                      <div className="font-bold text-[#F6E3B4] font-mono">
                        {formatPKRCurrency(commissionAmount)}
                      </div>
                      <span className={`px-2 py-0.5 rounded-full text-[9px] font-bold ${
                        commissionStatus === 'Paid'
                          ? 'bg-[#E7C878]/20 text-[#F6E3B4]'
                          : commissionStatus === 'Accrued'
                          ? 'bg-[#E7C878]/10 text-[#E7C878]'
                          : commissionStatus === 'Partially Paid'
                          ? 'bg-slate-800 text-slate-300'
                          : commissionStatus === 'Cancelled'
                          ? 'bg-slate-800 text-slate-400'
                          : 'bg-slate-800 text-slate-400'
                      }`}>
                        {commissionStatus}
                      </span>
                    </td>

                    <td className="py-4 px-4">
                      <select
                        value={txn.status}
                        disabled={!canUpdateDeals}
                        title={canUpdateDeals ? 'Update deal status' : 'Your role cannot modify deal status'}
                        onChange={(e) => handleStatusSelect(txn, e.target.value as TransactionStatus)}
                        className={`text-[10px] font-bold px-2 py-1 rounded-lg border focus:outline-none ${
                          txn.status === 'Completed'
                            ? 'bg-[#07141E] text-[#F6E3B4] border-[#E7C878]/40'
                            : txn.status === 'Under Transfer'
                            ? 'bg-[#0B1220] text-[#F6E3B4] border-[#E7C878]/40'
                            : txn.status === 'Advance Received'
                            ? 'bg-[#07141E] text-[#E7C878] border-[#E7C878]/30'
                            : 'bg-slate-800 text-slate-300 border-slate-700'
                        } ${canUpdateDeals ? 'cursor-pointer' : 'opacity-60 cursor-not-allowed'}`}
                      >
                        <option value="Draft">Draft</option>
                        <option value="Negotiation">Negotiation</option>
                        <option value="Agreement Signed">Agreement Signed</option>
                        <option value="Token Paid">Token Paid</option>
                        <option value="Advance Received">Advance Received</option>
                        <option value="Payment Pending">Payment Pending</option>
                        <option value="Partially Paid">Partially Paid</option>
                        <option value="Fully Paid">Fully Paid</option>
                        <option value="Under Transfer">Under Transfer</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                      </select>
                    </td>

                    <td className="py-4 px-4 text-right">
                      <div className="flex items-center justify-end gap-1.5">
                        {paymentModalTxn === null && (payRows.length > 0 || canRecordPayments) && (
                          <button
                            onClick={() => openPaymentModal(txn)}
                            className="px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-[#E7C878] hover:text-[#0B1220] text-[#E7C878] text-xs font-semibold transition flex items-center gap-1 cursor-pointer"
                            title={canRecordPayments ? 'View ledger & record payment or refund' : 'View payment ledger'}
                          >
                            <Wallet className="w-3.5 h-3.5" />
                            <span>{payRows.length > 0 ? `Payments (${payRows.length})` : 'Payments'}</span>
                          </button>
                        )}
                        {paymentModalTxn === null && canRecordPayments && !txn.voided
                          && dealMoney(txn, payRows).collected > 0 && (
                          <button
                            onClick={() => openRefundModal(txn)}
                            className="px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-rose-500/20 text-rose-300 border border-rose-900/50 text-xs font-semibold transition cursor-pointer"
                            title="Record an audited refund against collected funds"
                          >
                            Refund
                          </button>
                        )}

                        {canVoid && !txn.voided && (
                          <button
                            onClick={() => setVoidModalTxn(txn)}
                            className="px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white border border-slate-700 text-xs font-semibold transition cursor-pointer"
                            title="Void this deal with a mandatory reason (audit-preserved)"
                          >
                            <Ban className="w-3.5 h-3.5" />
                          </button>
                        )}

                        <button
                          onClick={() => setSelectedTxnForReceipt(txn)}
                          className="px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-[#E7C878] hover:text-[#0B1220] text-[#E7C878] text-xs font-semibold transition flex items-center gap-1 cursor-pointer"
                          title="View Official Sale Memorandum"
                        >
                          <Printer className="w-3.5 h-3.5" />
                          <span>Print</span>
                        </button>
                      </div>
                    </td>
                  </tr>
                );
              })}
              {filteredTxns.length === 0 && (
                <tr>
                  <td colSpan={8}>
                    <EmptyState
                      icon={<FileText className="w-9 h-9" />}
                      chip="CRM Ready"
                      title="No Transactions Yet"
                      description="Draft your first deal from a verified property file — lifecycle tracking, escrow milestones and dealer commissions stay synchronized automatically."
                      actionLabel="Open Property Listings"
                      onAction={() => setAdminTab('properties')}
                    />
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Sale Memorandum / Official Deal Receipt Modal */}
      {selectedTxnForReceipt && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fadeIn">
          <div className="relative w-full max-w-2xl bg-[#0B1220] text-slate-100 border border-[#E7C878]/40 rounded-2xl p-6 sm:p-8 shadow-2xl max-h-[90vh] overflow-y-auto">
            <button
              onClick={() => setSelectedTxnForReceipt(null)}
              className="absolute top-4 right-4 text-slate-400 hover:text-white p-1 rounded-lg cursor-pointer"
            >
              <X className="w-5 h-5" />
            </button>

            {/* Receipt Header */}
            <div className="border-b border-[#E7C878]/30 pb-6 mb-6 flex items-start justify-between">
              <div>
                <span className="text-[10px] uppercase font-bold tracking-widest text-[#E7C878] block">
                  OFFICIAL REAL ESTATE TRANSACTION MEMORANDUM
                </span>
                <h2 className="text-2xl font-bold font-serif-luxury text-white">
                  {currentTheme.agencyName}
                </h2>
                <p className="text-xs text-slate-400">
                  Taiser Town Scheme 45 Back-Office Operations Desk
                </p>
              </div>

              <div className="text-right">
                <span className="text-xs font-mono text-slate-400 block">Deal Reference #</span>
                <span className="text-base font-bold font-mono text-[#F6E3B4]">
                  {selectedTxnForReceipt.dealNumber}
                </span>
                <span className="text-xs text-slate-400 block mt-1">
                  Date: {selectedTxnForReceipt.dealDate}
                </span>
              </div>
            </div>

            {/* Property Summary */}
            <div className="bg-[#07141E] p-4 rounded-xl border border-slate-800 mb-6 space-y-2">
              <span className="text-xs font-bold uppercase tracking-wider text-[#E7C878]">
                Property Specifications
              </span>
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs pt-1">
                <div>
                  <span className="text-slate-400 block text-[10px]">Plot Number:</span>
                  <strong className="text-sm font-bold text-white">
                    Plot {selectedTxnForReceipt.plotNumber}
                  </strong>
                </div>
                <div>
                  <span className="text-slate-400 block text-[10px]">Sector:</span>
                  <strong className="text-sm font-bold text-white">
                    {selectedTxnForReceipt.sector}
                  </strong>
                </div>
                <div>
                  <span className="text-slate-400 block text-[10px]">File Number:</span>
                  <strong className="text-xs font-mono font-bold text-slate-300">
                    {selectedTxnForReceipt.fileNumber}
                  </strong>
                </div>
                <div>
                  <span className="text-slate-400 block text-[10px]">Deal Status:</span>
                  <strong className="text-xs font-bold text-[#E7C878]">
                    {selectedTxnForReceipt.status}
                  </strong>
                </div>
              </div>
            </div>

            {/* Parties — CNIC always rendered MASKED unless the current user
                holds view_sensitive_cnic (P0-5). Transactions reference parties
                by id; values are joined and masked at render time. */}
            {(() => {
              const canReveal = authorize(currentUser, 'view_sensitive_cnic').ok;
              const seller = sellers.find((s) => s.id === selectedTxnForReceipt.sellerId);
              const buyer = buyers.find((b) => b.id === selectedTxnForReceipt.buyerId);
              const sellerLine = canReveal && seller?.cnic
                ? seller.cnic
                : seller?.cnicMasked || maskCNIC(seller?.cnic ?? '');
              const buyerLine = canReveal && buyer?.cnic
                ? buyer.cnic
                : buyer?.cnicMasked || maskCNIC(buyer?.cnic ?? '');
              return (
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6 mb-6 text-xs">
                  <div className="p-4 rounded-xl bg-[#07141E] border border-slate-800 space-y-1">
                    <span className="text-[10px] font-bold uppercase text-[#E7C878] block">Party 1 (Seller / Allottee)</span>
                    <div className="font-bold text-sm text-white">{selectedTxnForReceipt.sellerName}</div>
                    <div className="text-slate-400 font-mono">CNIC: {sellerLine}</div>
                  </div>
                  <div className="p-4 rounded-xl bg-[#07141E] border border-slate-800 space-y-1">
                    <span className="text-[10px] font-bold uppercase text-[#E7C878] block">Party 2 (Buyer / Transferee)</span>
                    <div className="font-bold text-sm text-white">{selectedTxnForReceipt.buyerName}</div>
                    <div className="text-slate-400 font-mono">CNIC: {buyerLine}</div>
                  </div>
                </div>
              );
            })()}

            {/* Financial Ledger */}
            <div className="border border-slate-800 rounded-xl overflow-hidden mb-6 text-xs">
              <table className="w-full text-left">
                <thead className="bg-[#07141E] text-slate-400 font-bold border-b border-slate-800">
                  <tr>
                    <th className="py-2.5 px-4">Description</th>
                    <th className="py-2.5 px-4 text-right">Amount (PKR)</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800 font-mono">
                  <tr>
                    <td className="py-2.5 px-4 text-slate-300 font-sans">Total Agreed Deal Consideration</td>
                    <td className="py-2.5 px-4 text-right font-bold text-white">
                      {formatPKRCurrency(selectedTxnForReceipt.dealAmountPKR ?? selectedTxnForReceipt.finalSellingAmountPKR ?? selectedTxnForReceipt.agreementAmountPKR ?? 0)}
                    </td>
                  </tr>
                  <tr>
                    <td className="py-2.5 px-4 text-slate-300 font-sans">Advance / Bayana Deposit Received</td>
                    <td className="py-2.5 px-4 text-right font-bold text-[#E7C878]">
                      (-) {formatPKRCurrency(selectedTxnForReceipt.advancePKR ?? selectedTxnForReceipt.advancePaymentPKR ?? 0)}
                    </td>
                  </tr>
                  <tr className="bg-[#E7C878]/10 font-bold">
                    <td className="py-3 px-4 text-[#F6E3B4] font-sans">Outstanding Balance Payable at Transfer</td>
                    <td className="py-3 px-4 text-right text-base text-[#F6E3B4]">
                      {formatPKRCurrency(selectedTxnForReceipt.balancePKR ?? selectedTxnForReceipt.remainingBalancePKR ?? 0)}
                    </td>
                  </tr>
                  <tr>
                    <td className="py-2.5 px-4 text-slate-400 font-sans">
                      Agent Commission ({selectedTxnForReceipt.dealerName || 'Direct'})
                    </td>
                    <td className="py-2.5 px-4 text-right text-slate-300">
                      {formatPKRCurrency(
                        commissionEntries
                          .filter((entry) => entry.transactionId === selectedTxnForReceipt.id && entry.status !== 'Cancelled')
                          .reduce((sum, entry) => sum + entry.amountPKR, 0)
                        || selectedTxnForReceipt.commissionPKR
                        || selectedTxnForReceipt.commissionAmountPKR
                        || 0
                      )}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            {(selectedTxnForReceipt.notes || selectedTxnForReceipt.internalRemarks) && (
              <p className="text-xs text-slate-300 italic mb-6 bg-[#07141E] p-3 rounded-lg border border-slate-800">
                <strong className="text-[#E7C878]">Conditions: </strong> {selectedTxnForReceipt.notes || selectedTxnForReceipt.internalRemarks}
              </p>
            )}

            {/* Signatures */}
            <div className="grid grid-cols-3 gap-6 pt-6 border-t border-slate-800 text-center text-xs">
              <div>
                <div className="border-b border-slate-700 pb-8 mb-1" />
                <span className="font-semibold text-slate-300">Seller Signature</span>
              </div>
              <div>
                <div className="border-b border-slate-700 pb-8 mb-1" />
                <span className="font-semibold text-slate-300">Buyer Signature</span>
              </div>
              <div>
                <div className="border-b border-slate-700 pb-8 mb-1" />
                <span className="font-semibold text-slate-300">Authorized Officer</span>
              </div>
            </div>

            <div className="mt-8 flex justify-end gap-3 print:hidden">
              <button
                onClick={() => setSelectedTxnForReceipt(null)}
                className="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold text-xs cursor-pointer"
              >
                Close
              </button>
              <button
                onClick={() => window.print()}
                className="px-5 py-2 rounded-lg bg-[#E7C878] text-[#0B1220] hover:bg-[#F3C464] font-bold text-xs flex items-center gap-2 cursor-pointer shadow-lg shadow-[#E7C878]/20"
              >
                <Printer className="w-4 h-4" />
                <span>Print Deal Memorandum</span>
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Payment Ledger Modal (P1-2): history + record payment (permission-gated) */}
      {paymentModalTxn && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fadeIn">
          <div className="relative w-full max-w-lg bg-[#0B1220] text-slate-100 border border-[#E7C878]/40 rounded-2xl p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
            <button
              onClick={() => setPaymentModalTxn(null)}
              className="absolute top-4 right-4 text-slate-400 hover:text-white p-1 rounded-lg cursor-pointer"
            >
              <X className="w-5 h-5" />
            </button>

            <h3 className="font-serif-luxury text-xl font-bold text-white mb-1">Payment & Refund Ledger</h3>
            <p className="text-xs text-slate-400 mb-4">
              {paymentModalTxn.dealNumber || paymentModalTxn.id} · Plot {paymentModalTxn.plotNumber} {paymentModalTxn.sector}
            </p>

            {(() => {
              const rows = paymentsByTxId.get(paymentModalTxn.id) || [];
              const money = dealMoney(paymentModalTxn, rows);
              const paymentFrozen = paymentModalTxn.voided || paymentModalTxn.status === 'Cancelled';
              const refundFrozen = paymentModalTxn.voided || money.collected <= 0;
              return (
                <>
                  <div className="grid grid-cols-3 gap-3 mb-5">
                    <div className="p-3 rounded-xl bg-[#07141E] border border-[#E7C878]/30">
                      <span className="text-[10px] text-slate-400 block">Net collected</span>
                      <strong className="text-sm font-mono text-[#E7C878]">{formatPKRCurrency(money.collected)}</strong>
                    </div>
                    <div className="p-3 rounded-xl bg-[#07141E] border border-slate-700">
                      <span className="text-[10px] text-slate-400 block">Balance</span>
                      <strong className="text-sm font-mono text-white">{formatPKRCurrency(money.balance)}</strong>
                    </div>
                    <div className="p-3 rounded-xl bg-[#07141E] border border-slate-700">
                      <span className="text-[10px] text-slate-400 block">Deal</span>
                      <strong className="text-sm font-mono text-white">{formatPKRCurrency(money.dealAmount)}</strong>
                    </div>
                  </div>

                  <span className="text-[10px] font-bold uppercase tracking-wider text-[#E7C878] block mb-2">
                    Recorded movements ({rows.length})
                  </span>
                  <div className="space-y-2 mb-5 max-h-40 overflow-y-auto pr-1">
                    {rows.map((row) => {
                      const isRefund = row.type === 'refund';
                      return (
                        <div key={row.id} className="p-2.5 rounded-lg bg-[#07141E] border border-slate-800 text-xs flex items-start justify-between gap-3">
                          <div>
                            <div className={`font-mono font-bold ${isRefund ? 'text-rose-300' : 'text-[#E7C878]'}`}>
                              {isRefund ? '− ' : '+ '}{formatPKRCurrency(row.amountPKR)} {isRefund ? '· Refund' : ''}
                            </div>
                            <div className="text-[10px] text-slate-400">
                              {row.paidAt} · {row.method} · by {row.recordedBy}
                            </div>
                            {row.note && <div className="text-[10px] text-slate-500 italic mt-0.5">{row.note}</div>}
                          </div>
                        </div>
                      );
                    })}
                    {rows.length === 0 && (
                      <div className="text-[11px] text-slate-500 italic p-2">
                        No ledger movements yet — the recorded advance of this legacy deal appears in totals until the first entry is acknowledged.
                      </div>
                    )}
                  </div>

                  {canRecordPayments && (
                    <div className="flex gap-2 border-t border-slate-800 pt-4 mb-3">
                      <button
                        type="button"
                        disabled={paymentFrozen || money.balance <= 0}
                        onClick={() => {
                          setPaymentModalMode('payment');
                          setPaymentAmount(money.balance > 0 ? money.balance : 0);
                          setPaymentNote('');
                        }}
                        className="px-3 py-2 rounded-lg bg-slate-800 text-xs font-semibold text-slate-200 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-700"
                      >
                        Record Payment
                      </button>
                      <button
                        type="button"
                        disabled={refundFrozen}
                        onClick={() => {
                          setPaymentModalMode('refund');
                          setPaymentAmount(money.collected > 0 ? money.collected : 0);
                          setPaymentNote('');
                        }}
                        className="px-3 py-2 rounded-lg bg-rose-500/10 border border-rose-900/50 text-xs font-semibold text-rose-200 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-rose-500/20"
                      >
                        Record Refund
                      </button>
                    </div>
                  )}

                  {canRecordPayments && paymentModalMode === 'payment' && !paymentFrozen && money.balance > 0 && (
                    <form onSubmit={handleConfirmPayment} className="space-y-3">
                      <span className="text-[10px] font-bold uppercase tracking-wider text-[#E7C878] block">
                        New payment
                      </span>
                      <div className="grid grid-cols-2 gap-3">
                        <label className="block">
                          <span className="text-[10px] text-slate-400 block mb-1">Amount (PKR)</span>
                          <input
                            type="number"
                            min={1}
                            step={1}
                            max={money.balance}
                            value={paymentAmount}
                            onChange={(e) => setPaymentAmount(Number(e.target.value))}
                            className="w-full bg-[#07141E] text-white p-2 rounded-lg border border-slate-700 focus:border-[#E7C878] focus:outline-none text-xs font-mono"
                          />
                        </label>
                        <label className="block">
                          <span className="text-[10px] text-slate-400 block mb-1">Method</span>
                          <select
                            value={paymentMethod}
                            onChange={(e) => setPaymentMethod(e.target.value as PaymentEntry['method'])}
                            className="w-full bg-[#07141E] text-white p-2 rounded-lg border border-slate-700 focus:border-[#E7C878] focus:outline-none text-xs cursor-pointer"
                          >
                            <option>Bank Transfer</option>
                            <option>Pay Order</option>
                            <option>Cash / Cheque</option>
                            <option>Online Transfer</option>
                          </select>
                        </label>
                      </div>
                      <label className="block">
                        <span className="text-[10px] text-slate-400 block mb-1">Reference note (optional)</span>
                        <input
                          type="text"
                          value={paymentNote}
                          onChange={(e) => setPaymentNote(e.target.value)}
                          placeholder="e.g. Pay order #PO-10231 deposited"
                          className="w-full bg-[#07141E] text-white p-2 rounded-lg border border-slate-700 focus:border-[#E7C878] focus:outline-none text-xs"
                        />
                      </label>
                      <div className="flex justify-end">
                        <button
                          type="submit"
                          className="px-4 py-2 rounded-lg bg-[#E7C878] hover:bg-[#F3C464] text-[#0B1220] text-xs font-bold cursor-pointer transition shadow"
                        >
                          Record Payment
                        </button>
                      </div>
                    </form>
                  )}

                  {canRecordPayments && paymentModalMode === 'refund' && !refundFrozen && (
                    <form onSubmit={handleConfirmPayment} className="space-y-3">
                      <span className="text-[10px] font-bold uppercase tracking-wider text-rose-300 block">
                        Refund collected funds
                      </span>
                      <div className="grid grid-cols-2 gap-3">
                        <label className="block">
                          <span className="text-[10px] text-slate-400 block mb-1">Refund amount (PKR)</span>
                          <input
                            type="number"
                            min={1}
                            step={1}
                            max={money.collected}
                            value={paymentAmount}
                            onChange={(e) => setPaymentAmount(Number(e.target.value))}
                            className="w-full bg-[#07141E] text-white p-2 rounded-lg border border-rose-900/50 focus:border-rose-400 focus:outline-none text-xs font-mono"
                          />
                        </label>
                        <label className="block">
                          <span className="text-[10px] text-slate-400 block mb-1">Method</span>
                          <select
                            value={paymentMethod}
                            onChange={(e) => setPaymentMethod(e.target.value as PaymentEntry['method'])}
                            className="w-full bg-[#07141E] text-white p-2 rounded-lg border border-slate-700 focus:border-[#E7C878] focus:outline-none text-xs cursor-pointer"
                          >
                            <option>Bank Transfer</option>
                            <option>Pay Order</option>
                            <option>Cash / Cheque</option>
                            <option>Online Transfer</option>
                          </select>
                        </label>
                      </div>
                      <label className="block">
                        <span className="text-[10px] text-slate-400 block mb-1">Refund reason/reference (required)</span>
                        <input
                          type="text"
                          required
                          value={paymentNote}
                          onChange={(e) => setPaymentNote(e.target.value)}
                          placeholder="e.g. Cancellation refund approved, bank reference..."
                          className="w-full bg-[#07141E] text-white p-2 rounded-lg border border-rose-900/50 focus:border-rose-400 focus:outline-none text-xs"
                        />
                      </label>
                      <div className="flex justify-end">
                        <button
                          type="submit"
                          className="px-4 py-2 rounded-lg bg-rose-500/20 border border-rose-900/50 text-rose-100 text-xs font-bold cursor-pointer transition hover:bg-rose-500/30"
                        >
                          Record Refund
                        </button>
                      </div>
                    </form>
                  )}

                  {canRecordPayments && paymentModalMode === 'payment' && paymentFrozen && (
                    <div className="p-2.5 rounded-lg bg-[#07141E] border border-slate-700 text-[11px] text-slate-300">
                      This deal is {paymentModalTxn.voided ? 'voided' : 'cancelled'} — new payments are frozen. Previously collected funds can still be refunded.
                    </div>
                  )}
                  {canRecordPayments && paymentModalMode === 'refund' && refundFrozen && (
                    <div className="p-2.5 rounded-lg bg-[#07141E] border border-slate-700 text-[11px] text-slate-300">
                      {paymentModalTxn.voided ? 'Voided deals cannot be refunded.' : 'No net collected funds are available to refund.'}
                    </div>
                  )}
                  {!canRecordPayments && (
                    <div className="text-[11px] text-slate-500 italic">
                      View-only: recording payments requires manager/admin payment authority (your role has ledger visibility only).
                    </div>
                  )}
                </>
              );
            })()}
          </div>
        </div>
      )}

      {/* Void Modal (P1-1): super-admin only, mandatory reason, audit-preserved */}
      {voidModalTxn && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fadeIn">
          <div className="relative w-full max-w-md bg-[#0B1220] text-slate-100 border border-slate-700 rounded-2xl p-6 shadow-2xl">
            <h3 className="font-serif-luxury text-lg font-bold text-white mb-2">
              Void Deal {voidModalTxn.dealNumber || voidModalTxn.id}
            </h3>
            <p className="text-xs text-slate-400 mb-4">
              Voiding keeps the record with a full audit trail (who/when/why) instead of deleting it — totals exclude it
              immediately. Recorded payments freeze and any refund is handled manually outside this ledger.
            </p>
            <form onSubmit={handleConfirmVoid} className="space-y-3">
              <label className="block">
                <span className="text-[10px] text-slate-400 block mb-1">Void reason (required)</span>
                <textarea
                  rows={3}
                  value={voidReason}
                  onChange={(e) => setVoidReason(e.target.value)}
                  placeholder="e.g. Duplicate booking recorded in error; buyer withdrew before execution…"
                  className="w-full bg-[#07141E] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#E7C878] focus:outline-none text-xs"
                />
              </label>
              <div className="flex justify-end gap-3 pt-1">
                <button
                  type="button"
                  onClick={() => setVoidModalTxn(null)}
                  className="px-4 py-2 rounded-lg bg-slate-800 text-slate-300 text-xs font-semibold cursor-pointer hover:bg-slate-700"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-white border border-slate-700 text-xs font-bold cursor-pointer transition"
                >
                  Void Deal
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Cancel-reason Modal: cancelling a deal needs a reason (domain rule) */}
      {cancelModalTxnId && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fadeIn">
          <div className="relative w-full max-w-md bg-[#0B1220] text-slate-100 border border-amber-500/40 rounded-2xl p-6 shadow-2xl">
            <h3 className="font-serif-luxury text-lg font-bold text-white mb-2">Cancel Deal</h3>
            <p className="text-xs text-slate-400 mb-4">
              A cancellation reason is mandatory — it is stored on the deal record and cancels any unaccrued commission payable.
            </p>
            <form onSubmit={handleConfirmCancel} className="space-y-3">
              <label className="block">
                <span className="text-[10px] text-slate-400 block mb-1">Reason (required)</span>
                <textarea
                  rows={3}
                  value={cancelReason}
                  onChange={(e) => setCancelReason(e.target.value)}
                  placeholder="e.g. Buyer's financing fell through after token stage…"
                  className="w-full bg-[#07141E] text-white p-2.5 rounded-lg border border-slate-700 focus:border-amber-500 focus:outline-none text-xs"
                />
              </label>
              <div className="flex justify-end gap-3 pt-1">
                <button
                  type="button"
                  onClick={() => setCancelModalTxnId(null)}
                  className="px-4 py-2 rounded-lg bg-slate-800 text-slate-300 text-xs font-semibold cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 rounded-lg bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold cursor-pointer"
                >
                  Confirm Cancellation
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Add New Transaction Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fadeIn">
          <div className="relative w-full max-w-xl bg-[#0B1220] border border-[#E7C878]/40 rounded-2xl p-6 sm:p-8 shadow-2xl max-h-[90vh] overflow-y-auto">
            <h3 className="font-serif-luxury text-xl font-bold text-white mb-1">
              Record Real Estate Transaction
            </h3>
            <p className="text-xs text-slate-400 mb-6">
              Create a legally-structured deal between verified seller and buyer.
            </p>

            <form onSubmit={handleSubmit} className="space-y-4 text-xs">
              <div>
                <label className="block text-slate-300 font-semibold mb-1">Select Property / Plot *</label>
                <select
                  value={formData.propertyId}
                  onChange={(e) => handlePropertyChange(e.target.value)}
                  className="w-full bg-[#07141E] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#E7C878] focus:outline-none"
                >
                  {properties.map((p) => (
                    <option key={p.id} value={p.id}>
                      {p.plotNumber ? `Plot ${p.plotNumber}` : p.title} ({p.sector}) - {formatPKRCurrency(p.pricePKR)}
                    </option>
                  ))}
                </select>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-slate-300 font-semibold mb-1">Seller (Property Owner) *</label>
                  <select
                    value={formData.sellerId}
                    onChange={(e) => setFormData({ ...formData, sellerId: e.target.value })}
                    className="w-full bg-[#07141E] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#E7C878] focus:outline-none"
                  >
                    {(sellers || []).map((s) => (
                      <option key={s.id} value={s.id}>{s.fullName}</option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block text-slate-300 font-semibold mb-1">Buyer (Purchaser) *</label>
                  <select
                    value={formData.buyerId}
                    onChange={(e) => setFormData({ ...formData, buyerId: e.target.value })}
                    className="w-full bg-[#07141E] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#E7C878] focus:outline-none"
                  >
                    {(buyers || []).map((b) => (
                      <option key={b.id} value={b.id}>{b.fullName}</option>
                    ))}
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-slate-300 font-semibold mb-1">Agreed Deal Amount (PKR) *</label>
                  <input
                    type="number"
                    min={1}
                    step={1}
                    required
                    value={formData.dealAmountPKR}
                    onChange={(e) => setFormData({ ...formData, dealAmountPKR: Number(e.target.value) })}
                    className="w-full bg-[#07141E] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#E7C878] focus:outline-none font-mono text-sm"
                  />
                </div>

                <div>
                  <label className="block text-slate-300 font-semibold mb-1">Advance / Bayana Deposit (PKR) *</label>
                  <input
                    type="number"
                    min={0}
                    step={1}
                    required
                    value={formData.advancePKR}
                    onChange={(e) => setFormData({ ...formData, advancePKR: Number(e.target.value) })}
                    className="w-full bg-[#07141E] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#E7C878] focus:outline-none font-mono text-sm"
                  />
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-slate-300 font-semibold mb-1">Facilitating Agent / Dealer</label>
                  <select
                    value={formData.dealerId}
                    onChange={(e) => setFormData({ ...formData, dealerId: e.target.value })}
                    className="w-full bg-[#07141E] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#E7C878] focus:outline-none"
                  >
                    {(dealers || []).map((d) => (
                      <option key={d.id} value={d.id}>{d.fullName} ({d.agencyOrBranch})</option>
                    ))}
                    <option value="">— Direct deal (no dealer) —</option>
                  </select>
                </div>

                <div>
                  <label className="block text-slate-300 font-semibold mb-1">Deal Status</label>
                  <select
                    value={formData.status}
                    onChange={(e) => setFormData({ ...formData, status: e.target.value as TransactionStatus })}
                    className="w-full bg-[#07141E] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#E7C878] focus:outline-none"
                  >
                    <option value="Draft">Draft Agreement</option>
                    <option value="Token Paid">Token Paid</option>
                    <option value="Advance Received">Advance Received</option>
                    <option value="Under Transfer">Under Transfer at MDA</option>
                    <option value="Completed">Completed</option>
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-slate-300 font-semibold mb-1">Contract Booking Date</label>
                  <input
                    type="date"
                    value={formData.dealDate}
                    onChange={(e) => setFormData({ ...formData, dealDate: e.target.value })}
                    className="w-full bg-[#07141E] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#E7C878] focus:outline-none"
                  />
                </div>
                <div>
                  <label className="block text-slate-300 font-semibold mb-1">Expected Possession Date</label>
                  <input
                    type="date"
                    value={formData.expectedPossessionDate}
                    onChange={(e) => setFormData({ ...formData, expectedPossessionDate: e.target.value })}
                    className="w-full bg-[#07141E] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#E7C878] focus:outline-none"
                  />
                </div>
              </div>

              <div>
                <label className="block text-slate-300 font-semibold mb-1">Terms & Handwritten Clauses</label>
                <textarea
                  rows={2}
                  placeholder="e.g. Plot R-66, 100ft road facing, 24x45 dimension, 20,000 commission agreed..."
                  value={formData.notes}
                  onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                  className="w-full bg-[#07141E] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#E7C878] focus:outline-none"
                />
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
                  className="px-5 py-2 rounded-lg bg-[#E7C878] hover:bg-[#F3C464] text-[#0B1220] font-bold cursor-pointer shadow"
                >
                  Execute Deal Entry
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
