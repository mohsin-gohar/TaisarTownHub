import React, { useState } from 'react';
import { useApp } from '../../context/AppContext';
import {
  Home,
  Plus,
  Edit2,
  Trash2,
  Check,
  X,
  MapPin,
  Maximize2,
  Star,
  ExternalLink,
  Flame
} from 'lucide-react';
import { Property, PropertyType, PropertyStatus } from '../../types';
import { formatPKRCurrency, isDuplicatePlot, hasPermission } from '../../utils/logic';

export const AdminProperties: React.FC = () => {
  const {
    properties,
    addProperty,
    updateProperty,
    deleteProperty,
    sellers,
    dealers,
    setSelectedPropertyId,
    setCurrentView,
    showToast,
    activeRole,
    confirmAction
  } = useApp();

  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingPropId, setEditingPropId] = useState<string | null>(null);

  const [formData, setFormData] = useState({
    title: '',
    description: '',
    pricePKR: 3500000,
    privatePurchasePricePKR: 3000000,
    location: 'Sector 63, Taiser Town Scheme 45, Karachi',
    sector: 'Sector 63',
    block: 'Block A',
    plotNumber: 'R-66',
    fileNumber: 'TT-63-A-066',
    sizeSqYards: 120,
    frontMeasurementFt: 24,
    depthMeasurementFt: 45,
    roadWidthFt: 100,
    is100ftRoadFacing: true,
    type: 'Residential Plot' as PropertyType,
    status: 'Available' as PropertyStatus,
    isFeatured: true,
    possessionReady: true,
    westOpen: true,
    cornerPlot: false,
    parkFacing: false,
    sellerId: sellers[0]?.id || '',
    assignedDealerId: dealers[0]?.id || '',
    image: 'https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&fit=crop&w=1000&q=80',
    features: 'West Open, 100ft Main Boulevard, 24x45 Dimension, MDA Verified File, Ready for Possession'
  });

  const handleOpenAdd = () => {
    setEditingPropId(null);
    setFormData({
      title: '',
      description: '',
      pricePKR: 3800000,
      privatePurchasePricePKR: 3200000,
      location: 'Sector 63, Taiser Town Scheme 45, Karachi',
      sector: 'Sector 63',
      block: 'Block A',
      plotNumber: '',
      fileNumber: '',
      sizeSqYards: 120,
      frontMeasurementFt: 24,
      depthMeasurementFt: 45,
      roadWidthFt: 100,
      is100ftRoadFacing: false,
      type: 'Residential Plot',
      status: 'Available',
      isFeatured: true,
      possessionReady: true,
      westOpen: true,
      cornerPlot: false,
      parkFacing: false,
      sellerId: sellers[0]?.id || '',
      assignedDealerId: dealers[0]?.id || '',
      image: 'https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&fit=crop&w=1000&q=80',
      features: 'West Open, 100ft Main Boulevard, MDA Verified File, Ready for Construction'
    });
    setIsModalOpen(true);
  };

  const handleOpenEdit = (prop: Property) => {
    setEditingPropId(prop.id);
    setFormData({
      title: prop.title,
      description: prop.description,
      pricePKR: prop.pricePKR,
      privatePurchasePricePKR: prop.privatePurchasePricePKR || 0,
      location: prop.location,
      sector: prop.sector,
      block: prop.block || 'Block A',
      plotNumber: prop.plotNumber || '',
      fileNumber: prop.fileNumber || '',
      sizeSqYards: prop.sizeSqYards,
      frontMeasurementFt: prop.frontMeasurementFt || 24,
      depthMeasurementFt: prop.depthMeasurementFt || 45,
      roadWidthFt: prop.roadWidthFt || 40,
      is100ftRoadFacing: !!prop.is100ftRoadFacing,
      type: prop.type,
      status: prop.status,
      isFeatured: prop.isFeatured,
      possessionReady: prop.possessionReady,
      westOpen: !!prop.westOpen,
      cornerPlot: !!prop.cornerPlot,
      parkFacing: !!prop.parkFacing,
      sellerId: prop.sellerId || '',
      assignedDealerId: prop.assignedDealerId || '',
      image: prop.images[0] || '',
      features: prop.features.join(', ')
    });
    setIsModalOpen(true);
  };

  const handleSave = (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.title.trim()) {
      showToast('error', 'Please enter property title.');
      return;
    }

    // Check duplicate plot in same sector
    if (formData.plotNumber) {
      const isDuplicate = isDuplicatePlot(
        properties,
        formData.plotNumber,
        formData.sector,
        formData.block,
        editingPropId || undefined
      );
      if (isDuplicate) {
        showToast('error', `Duplicate Plot: Plot ${formData.plotNumber} already registered in ${formData.sector}.`);
        return;
      }
    }

    const featureList = formData.features.split(',').map((f) => f.trim()).filter(Boolean);
    const formattedPrice = formatPKRCurrency(Number(formData.pricePKR));

    const selectedDealer = dealers.find((d) => d.id === formData.assignedDealerId);
    const assignedContactPerson = selectedDealer?.fullName || 'Authorized Consultant';
    const assignedContactPhone = selectedDealer?.phone || '+92 321 8292831';
    const assignedContactWhatsApp = selectedDealer?.whatsApp || selectedDealer?.phone || '+92 321 8292831';
    const assignedAgencyName = selectedDealer?.agencyOrBranch || selectedDealer?.agencyAffiliation || 'TaisarTownHub Premier Advisory';

    if (editingPropId) {
      const result = updateProperty(editingPropId, {
        title: formData.title,
        description: formData.description,
        pricePKR: Number(formData.pricePKR),
        privatePurchasePricePKR: Number(formData.privatePurchasePricePKR),
        formattedPrice,
        location: formData.location,
        sector: formData.sector,
        block: formData.block,
        plotNumber: formData.plotNumber,
        fileNumber: formData.fileNumber,
        sizeSqYards: Number(formData.sizeSqYards),
        frontMeasurementFt: Number(formData.frontMeasurementFt),
        depthMeasurementFt: Number(formData.depthMeasurementFt),
        roadWidthFt: Number(formData.roadWidthFt),
        is100ftRoadFacing: formData.is100ftRoadFacing,
        type: formData.type,
        status: formData.status,
        isFeatured: formData.isFeatured,
        possessionReady: formData.possessionReady,
        westOpen: formData.westOpen,
        cornerPlot: formData.cornerPlot,
        parkFacing: formData.parkFacing,
        sellerId: formData.sellerId,
        assignedDealerId: formData.assignedDealerId,
        contactPerson: assignedContactPerson,
        contactPhone: assignedContactPhone,
        contactWhatsApp: assignedContactWhatsApp,
        agencyName: assignedAgencyName,
        images: [formData.image],
        features: featureList,
      });
      if (!result.success) {
        // updateProperty already surfaced the specific error (duplicate / permission)
        return;
      }
      showToast('success', 'Property listing updated.');
    } else {
      const result = addProperty({
        title: formData.title,
        description: formData.description,
        pricePKR: Number(formData.pricePKR),
        privatePurchasePricePKR: Number(formData.privatePurchasePricePKR),
        formattedPrice,
        location: formData.location,
        sector: formData.sector,
        block: formData.block,
        plotNumber: formData.plotNumber,
        fileNumber: formData.fileNumber,
        sizeSqYards: Number(formData.sizeSqYards),
        frontMeasurementFt: Number(formData.frontMeasurementFt),
        depthMeasurementFt: Number(formData.depthMeasurementFt),
        roadWidthFt: Number(formData.roadWidthFt),
        is100ftRoadFacing: formData.is100ftRoadFacing,
        type: formData.type,
        status: formData.status,
        isFeatured: formData.isFeatured,
        possessionReady: formData.possessionReady,
        westOpen: formData.westOpen,
        cornerPlot: formData.cornerPlot,
        parkFacing: formData.parkFacing,
        sellerId: formData.sellerId,
        assignedDealerId: formData.assignedDealerId,
        agencyName: assignedAgencyName,
        images: [formData.image],
        features: featureList,
        mdaApproved: true,
        contactPerson: assignedContactPerson,
        contactPhone: assignedContactPhone,
        contactWhatsApp: assignedContactWhatsApp
      });
      if (!result.success) {
        // addProperty already surfaced the specific error (duplicate / permission)
        return;
      }
      showToast('success', 'New property registered in agency inventory.');
    }

    setIsModalOpen(false);
  };

  const handleDelete = async (id: string, title: string) => {
    const ok = await confirmAction(`Are you sure you want to delete listing "${title}"?`, {
      title: 'Delete listing',
      confirmLabel: 'Delete'
    });
    if (ok) {
      deleteProperty(id);
    }
  };

  return (
    <div className="space-y-6 animate-fadeIn">
      {/* Header bar */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-6 rounded-2xl bg-[#101C2C] border border-slate-800 shadow-xl">
        <div>
          <h2 className="font-serif-luxury text-2xl font-bold text-white">
            Property Listings Management
          </h2>
          <p className="text-xs text-slate-400">
            Full control over plot allotments, commercial avenues, duplex villas, and pricing.
          </p>
        </div>
        <button
          onClick={handleOpenAdd}
          className="px-4 py-2.5 rounded-xl bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C] font-bold text-xs flex items-center gap-2 cursor-pointer shadow-lg shadow-[#C5A46D]/20 self-start sm:self-auto"
        >
          <Plus className="w-4 h-4" />
          <span>Add Property Listing</span>
        </button>
      </div>

      {/* Table */}
      <div className="bg-[#101C2C] rounded-2xl border border-slate-800 overflow-hidden shadow-xl">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-[#0B1220] text-slate-400 uppercase tracking-wider text-[10px]">
              <tr>
                <th className="py-3 px-4">Plot & Listing</th>
                <th className="py-3 px-4">Sector & Block</th>
                <th className="py-3 px-4">Dimensions</th>
                <th className="py-3 px-4">List Price (PKR)</th>
                <th className="py-3 px-4">Road / Features</th>
                <th className="py-3 px-4">Status</th>
                <th className="py-3 px-4">Featured</th>
                <th className="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800">
              {properties.filter((p) => !p.deletedAt).map((prop) => (
                <tr key={prop.id} className="hover:bg-slate-800/40 transition-colors">
                  <td className="py-3.5 px-4">
                    <div className="flex items-center gap-3">
                      <img
                        src={prop.images[0]}
                        alt={prop.title}
                        className="w-12 h-10 rounded-lg object-cover border border-slate-700"
                      />
                      <div>
                        <div className="flex items-center gap-2">
                          <span className="font-bold text-white text-sm">
                            {prop.plotNumber ? `Plot ${prop.plotNumber}` : prop.title}
                          </span>
                          {prop.plotNumber === 'R-66' && (
                            <span className="px-1.5 py-0.5 rounded text-[9px] font-bold bg-[#C5A46D] text-[#101C2C]">
                              Handwritten
                            </span>
                          )}
                        </div>
                        <span className="text-[11px] text-[#C5A46D] block">
                          {prop.fileNumber ? `File: ${prop.fileNumber}` : prop.type}
                        </span>
                      </div>
                    </div>
                  </td>
                  <td className="py-3.5 px-4 text-slate-300">
                    <span className="font-medium text-white block">{prop.sector}</span>
                    <span className="text-[10px] text-slate-400">{prop.block || 'Scheme 45'}</span>
                  </td>
                  <td className="py-3.5 px-4 text-slate-300">
                    <span className="font-bold text-white block">{prop.sizeSqYards} Sq. Yd</span>
                    {prop.frontMeasurementFt && prop.depthMeasurementFt && (
                      <span className="text-[10px] text-[#C5A46D] font-mono">
                        {prop.frontMeasurementFt}&apos; × {prop.depthMeasurementFt}&apos;
                      </span>
                    )}
                  </td>
                  <td className="py-3.5 px-4 font-serif-luxury font-bold text-[#F9E7BA]">
                    <div>{prop.formattedPrice}</div>
                    {hasPermission(activeRole, 'view_sensitive_cnic') && prop.privatePurchasePricePKR && (
                      <div className="text-[10px] text-amber-400/80 font-mono font-normal">
                        Cost: {formatPKRCurrency(prop.privatePurchasePricePKR)}
                      </div>
                    )}
                  </td>
                  <td className="py-3.5 px-4">
                    <div className="flex flex-wrap gap-1">
                      {prop.is100ftRoadFacing && (
                        <span className="px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-500/20 text-amber-400 border border-amber-500/30">
                          100ft Road
                        </span>
                      )}
                      {prop.westOpen && (
                        <span className="px-1.5 py-0.5 rounded text-[9px] font-medium bg-slate-800 text-slate-300">
                          West Open
                        </span>
                      )}
                      {prop.cornerPlot && (
                        <span className="px-1.5 py-0.5 rounded text-[9px] font-medium bg-[#C5A46D]/10 text-[#E0C79A]">
                          Corner
                        </span>
                      )}
                    </div>
                  </td>
                  <td className="py-3.5 px-4">
                    <span className={`px-2.5 py-0.5 rounded-full text-[10px] font-bold border ${
                      prop.status === 'Available' ? 'bg-[#C5A46D]/15 text-[#F9E7BA] border-[#C5A46D]/30' :
                      prop.status === 'Under Deal' ? 'bg-[#C5A46D]/15 text-[#F9E7BA] border-[#C5A46D]/30' :
                      'bg-slate-800 text-slate-300 border-slate-700'
                    }`}>
                      {prop.status}
                    </span>
                  </td>
                  <td className="py-3.5 px-4">
                    <button
                      onClick={() => updateProperty(prop.id, { isFeatured: !prop.isFeatured })}
                      className={`p-1.5 rounded-lg text-xs cursor-pointer ${
                        prop.isFeatured ? 'text-[#C5A46D]' : 'text-slate-600'
                      }`}
                      title="Toggle Featured"
                    >
                      <Star className={`w-4 h-4 ${prop.isFeatured ? 'fill-current' : ''}`} />
                    </button>
                  </td>
                  <td className="py-3.5 px-4 text-right">
                    <div className="flex items-center justify-end gap-2">
                      <button
                        onClick={() => {
                          setSelectedPropertyId(prop.id);
                          setCurrentView('property-detail');
                        }}
                        className="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300"
                        title="View Public Page"
                      >
                        <ExternalLink className="w-3.5 h-3.5" />
                      </button>
                      <button
                        onClick={() => handleOpenEdit(prop)}
                        className="p-1.5 rounded-lg bg-[#C5A46D]/20 hover:bg-[#C5A46D] text-[#C5A46D] hover:text-[#101C2C] transition-colors"
                        title="Edit Listing"
                      >
                        <Edit2 className="w-3.5 h-3.5" />
                      </button>
                      <button
                        onClick={() => handleDelete(prop.id, prop.title)}
                        className="p-1.5 rounded-lg bg-slate-800 text-slate-400 hover:text-white hover:bg-slate-700 transition-colors"
                        title="Delete Listing"
                      >
                        <Trash2 className="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Add / Edit Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md animate-fadeIn">
          <div className="relative w-full max-w-2xl bg-[#101C2C] border border-[#C5A46D]/40 rounded-2xl p-6 sm:p-8 shadow-2xl space-y-6 max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between pb-4 border-b border-slate-800">
              <h3 className="font-serif-luxury text-xl font-bold text-white">
                {editingPropId ? 'Edit Property Listing' : 'Add New Property Listing'}
              </h3>
              <button
                onClick={() => setIsModalOpen(false)}
                className="text-slate-400 hover:text-white p-1 rounded-lg"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSave} className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-1">
                  <label className="text-xs font-semibold text-slate-300">Property Title</label>
                  <input
                    type="text"
                    required
                    value={formData.title}
                    onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                    placeholder="e.g. 120 Sq. Yd. Corner Plot Sector 71"
                    className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                  />
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-semibold text-slate-300">Property Type</label>
                  <select
                    value={formData.type}
                    onChange={(e) => setFormData({ ...formData, type: e.target.value as PropertyType })}
                    className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none cursor-pointer"
                  >
                    <option value="Residential Plot">Residential Plot</option>
                    <option value="Commercial Plot">Commercial Plot</option>
                    <option value="House / Villa">House / Villa</option>
                    <option value="Commercial Shop">Commercial Shop</option>
                    <option value="Apartment">Apartment</option>
                    <option value="Farmhouse">Farmhouse</option>
                  </select>
                </div>
              </div>

              {/* Plot, File, and Block Identification */}
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 p-3.5 rounded-xl bg-[#0B1220] border border-slate-800">
                <div className="space-y-1">
                  <label className="text-xs font-semibold text-[#C5A46D]">Plot Number *</label>
                  <input
                    type="text"
                    required
                    value={formData.plotNumber}
                    onChange={(e) => setFormData({ ...formData, plotNumber: e.target.value })}
                    placeholder="e.g. R-66"
                    className="w-full bg-slate-900 text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none font-bold font-mono"
                  />
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-semibold text-slate-300">File Number</label>
                  <input
                    type="text"
                    value={formData.fileNumber}
                    onChange={(e) => setFormData({ ...formData, fileNumber: e.target.value })}
                    placeholder="e.g. TT-63-A-066"
                    className="w-full bg-slate-900 text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none font-mono"
                  />
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-semibold text-slate-300">Sector Block</label>
                  <input
                    type="text"
                    value={formData.block}
                    onChange={(e) => setFormData({ ...formData, block: e.target.value })}
                    placeholder="e.g. Block A"
                    className="w-full bg-slate-900 text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                  />
                </div>
              </div>

              {/* Dimensions & Road Width (Handwritten Note Specifics) */}
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5 rounded-xl bg-[#0B1220] border border-slate-800">
                <div className="space-y-1">
                  <label className="text-xs font-semibold text-slate-300">Front (Feet)</label>
                  <input
                    type="number"
                    value={formData.frontMeasurementFt}
                    onChange={(e) => setFormData({ ...formData, frontMeasurementFt: Number(e.target.value) })}
                    placeholder="24"
                    className="w-full bg-slate-900 text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none font-mono"
                  />
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-semibold text-slate-300">Depth (Feet)</label>
                  <input
                    type="number"
                    value={formData.depthMeasurementFt}
                    onChange={(e) => setFormData({ ...formData, depthMeasurementFt: Number(e.target.value) })}
                    placeholder="45"
                    className="w-full bg-slate-900 text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none font-mono"
                  />
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-semibold text-slate-300">Road Width (Ft)</label>
                  <input
                    type="number"
                    value={formData.roadWidthFt}
                    onChange={(e) => setFormData({ ...formData, roadWidthFt: Number(e.target.value) })}
                    placeholder="100"
                    className="w-full bg-slate-900 text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none font-mono"
                  />
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-semibold text-slate-300">Plot Area (Sq Yd)</label>
                  <input
                    type="number"
                    required
                    value={formData.sizeSqYards}
                    onChange={(e) => setFormData({ ...formData, sizeSqYards: Number(e.target.value) })}
                    placeholder="120"
                    className="w-full bg-slate-900 text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none font-mono"
                  />
                </div>
              </div>

              {/* Pricing (Public vs Private ERP) */}
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div className="space-y-1">
                  <label className="text-xs font-semibold text-[#C5A46D]">Public Sale Price (PKR) *</label>
                  <input
                    type="number"
                    required
                    value={formData.pricePKR}
                    onChange={(e) => setFormData({ ...formData, pricePKR: Number(e.target.value) })}
                    className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none font-mono font-bold"
                  />
                </div>

                {hasPermission(activeRole, 'view_sensitive_cnic') && (
                  <div className="space-y-1">
                    <label className="text-xs font-semibold text-amber-400">Private Buy Price (PKR)</label>
                    <input
                      type="number"
                      value={formData.privatePurchasePricePKR}
                      onChange={(e) => setFormData({ ...formData, privatePurchasePricePKR: Number(e.target.value) })}
                      className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none font-mono"
                    />
                  </div>
                )}

                <div className="space-y-1">
                  <label className="text-xs font-semibold text-slate-300">Sector</label>
                  <input
                    type="text"
                    required
                    value={formData.sector}
                    onChange={(e) => setFormData({ ...formData, sector: e.target.value })}
                    placeholder="Sector 63"
                    className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                  />
                </div>
              </div>

              {/* Assigned Seller and Dealer */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-1">
                  <label className="text-xs font-semibold text-slate-300">Assigned Seller / Owner</label>
                  <select
                    value={formData.sellerId}
                    onChange={(e) => setFormData({ ...formData, sellerId: e.target.value })}
                    className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                  >
                    <option value="">None / Agency Stock</option>
                    {sellers.map((s) => (
                      <option key={s.id} value={s.id}>{s.fullName} ({s.cnic})</option>
                    ))}
                  </select>
                </div>

                <div className="space-y-1">
                  <label className="text-xs font-semibold text-slate-300">Assigned Dealer / Agent</label>
                  <select
                    value={formData.assignedDealerId}
                    onChange={(e) => setFormData({ ...formData, assignedDealerId: e.target.value })}
                    className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                  >
                    <option value="">Direct Agency Desk</option>
                    {dealers.map((d) => (
                      <option key={d.id} value={d.id}>{d.fullName} ({d.agencyOrBranch})</option>
                    ))}
                  </select>
                </div>
              </div>

              <div className="space-y-1">
                <label className="text-xs font-semibold text-slate-300">Full Location Address</label>
                <input
                  type="text"
                  required
                  value={formData.location}
                  onChange={(e) => setFormData({ ...formData, location: e.target.value })}
                  placeholder="Street 14, Sector 70-D, Taiser Town Scheme 45, Karachi"
                  className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                />
              </div>

              <div className="space-y-1">
                <label className="text-xs font-semibold text-slate-300">Main Image URL</label>
                <input
                  type="url"
                  required
                  value={formData.image}
                  onChange={(e) => setFormData({ ...formData, image: e.target.value })}
                  placeholder="https://..."
                  className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                />
              </div>

              <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2">
                <label className="flex items-center gap-2 text-xs text-slate-300 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={formData.possessionReady}
                    onChange={(e) => setFormData({ ...formData, possessionReady: e.target.checked })}
                    className="accent-[#C5A46D]"
                  />
                  <span>Possession Ready</span>
                </label>

                <label className="flex items-center gap-2 text-xs text-slate-300 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={formData.westOpen}
                    onChange={(e) => setFormData({ ...formData, westOpen: e.target.checked })}
                    className="accent-[#C5A46D]"
                  />
                  <span>West Open</span>
                </label>

                <label className="flex items-center gap-2 text-xs text-slate-300 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={formData.cornerPlot}
                    onChange={(e) => setFormData({ ...formData, cornerPlot: e.target.checked })}
                    className="accent-[#C5A46D]"
                  />
                  <span>Corner Plot</span>
                </label>

                <label className="flex items-center gap-2 text-xs text-slate-300 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={formData.parkFacing}
                    onChange={(e) => setFormData({ ...formData, parkFacing: e.target.checked })}
                    className="accent-[#C5A46D]"
                  />
                  <span>Park Facing</span>
                </label>
              </div>

              <div className="space-y-1">
                <label className="text-xs font-semibold text-slate-300">Features & Amenities (comma-separated)</label>
                <input
                  type="text"
                  value={formData.features}
                  onChange={(e) => setFormData({ ...formData, features: e.target.value })}
                  placeholder="West Open, 40ft Carpeted Street, Boundary Walled"
                  className="w-full bg-[#0B1220] text-white text-xs rounded-lg px-3 py-2 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                />
              </div>

              <div className="space-y-1">
                <label className="text-xs font-semibold text-slate-300">Property Description</label>
                <textarea
                  rows={3}
                  required
                  value={formData.description}
                  onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                  placeholder="Detail allotment status, construction readiness, street dimensions..."
                  className="w-full bg-[#0B1220] text-white text-xs rounded-lg p-3 border border-slate-700 focus:border-[#C5A46D] focus:outline-none"
                />
              </div>

              <div className="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                <button
                  type="button"
                  onClick={() => setIsModalOpen(false)}
                  className="px-4 py-2 rounded-lg bg-slate-800 text-slate-300 text-xs font-semibold hover:bg-slate-700"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-6 py-2 rounded-lg bg-[#C5A46D] hover:bg-[#D4AF37] text-[#101C2C] text-xs font-bold shadow"
                >
                  {editingPropId ? 'Update Listing' : 'Add Listing'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
