import React, { useState } from 'react';
import { useApp } from '../../context/AppContext';
import {
  UserCheck,
  Plus,
  Edit2,
  Trash2,
  Phone,
  CreditCard,
  MapPin,
  Search,
  Eye,
  EyeOff,
  Coins,
  CheckCircle2,
  Clock,
  Sparkles,
  ArrowRight
} from 'lucide-react';
import { Buyer, BuyerPaymentStatus } from '../../types';
import { authorize } from '../../domain/permissions';
import { maskCNIC, formatPKRCurrency } from '../../utils/logic';

export const AdminBuyers: React.FC = () => {
  const { buyers, addBuyer, updateBuyer, deleteBuyer, properties, dealers, showToast, setSelectedPropertyId, setCurrentView, currentUser, confirmAction } = useApp();

  const canViewSensitive = authorize(currentUser, 'view_sensitive_cnic').ok;

  const [searchQuery, setSearchQuery] = useState('');
  const [filterStatus, setFilterStatus] = useState<string>('all');
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingBuyerId, setEditingBuyerId] = useState<string | null>(null);
  const [unmaskedCnicIds, setUnmaskedCnicIds] = useState<Record<string, boolean>>({});

  const [formData, setFormData] = useState({
    fullName: '',
    cnic: '',
    phone: '',
    budgetPKR: 4000000,
    interestedSector: 'Sector 63',
    interestedPlotSize: '120 Sq Yds',
    paymentStatus: 'Advance Paid' as BuyerPaymentStatus,
    advancePaidPKR: 800000,
    assignedDealerId: 'dealer-1',
    notes: ''
  });

  const toggleCnicMask = (id: string) => {
    setUnmaskedCnicIds((prev) => ({ ...prev, [id]: !prev[id] }));
  };

  const filteredBuyers = (buyers || []).filter((b) => {
    if (b.deletedAt) return false;
    const matchesSearch =
      b.fullName.toLowerCase().includes(searchQuery.toLowerCase()) ||
      (b.cnic || b.cnicMasked || '').includes(searchQuery) ||
      b.phone.includes(searchQuery) ||
      b.interestedSector.toLowerCase().includes(searchQuery.toLowerCase());

    const matchesStatus = filterStatus === 'all' || b.paymentStatus === filterStatus;
    return matchesSearch && matchesStatus;
  });

  const handleOpenAdd = () => {
    setEditingBuyerId(null);
    setFormData({
      fullName: '',
      cnic: '',
      phone: '',
      budgetPKR: 4000000,
      interestedSector: 'Sector 63',
      interestedPlotSize: '120 Sq Yds',
      paymentStatus: 'Advance Paid',
      advancePaidPKR: 0,
      assignedDealerId: dealers[0]?.id || '',
      notes: ''
    });
    setIsModalOpen(true);
  };

  const handleOpenEdit = (buyer: Buyer) => {
    setEditingBuyerId(buyer.id);
    setFormData({
      fullName: buyer.fullName,
      cnic: buyer.cnic || buyer.cnicMasked,
      phone: buyer.phone,
      budgetPKR: buyer.budgetPKR || 4000000,
      interestedSector: buyer.interestedSector,
      interestedPlotSize: buyer.interestedPlotSize || '120 Sq Yds',
      paymentStatus: buyer.paymentStatus,
      advancePaidPKR: buyer.advancePaidPKR || 0,
      assignedDealerId: buyer.assignedDealerId || '',
      notes: buyer.notes || ''
    });
    setIsModalOpen(true);
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.fullName.trim() || !formData.cnic.trim()) {
      showToast('error', 'Please fill in buyer name and CNIC.');
      return;
    }

    if (editingBuyerId) {
      updateBuyer(editingBuyerId, {
        fullName: formData.fullName,
        cnic: formData.cnic,
        cnicMasked: maskCNIC(formData.cnic),
        phone: formData.phone,
        whatsApp: formData.phone,
        address: 'Karachi',
        budgetPKR: Number(formData.budgetPKR),
        interestedSector: formData.interestedSector,
        interestedPlotSize: formData.interestedPlotSize,
        paymentStatus: formData.paymentStatus,
        advancePaidPKR: Number(formData.advancePaidPKR),
        assignedDealerId: formData.assignedDealerId,
        notes: formData.notes
      });
      showToast('success', 'Buyer portfolio updated.');
    } else {
      addBuyer({
        fullName: formData.fullName,
        cnic: formData.cnic,
        cnicMasked: maskCNIC(formData.cnic),
        phone: formData.phone,
        whatsApp: formData.phone,
        address: 'Karachi',
        budgetPKR: Number(formData.budgetPKR),
        interestedSector: formData.interestedSector,
        interestedPlotSize: formData.interestedPlotSize,
        paymentStatus: formData.paymentStatus,
        advancePaidPKR: Number(formData.advancePaidPKR),
        assignedDealerId: formData.assignedDealerId,
        notes: formData.notes,
        interestedProperties: [],
        interestedPropertyIds: []
      });
      showToast('success', 'New buyer lead registered.');
    }
    setIsModalOpen(false);
  };

  const handleDelete = async (id: string, name: string) => {
    const ok = await confirmAction(`Delete buyer record "${name}"?`, {
      title: 'Delete buyer record',
      confirmLabel: 'Delete'
    });
    if (ok) {
      deleteBuyer(id);
      showToast('info', 'Buyer record removed.');
    }
  };

  return (
    <div className="space-y-6 animate-fadeIn">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-6 rounded-2xl bg-gradient-to-r from-[#0B1220] to-[#1A2638] border border-[#E7C878]/30 shadow-xl">
        <div>
          <div className="flex items-center gap-2">
            <h2 className="font-serif-luxury text-2xl font-bold text-white">
              Buyer CRM & Deal Pipeline
            </h2>
            <span className="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-[#E7C878]/20 text-[#E7C878] border border-[#E7C878]/40">
              Investors & Allottees
            </span>
          </div>
          <p className="text-xs text-slate-300 mt-1">
            Tracking qualified buyers, budget caps, sector preferences, down payments, and assigned closing agents.
          </p>
        </div>

        <button
          onClick={handleOpenAdd}
          className="px-4 py-2.5 rounded-xl bg-[#E7C878] hover:bg-[#F3C464] text-[#0B1220] font-bold text-xs flex items-center justify-center gap-2 cursor-pointer shadow-lg shadow-[#E7C878]/20"
        >
          <Plus className="w-4 h-4" />
          <span>Register New Buyer</span>
        </button>
      </div>

      {/* Filter and Search */}
      <div className="flex flex-col md:flex-row items-center justify-between gap-4 bg-[#0B1220] p-4 rounded-xl border border-slate-800">
        <div className="relative w-full md:w-80">
          <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            placeholder="Search buyer by name, CNIC, sector..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="w-full bg-[#07141E] text-white text-xs pl-9 pr-4 py-2.5 rounded-lg border border-slate-700 focus:border-[#E7C878] focus:outline-none"
          />
        </div>

        <div className="flex items-center gap-2 w-full md:w-auto overflow-x-auto">
          {['all', 'Advance Paid', 'Fully Paid', 'Pending Installment'].map((status) => (
            <button
              key={status}
              onClick={() => setFilterStatus(status)}
              className={`px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap cursor-pointer transition ${
                filterStatus === status
                  ? 'bg-[#E7C878] text-[#0B1220]'
                  : 'bg-[#07141E] text-slate-400 hover:text-white border border-slate-800'
              }`}
            >
              {status === 'all' ? 'All Buyers' : status}
            </button>
          ))}
        </div>
      </div>

      {/* Buyers Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {filteredBuyers.map((buyer) => {
          const isUnmasked = !!unmaskedCnicIds[buyer.id];
          const assignedDealer = dealers.find((d) => d.id === buyer.assignedDealerId);

          // Matching properties in inventory within budget
          const matchingProperties = properties.filter(
            (p) =>
              (p.sector || '').toLowerCase().includes((buyer.interestedSector || '').toLowerCase()) &&
              p.pricePKR <= (buyer.budgetPKR || 4500000) * 1.15
          );

          return (
            <div
              key={buyer.id}
              className="bg-[#0B1220] rounded-2xl border border-slate-800 p-6 flex flex-col justify-between hover:border-[#E7C878]/50 transition-all shadow-lg"
            >
              <div className="space-y-4">
                <div className="flex items-start justify-between">
                  <div className="flex items-center gap-3">
                    <div className="w-11 h-11 rounded-xl bg-gradient-to-br from-[#E7C878]/20 to-[#0B1220] border border-[#E7C878]/40 flex items-center justify-center font-serif-luxury font-bold text-[#F6E3B4] text-lg shadow">
                      {buyer.fullName.charAt(0)}
                    </div>
                    <div>
                      <h3 className="font-bold text-white text-sm">
                        {buyer.fullName}
                      </h3>
                      <span className="text-[11px] text-[#E7C878] font-mono">
                        {buyer.interestedSector} • {buyer.interestedPlotSize || '120 Sq Yds'}
                      </span>
                    </div>
                  </div>
                  <div className="flex items-center gap-1.5">
                    <button
                      onClick={() => handleOpenEdit(buyer)}
                      className="p-1.5 rounded-lg bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 transition cursor-pointer"
                      title="Edit Buyer"
                    >
                      <Edit2 className="w-3.5 h-3.5" />
                    </button>
                    <button
                      onClick={() => handleDelete(buyer.id, buyer.fullName)}
                      className="p-1.5 rounded-lg bg-slate-800 text-slate-400 hover:text-white hover:bg-slate-700 transition cursor-pointer"
                      title="Delete Buyer"
                    >
                      <Trash2 className="w-3.5 h-3.5" />
                    </button>
                  </div>
                </div>

                {/* Status Badge */}
                <div className="flex items-center justify-between">
                  <span
                    className={`px-2.5 py-1 rounded-full text-[10px] font-bold flex items-center gap-1.5 ${
                      buyer.paymentStatus === 'Fully Paid'
                        ? 'bg-[#E7C878]/20 text-[#F6E3B4] border border-[#E7C878]/40'
                        : buyer.paymentStatus === 'Advance Paid'
                        ? 'bg-[#E7C878]/15 text-[#F6E3B4] border border-[#E7C878]/30'
                        : 'bg-slate-800 text-slate-300 border border-slate-700'
                    }`}
                  >
                    {buyer.paymentStatus === 'Fully Paid' && <CheckCircle2 className="w-3 h-3 text-[#E7C878]" />}
                    {buyer.paymentStatus === 'Advance Paid' && <Clock className="w-3 h-3 text-[#E7C878]" />}
                    {buyer.paymentStatus}
                  </span>

                  <span className="text-[11px] text-slate-400">
                    Advance: <strong className="text-white font-mono">{formatPKRCurrency(buyer.advancePaidPKR || 0)}</strong>
                  </span>
                </div>

                {/* Details */}
                <div className="space-y-2 text-xs pt-2 border-t border-slate-800/80">
                  <div className="flex items-center justify-between bg-[#07141E] p-2 rounded-lg">
                    <span className="text-slate-400 flex items-center gap-1.5">
                      <CreditCard className="w-3.5 h-3.5 text-slate-500" />
                      CNIC:
                    </span>
                    <div className="flex items-center gap-2">
                      <span className="font-mono text-white font-medium">
                        {canViewSensitive
                          ? (isUnmasked ? (buyer.cnic ?? buyer.cnicMasked ?? 'N/A') : (buyer.cnicMasked || maskCNIC(buyer.cnic ?? '')))
                          : (buyer.cnicMasked || maskCNIC(buyer.cnic ?? ''))}
                      </span>
                      {hasPermission(currentUser.role, 'view_sensitive_cnic') && (
                        <button
                          type="button"
                          onClick={() => toggleCnicMask(buyer.id)}
                          className="text-slate-400 hover:text-[#E7C878] cursor-pointer"
                          title={isUnmasked ? 'Mask' : 'Reveal'}
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
                    <span className="font-mono">{buyer.phone}</span>
                  </div>

                  <div className="flex items-center justify-between text-slate-300 px-1">
                    <span className="text-slate-400 flex items-center gap-1.5">
                      <Coins className="w-3.5 h-3.5 text-[#E7C878]" />
                      Max Budget:
                    </span>
                    <span className="font-bold text-white font-mono">
                      {formatPKRCurrency(buyer.budgetPKR || 0)}
                    </span>
                  </div>

                  {assignedDealer && (
                    <div className="flex items-center justify-between text-slate-300 px-1 pt-1">
                      <span className="text-slate-400">Assigned Agent:</span>
                      <span className="text-[#F6E3B4] font-semibold">{assignedDealer.fullName}</span>
                    </div>
                  )}

                  {buyer.notes && (
                    <div className="p-2 rounded bg-slate-900/60 text-[11px] text-slate-300 border border-slate-800">
                      {buyer.notes}
                    </div>
                  )}
                </div>

                {/* Inventory Match Spotlight */}
                <div className="pt-2">
                  <div className="text-[10px] font-bold text-[#E7C878] uppercase tracking-wider mb-1.5 flex items-center gap-1">
                    <Sparkles className="w-3 h-3 text-[#E7C878]" />
                    <span>Matching Plots in Agency Inventory ({matchingProperties.length})</span>
                  </div>
                  {matchingProperties.length > 0 ? (
                    <div className="space-y-1">
                      {matchingProperties.slice(0, 2).map((mp) => (
                        <div
                          key={mp.id}
                          className="p-1.5 rounded bg-[#07141E] border border-slate-800/80 text-[10px] flex items-center justify-between"
                        >
                          <span className="text-white font-medium truncate max-w-[170px]">
                            {mp.plotNumber ? `Plot ${mp.plotNumber} (${mp.sector})` : mp.title}
                          </span>
                          <span className="text-[#E7C878] font-mono font-bold">
                            {formatPKRCurrency(mp.pricePKR)}
                          </span>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <span className="text-[10px] text-slate-500 italic">No exact inventory price match.</span>
                  )}
                </div>
              </div>

              {/* Footer */}
              <div className="pt-3 mt-4 border-t border-slate-800 flex items-center justify-between text-[11px] text-slate-400">
                <span>Preference: {buyer.interestedPlotSize}</span>
                <span className="text-[#E7C878] font-semibold">Active Deal Lead</span>
              </div>
            </div>
          );
        })}
      </div>

      {/* Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fadeIn">
          <div className="relative w-full max-w-lg bg-[#0B1220] border border-[#E7C878]/40 rounded-2xl p-6 sm:p-8 shadow-2xl max-h-[90vh] overflow-y-auto">
            <h3 className="font-serif-luxury text-xl font-bold text-white mb-1">
              {editingBuyerId ? 'Edit Buyer Profile' : 'Register New Buyer Prospect'}
            </h3>
            <p className="text-xs text-slate-400 mb-6">
              Record investor requirements and down-payment advances.
            </p>

            <form onSubmit={handleSubmit} className="space-y-4 text-xs">
              <div>
                <label className="block text-slate-300 font-semibold mb-1">Buyer Full Name *</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Muhammad Usman"
                  value={formData.fullName}
                  onChange={(e) => setFormData({ ...formData, fullName: e.target.value })}
                  className="w-full bg-[#07141E] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#E7C878] focus:outline-none"
                />
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-slate-300 font-semibold mb-1">CNIC Number *</label>
                  <input
                    type="text"
                    required
                    placeholder="42201-1234567-1"
                    value={formData.cnic}
                    onChange={(e) => setFormData({ ...formData, cnic: e.target.value })}
                    className="w-full bg-[#07141E] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#E7C878] focus:outline-none font-mono"
                  />
                </div>
                <div>
                  <label className="block text-slate-300 font-semibold mb-1">Phone / WhatsApp *</label>
                  <input
                    type="text"
                    required
                    placeholder="0312-3456789"
                    value={formData.phone}
                    onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                    className="w-full bg-[#07141E] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#E7C878] focus:outline-none font-mono"
                  />
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-slate-300 font-semibold mb-1">Budget in PKR *</label>
                  <input
                    type="number"
                    required
                    value={formData.budgetPKR}
                    onChange={(e) => setFormData({ ...formData, budgetPKR: Number(e.target.value) })}
                    className="w-full bg-[#07141E] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#E7C878] focus:outline-none font-mono"
                  />
                </div>
                <div>
                  <label className="block text-slate-300 font-semibold mb-1">Advance Deposit (PKR)</label>
                  <input
                    type="number"
                    value={formData.advancePaidPKR}
                    onChange={(e) => setFormData({ ...formData, advancePaidPKR: Number(e.target.value) })}
                    className="w-full bg-[#07141E] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#E7C878] focus:outline-none font-mono"
                  />
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-slate-300 font-semibold mb-1">Target Sector</label>
                  <select
                    value={formData.interestedSector}
                    onChange={(e) => setFormData({ ...formData, interestedSector: e.target.value })}
                    className="w-full bg-[#07141E] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#E7C878] focus:outline-none"
                  >
                    <option value="Sector 63">Sector 63 (Scheme 45)</option>
                    <option value="Sector 70-D">Sector 70-D</option>
                    <option value="Sector 71">Sector 71</option>
                    <option value="Sector 74">Sector 74</option>
                    <option value="MDA Homes">MDA Homes Project</option>
                  </select>
                </div>
                <div>
                  <label className="block text-slate-300 font-semibold mb-1">Plot Size Preference</label>
                  <select
                    value={formData.interestedPlotSize}
                    onChange={(e) => setFormData({ ...formData, interestedPlotSize: e.target.value })}
                    className="w-full bg-[#07141E] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#E7C878] focus:outline-none"
                  >
                    <option value="80 Sq Yds">80 Sq Yds</option>
                    <option value="120 Sq Yds">120 Sq Yds (Colloquial 120-M)</option>
                    <option value="240 Sq Yds">240 Sq Yds</option>
                    <option value="400 Sq Yds">400 Sq Yds</option>
                    <option value="Commercial 100 Sq Yds">Commercial 100 Sq Yds</option>
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-slate-300 font-semibold mb-1">Payment Status</label>
                  <select
                    value={formData.paymentStatus}
                    onChange={(e) => setFormData({ ...formData, paymentStatus: e.target.value as BuyerPaymentStatus })}
                    className="w-full bg-[#07141E] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#E7C878] focus:outline-none"
                  >
                    <option value="Advance Paid">Advance Paid</option>
                    <option value="Fully Paid">Fully Paid</option>
                    <option value="Pending Installment">Pending Installment</option>
                  </select>
                </div>
                <div>
                  <label className="block text-slate-300 font-semibold mb-1">Assigned Agent</label>
                  <select
                    value={formData.assignedDealerId}
                    onChange={(e) => setFormData({ ...formData, assignedDealerId: e.target.value })}
                    className="w-full bg-[#07141E] text-white p-2.5 rounded-lg border border-slate-700 focus:border-[#E7C878] focus:outline-none"
                  >
                    {dealers.map((d) => (
                      <option key={d.id} value={d.id}>{d.fullName} ({d.agencyOrBranch})</option>
                    ))}
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-slate-300 font-semibold mb-1">Notes</label>
                <textarea
                  rows={2}
                  placeholder="Special terms, payment schedule, road-facing requirement..."
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
                  {editingBuyerId ? 'Save Changes' : 'Save Buyer'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
