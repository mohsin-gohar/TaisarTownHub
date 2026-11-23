import test from 'node:test';
import assert from 'node:assert/strict';
import {
  formatPKRCurrency,
  calculateInstallmentPlan,
  generateWhatsAppLink,
  filterProperties,
  validateInquiryForm,
  maskCNIC,
  checkDuplicateProperty,
  isDuplicatePlot,
  calculateCommission,
  calculateERPFinancials,
  calculateMediaUsageCount,
  hasPermission
} from './logic';
import { Property, Transaction, PaymentEntry, CommissionEntry } from '../types';

test('formatPKRCurrency formats Lacs and Crores correctly', () => {
  assert.equal(formatPKRCurrency(3500000), 'PKR 35 Lacs');
  assert.equal(formatPKRCurrency(12500000), 'PKR 1.25 Crore');
  assert.equal(formatPKRCurrency(20000000), 'PKR 2 Crore');
  assert.equal(formatPKRCurrency(85000), 'PKR 85,000');
  assert.equal(formatPKRCurrency(0), 'PKR 0');
});

test('formatPKRCurrency renders negative amounts with an explicit sign', () => {
  assert.equal(formatPKRCurrency(-3500000), '-PKR 35 Lacs');
  assert.equal(formatPKRCurrency(-12500000), '-PKR 1.25 Crore');
  assert.equal(formatPKRCurrency(-85000), '-PKR 85,000');
  assert.equal(formatPKRCurrency(NaN), 'PKR 0');
});

test('calculateInstallmentPlan computes down payment and monthly correctly', () => {
  const result = calculateInstallmentPlan(4000000, 25, 2);
  assert.equal(result.downPayment, 1000000);
  assert.equal(result.remainingAmount, 3000000);
  assert.equal(result.totalMonths, 24);
  assert.equal(result.monthlyInstallment, 125000);
});

test('generateWhatsAppLink produces valid encoded wa.me URL', () => {
  const link = generateWhatsAppLink('+92 300 1234567', 'Salam, I want info on Sector 70-D');
  assert.ok(link.startsWith('https://wa.me/923001234567?text='));
});

test('generateWhatsAppLink normalizes local and international PK formats', () => {
  // 0300... local format
  assert.ok(generateWhatsAppLink('03001234567', 'hi').startsWith('https://wa.me/923001234567?text='));
  // 0092... international dialing prefix
  assert.ok(generateWhatsAppLink('00923001234567', 'hi').startsWith('https://wa.me/923001234567?text='));
  // Bare 10-digit national number
  assert.ok(generateWhatsAppLink('3001234567', 'hi').startsWith('https://wa.me/923001234567?text='));
  // Missing or implausible numbers produce no URL. Callers must hide the CTA.
  assert.equal(generateWhatsAppLink('12345', 'hi'), '');
  assert.equal(generateWhatsAppLink('', 'hi'), '');
  assert.equal(generateWhatsAppLink('92923001234567', 'hi').startsWith('https://wa.me/923001234567?text='), true);
});

test('validateInquiryForm checks required fields strictly', () => {
  assert.equal(validateInquiryForm({ fullName: '', phone: '12345678', message: 'Hello world' }).isValid, false);
  assert.equal(validateInquiryForm({ fullName: 'Ali Khan', phone: '03001234567', message: 'Interested in plot' }).isValid, true);
  assert.equal(validateInquiryForm({ fullName: 'Ali Khan', phone: '12345', message: 'Interested in plot' }).isValid, false);
  assert.equal(validateInquiryForm({ fullName: 'Ali Khan', phone: '+92 300 1234567', message: 'Interested in plot' }).isValid, true);
});

test('maskCNIC masks national ID numbers for privacy', () => {
  assert.equal(maskCNIC('42101-1234567-1'), '42101-*******-1');
  assert.equal(maskCNIC('4210112345671'), '42101-*******-1');
  assert.equal(maskCNIC(''), 'N/A');
});

test('checkDuplicateProperty flags matching plot and sector', () => {
  const mockProps: Property[] = [
    {
      id: 'p-1',
      title: 'Plot R-66 Sector 63',
      description: '120 Sq Yd on 100ft road',
      pricePKR: 3500000,
      formattedPrice: 'PKR 35 Lacs',
      location: 'Sector 63, Karachi',
      sector: 'Sector 63',
      block: 'Block A',
      plotNumber: 'R-66',
      sizeSqYards: 120,
      type: 'Residential Plot',
      status: 'Available',
      isFeatured: true,
      images: ['https://example.com/r66.jpg'],
      features: ['100ft Road Facing', '24ft Front'],
      possessionReady: true,
      mdaApproved: true,
      createdDate: '2026-01-01',
      contactPerson: 'Saleem Siddiqui',
      contactPhone: '03001234567',
      contactWhatsApp: '03001234567'
    }
  ];

  // Exact duplicate: R-66 in Sector 63 Block A
  const dupCheck = checkDuplicateProperty(mockProps, 'R-66', 'Sector 63', 'Block A');
  assert.equal(dupCheck.isDuplicate, true);

  // Different plot number: R-67 in Sector 63
  const newPlotCheck = checkDuplicateProperty(mockProps, 'R-67', 'Sector 63', 'Block A');
  assert.equal(newPlotCheck.isDuplicate, false);

  // Exclude current ID during edit: editing p-1 itself
  const editSelfCheck = checkDuplicateProperty(mockProps, 'R-66', 'Sector 63', 'Block A', 'p-1');
  assert.equal(editSelfCheck.isDuplicate, false);
});

test('isDuplicatePlot returns a primitive boolean gate (H5 regression: object truthiness blocked every save)', () => {
  const mockProps: Property[] = [
    {
      id: 'p-1',
      title: 'Plot QAF-001 Sector 71',
      description: '120 Sq Yd',
      pricePKR: 3500000,
      formattedPrice: 'PKR 35 Lacs',
      location: 'Sector 71, Karachi',
      sector: 'Sector 71',
      block: 'Block QA',
      plotNumber: 'QAF-001',
      sizeSqYards: 120,
      type: 'Residential Plot',
      status: 'Available',
      isFeatured: false,
      images: ['https://example.com/qaf.jpg'],
      features: ['West Open'],
      possessionReady: true,
      mdaApproved: true,
      createdDate: '2026-01-01',
      contactPerson: 'Saleem Siddiqui',
      contactPhone: '03001234567',
      contactWhatsApp: '03001234567'
    }
  ];

  // The exact bug: the form once consumed checkDuplicateProperty()'s object return
  // directly in `if (isDuplicate)`, and an object is always truthy — so EVERY new plot
  // was rejected as a duplicate. The helper must hand back a real boolean.
  const brandNew = isDuplicatePlot(mockProps, 'QAF-777', 'Sector 80', 'Block B');
  assert.equal(typeof brandNew, 'boolean');
  assert.equal(brandNew, false);

  // Genuinely new plot in an empty inventory must still be allowed.
  assert.equal(isDuplicatePlot([], 'QAF-001', 'Sector 71'), false);

  // Real duplicate (same plot + sector + block) must still be caught.
  assert.equal(isDuplicatePlot(mockProps, 'qaf-001', 'sector 71', 'block qa'), true);

  // Same plot number in a DIFFERENT sector is a distinct plot, not a duplicate.
  assert.equal(isDuplicatePlot(mockProps, 'QAF-001', 'Sector 72', 'Block QA'), false);

  // Editing a record without changing its plot must not flag itself (excludeId).
  assert.equal(isDuplicatePlot(mockProps, 'QAF-001', 'Sector 71', 'Block QA', 'p-1'), false);

  // Missing plot/sector means no duplicate gate applies.
  assert.equal(isDuplicatePlot(mockProps, undefined, 'Sector 71'), false);
  assert.equal(isDuplicatePlot(mockProps, 'QAF-001', ''), false);
});

test('filterProperties never returns soft-deleted listings (H3 regression)', () => {
  const active: Property = {
    id: 'p-1',
    title: 'Plot R-66 Sector 63',
    description: '120 Sq Yd on 100ft road',
    pricePKR: 3500000,
    formattedPrice: 'PKR 35 Lacs',
    location: 'Sector 63, Karachi',
    sector: 'Sector 63',
    block: 'Block A',
    plotNumber: 'R-66',
    sizeSqYards: 120,
    type: 'Residential Plot',
    status: 'Available',
    isFeatured: true,
    images: ['https://example.com/r66.jpg'],
    features: ['100ft Road Facing'],
    possessionReady: true,
    mdaApproved: true,
    createdDate: '2026-01-01',
    contactPerson: 'Saleem Siddiqui',
    contactPhone: '03001234567',
    contactWhatsApp: '03001234567'
  };
  const deleted: Property = {
    ...active,
    id: 'p-2',
    plotNumber: 'R-67',
    deletedAt: '2026-11-01T00:00:00.000Z',
    deletedBy: 'Syed Moazzam Ali'
  };

  const all = filterProperties([active, deleted], {});
  assert.equal(all.length, 1);
  assert.equal(all[0].id, 'p-1');

  // Even when every filter matches the deleted row, it must not surface.
  const bySearch = filterProperties([active, deleted], { searchTerm: 'R-67' });
  assert.equal(bySearch.length, 0);
});

test('filterProperties tolerates null text fields (N-05 regression: keyword search crashed the listing)', () => {
  // Catalog rows can carry null description/location/sector (e.g. listings
  // created through the API without those optional fields). filterProperties
  // used to call .toLowerCase() on them and throw inside Array.filter, which
  // the ErrorBoundary turned into a blank listing area.
  const sparse: Property = {
    id: 'p-9',
    title: 'WORKFLOW lifecycle listing',
    description: null as unknown as string,
    pricePKR: 31000000,
    formattedPrice: 'PKR 3.1 Crore',
    location: null as unknown as string,
    sector: null as unknown as string,
    sizeSqYards: 240,
    type: 'Residential Plot',
    status: 'Available',
    isFeatured: false,
    images: [],
    features: [],
    possessionReady: false,
    mdaApproved: false,
    createdDate: '2026-11-14',
    contactPerson: 'Probe',
    contactPhone: '03001112223',
    contactWhatsApp: '03001112223',
  };

  const byTitle = filterProperties([sparse], { searchTerm: 'WORKFLOW' });
  assert.equal(byTitle.length, 1);

  const noMatch = filterProperties([sparse], { searchTerm: 'Sector 70-D' });
  assert.equal(noMatch.length, 0);

  // Sector facet with a null sector must exclude, not throw.
  const bySector = filterProperties([sparse], { sector: 'Sector 70-D' });
  assert.equal(bySector.length, 0);
});

test('calculateCommission calculates fixed, percentage, and custom rates', () => {
  // Handwritten requirement example: 20,000 fixed
  assert.equal(calculateCommission(3500000, 'Fixed', 20000), 20000);
  // 1% on 5,000,000
  assert.equal(calculateCommission(5000000, 'Percentage', 1), 50000);
  // Custom
  assert.equal(calculateCommission(3000000, 'Custom', 35000), 35000);
});

test('calculateERPFinancials computes totals from the ledgers (P1-5 semantics)', () => {
  const transactions: Transaction[] = [
    {
      id: 'tx-1',
      propertyId: 'p-1',
      propertyTitle: 'Plot R-66 Sector 63',
      plotNumber: 'R-66',
      sector: 'Sector 63',
      sellerId: 's-1',
      sellerName: 'Haji Rasheed',
      buyerId: 'b-1',
      buyerName: 'Dr. Farooq',
      dealerId: 'd-1',
      dealerName: 'Saleem Siddiqui',
      transactionType: 'Sale',
      agreementAmountPKR: 3500000,
      purchaseAmountPKR: 3200000,
      finalSellingAmountPKR: 3500000,
      advancePaymentPKR: 1000000,
      remainingBalancePKR: 2500000,
      commissionAmountPKR: 20000,
      commissionStatus: 'Pending',
      transactionDate: '2026-02-01',
      paymentMethod: 'Pay Order',
      paymentStatus: 'Partially Paid',
      status: 'Agreement Signed',
      internalRemarks: 'Token paid',
      documents: []
    },
    {
      id: 'tx-2',
      propertyId: 'p-2',
      propertyTitle: 'Commercial Plot Sector 50',
      plotNumber: 'C-10',
      sector: 'Sector 50',
      sellerId: 's-2',
      sellerName: 'Malik Builders',
      buyerId: 'b-2',
      buyerName: 'Sheikh Nasir',
      transactionType: 'Sale',
      agreementAmountPKR: 15000000,
      purchaseAmountPKR: 14000000,
      finalSellingAmountPKR: 15000000,
      advancePaymentPKR: 15000000,
      remainingBalancePKR: 0,
      commissionAmountPKR: 100000,
      commissionStatus: 'Paid',
      transactionDate: '2026-02-15',
      paymentMethod: 'Bank Transfer',
      paymentStatus: 'Fully Paid',
      status: 'Completed',
      internalRemarks: 'Full transfer done',
      documents: []
    },
    {
      id: 'tx-3',
      propertyId: 'p-3',
      plotNumber: 'R-70',
      sector: 'Sector 64',
      sellerId: 's-3',
      sellerName: 'Farmhouse Seller',
      buyerId: 'b-3',
      buyerName: 'Overseas Buyer',
      dealAmountPKR: 4000000,
      commissionAmountPKR: 25000,
      commissionStatus: 'Pending',
      commissionPaidPKR: 0,
      paymentMethod: 'Bank Transfer',
      paymentStatus: 'Fully Paid',
      status: 'Fully Paid',
      documents: []
    }
  ];

  // Ledger truth: money and commission come from the payment/commission
  // ledgers; tx-1 has no ledger rows, so its recorded advance still counts.
  const payments: PaymentEntry[] = [
    { id: 'pay-1', transactionId: 'tx-2', amountPKR: 15000000, method: 'Bank Transfer', paidAt: '2026-02-15', recordedBy: 'Tester' },
    { id: 'pay-2', transactionId: 'tx-3', amountPKR: 4000000, method: 'Bank Transfer', paidAt: '2026-03-01', recordedBy: 'Tester' }
  ];
  const commissions: CommissionEntry[] = [
    { id: 'cm-1', transactionId: 'tx-1', dealerId: 'd-1', amountPKR: 20000, paidPKR: 0, payouts: [], status: 'Pending Accrual' },
    { id: 'cm-2', transactionId: 'tx-2', dealerId: 'd-1', amountPKR: 100000, paidPKR: 100000, payouts: [], status: 'Paid', accruedOn: '2026-02-15' },
    { id: 'cm-3', transactionId: 'tx-3', dealerId: 'd-2', amountPKR: 25000, paidPKR: 0, payouts: [], status: 'Accrued', accruedOn: '2026-03-01' }
  ];

  const fin = calculateERPFinancials(transactions, payments, commissions);
  assert.equal(fin.totalSalesVolume, 22500000); // 3.5M + 15M + 4M (voided/cancelled never enter)
  assert.equal(fin.totalCollected, 20000000);   // 1M advance fallback + 15M + 4M ledger
  assert.equal(fin.totalAdvancesCollected, 20000000); // legacy alias kept
  assert.equal(fin.totalPendingReceivables, 2500000); // only the unfinished deal
  assert.equal(fin.pendingReceivables, 2500000);      // legacy alias kept
  // Payable = ACCRUED balances only: 25k accrued (tx-3); the 20k promise
  // (Pending Accrual) is NOT payable yet; 100k Paid contributes zero.
  assert.equal(fin.totalCommissionPayable, 25000);
  assert.equal(fin.totalCommissionPaid, 100000);
  assert.equal(fin.completedDealsCount, 1);
  assert.equal(fin.activeDealsCount, 2);
  assert.equal(fin.totalDealsCount, 2); // alias of activeDealsCount
});

test('calculateMediaUsageCount detects real asset references', () => {
  const url = 'https://example.com/active-photo.jpg';
  const properties: any[] = [
    { id: '1', title: 'Plot 1', images: [url] },
    { id: '2', title: 'Plot 2', images: ['https://example.com/other.jpg'] }
  ];
  const projects: any[] = [
    { id: 'pr-1', title: 'Project 1', coverImage: url, galleryImages: [] }
  ];

  const usage = calculateMediaUsageCount(url, properties, projects, []);
  assert.equal(usage.count, 2);
  assert.ok(usage.referencingTitles.includes('Plot 1'));
  assert.ok(usage.referencingTitles.includes('Project 1'));
});

test('hasPermission enforces role boundaries', () => {
  assert.equal(hasPermission('Super Admin', 'view_sensitive_cnic'), true);
  assert.equal(hasPermission('Super Admin', 'manage_dealers'), true);
  assert.equal(hasPermission('Property Dealer', 'manage_users'), false);
  assert.equal(hasPermission('Data Entry Operator', 'approve_transactions'), false);
});
