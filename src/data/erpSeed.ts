/**
 * ERP seed data — ADMIN / DEMO CHUNK ONLY.
 * Dynamically imported by AppContext only when an admin session starts.
 * Never statically imported from public components.
 */
import {
  Seller,
  Buyer,
  Dealer,
  Transaction,
  PropertyFileItem,
  Inquiry,
  AuthUser,
  Property
} from '../types';
import { initialProperties as publicProperties } from './publicSeed';

/**
 * Fictional demo accounts for the presentation build (DEMO ONLY).
 * Never contains real credentials. This module is dynamically imported only
 * during authentication and is absent from public/production bundles.
 * scripts/sensitive-strings.json mirrors the same passwords so check:bundle
 * can verify they never leak into public chunks.
 */
export interface DemoAccount {
  role: string;
  name: string;
  title: string;
  email: string;
  password: string;
}

export const DEMO_ACCOUNTS: DemoAccount[] = [
  { role: 'super_admin', name: 'Syed Moazzam Ali', title: 'Super Admin', email: 'superadmin@taisartownhub.com', password: 'Super@Admin2026!' },
  { role: 'admin', name: 'Saleem Siddiqui', title: 'Agency Admin', email: 'admin@taisartownhub.com', password: 'Admin@Taisar2026' },
  { role: 'manager', name: 'Kamran Haider', title: 'Manager', email: 'manager@taisartownhub.com', password: 'Manager@2026' },
  { role: 'dealer', name: 'Bilal Farooqui', title: 'Property Dealer', email: 'dealer@taisartownhub.com', password: 'Dealer@2026' },
  { role: 'operator', name: 'Zainab Fatima', title: 'Data Operator', email: 'operator@taisartownhub.com', password: 'Operator@2026' },
];

/**
 * ERP-enriched property records: public seed + private fields (admin views).
 * Stays in this admin chunk; public bundle keeps the stripped copies.
 */
export const initialProperties: Property[] = publicProperties.map((p) =>
  p.id === 'prop-r66'
    ? {
        ...p,
        purchasePricePKR: 3200000,
        sellingPricePKR: 3500000,
        sellerId: 'sel-1',
        assignedDealerId: 'deal-1',
        assignedDealerName: 'Saleem Siddiqui',
        internalNotes: 'Client handwritten note record: 24x45 dimension, 100ft road facing. Fixed dealer commission: PKR 20,000 agreed on closing.',
      }
    : p
);

export const initialInquiries: Inquiry[] = [
  {
    id: 'inq-1',
    fullName: 'Muhammad Tariq Chaudhry',
    email: 'tariq.chaudhry@gmail.com',
    phone: '+92 300 2489102',
    subject: 'Inquiry regarding 120 Sq. Yd. Plot in Sector 70-D',
    message: 'Salam, I am an overseas Pakistani currently residing in Dubai. I am keen to invest in 2 residential plots of 120 sq. yds in Sector 70-D. Can your team verify if physical possession is ready and share the transfer fee breakdown?',
    preferredPropertyOrProject: '120 Sq. Yd. Prime West-Open Residential Plot',
    budgetRange: 'PKR 70 - 80 Lacs',
    status: 'Pending',
    submittedAt: '2026-02-28 14:32',
    adminNotes: 'Overseas buyer from UAE. Very interested. Send WhatsApp video tour of Sector 70-D and fee schedule.',
    source: 'Property Page'
  },
  {
    id: 'inq-2',
    fullName: 'Dr. Ayesha Farooq',
    email: 'ayesha.farooq.dr@yahoo.com',
    phone: '+92 333 9182374',
    subject: 'MDA Homes Ready Villa Details',
    message: 'Please send detailed photos of the interior kitchen, bathrooms, and roof terrace for the 3-bed duplex villa in Sector 30-A. Does it have Sui Gas or LPG cylinder provision?',
    preferredPropertyOrProject: 'Brand New 120 Sq. Yd. Modern Duplex Villa',
    budgetRange: 'PKR 1.2 Crore',
    status: 'Contacted',
    submittedAt: '2026-02-27 10:15',
    adminNotes: 'Spoke over phone on 28th Feb. Scheduled on-site inspection for coming Sunday afternoon.',
    source: 'Hero Search'
  },
  {
    id: 'inq-3',
    fullName: 'Sheikh Nasir Mehmood',
    email: 'nasir.textiles@hotmail.com',
    phone: '+92 321 4458901',
    subject: 'Commercial Plot 200 Sq Yd on 150ft Boulevard',
    message: 'We are looking to develop a commercial showroom and corporate offices. Is the 200 sq yd plot in Sector 50 completely clear from MDA litigation? Let us know the final price and payment terms.',
    preferredPropertyOrProject: '200 Sq. Yd. Main Boulevard Commercial Plot',
    budgetRange: 'PKR 2 Crore',
    status: 'In Progress',
    submittedAt: '2026-02-26 16:40',
    adminNotes: 'High-value client. Sent official MDA demarcation map and verified NOC copy.',
    source: 'Contact Form'
  },
  {
    id: 'inq-4',
    fullName: 'Raza Ali Baig',
    email: 'raza.baig88@gmail.com',
    phone: '+92 345 8891023',
    subject: 'Al-Samnan Arcade Shop Booking',
    message: 'Interested in booking a ground-floor retail shop in Al-Samnan Arcade. Please share the monthly installment schedule.',
    preferredPropertyOrProject: 'Ground Floor Commercial Retail Shop',
    budgetRange: 'PKR 50 Lacs',
    status: 'Resolved',
    submittedAt: '2026-02-25 11:20',
    adminNotes: 'Booking deposit confirmed. Token receipt issued.',
    source: 'Project Page'
  }
];

export const initialSellers: Seller[] = [
  {
    id: 'sel-1',
    fullName: 'Haji Abdul Rasheed',
    cnic: '42101-8815027-3',
    cnicMasked: '42101-*******-3',
    phone: '+92 300 9281721',
    whatsApp: '+92 300 9281721',
    email: 'rasheed.haji@gmail.com',
    address: 'House 42, Block 5, Gulshan-e-Iqbal, Karachi',
    ownedPropertyIds: ['prop-r66'],
    fileNumbers: ['MDA-63-R66-45'],
    documentCount: 3,
    notes: 'Primary owner of Plot R-66, Sector 63. All original MDA allotment and transfer files handed over for physical verification.',
    createdAt: '2026-01-10'
  },
  {
    id: 'sel-2',
    fullName: 'Muhammad Akram Qureshi',
    cnic: '42201-4429816-7',
    cnicMasked: '42201-*******-7',
    phone: '+92 321 4481920',
    whatsApp: '+92 321 4481920',
    email: 'akram.qureshi@yahoo.com',
    address: 'Flat 302, Al-Rauf Classic, North Nazimabad, Karachi',
    ownedPropertyIds: ['prop-1'],
    fileNumbers: ['MDA-70D-112'],
    documentCount: 2,
    notes: 'Possession letter available. Urgent sale requirement.',
    createdAt: '2026-01-20'
  },
  {
    id: 'sel-3',
    fullName: 'Chaudhry Riaz Ahmed',
    cnic: '35202-3306745-5',
    cnicMasked: '35202-*******-5',
    phone: '+92 333 8819201',
    whatsApp: '+92 333 8819201',
    address: 'Sector 50 Commercial Avenue, Karachi',
    ownedPropertyIds: ['prop-3', 'prop-4'],
    fileNumbers: ['MDA-50-COM-200', 'MDA-63-4-9201'],
    documentCount: 4,
    notes: 'Commercial 150ft road plot owner. Ground + 4 approval certified.',
    createdAt: '2026-02-01'
  }
];

export const initialBuyers: Buyer[] = [
  {
    id: 'buy-1',
    fullName: 'Muhammad Usman',
    cnic: '42101-5538291-9',
    cnicMasked: '42101-*******-9',
    phone: '+971 50 1234567',
    whatsApp: '+971 50 1234567',
    email: 'usman.dubai@emirates.net',
    address: 'Deira Creek Tower, Dubai, UAE (Overseas Investor)',
    interestedSector: 'Sector 63 / Sector 70-D',
    budgetRange: 'PKR 35 - 40 Lacs',
    interestedPropertyIds: ['prop-r66'],
    paymentStatus: 'Advance Paid',
    notes: 'Agreement executed for Plot R-66 Sector 63. Paid PKR 1,000,000 advance through Pay Order.',
    createdAt: '2026-02-16'
  },
  {
    id: 'buy-2',
    fullName: 'Dr. Ayesha Farooq',
    cnic: '42301-6640172-2',
    cnicMasked: '42301-*******-2',
    phone: '+92 333 9182374',
    whatsApp: '+92 333 9182374',
    email: 'ayesha.farooq.dr@yahoo.com',
    address: 'Civil Hospital Colony, Karachi',
    interestedSector: 'Sector 30-A (MDA Homes)',
    budgetRange: 'PKR 1.2 Crore',
    interestedPropertyIds: ['prop-5'],
    paymentStatus: 'Searching',
    notes: 'Looking for ready duplex villa for family residence.',
    createdAt: '2026-02-18'
  },
  {
    id: 'buy-3',
    fullName: 'Sheikh Nasir Mehmood',
    cnic: '42201-7746028-1',
    cnicMasked: '42201-*******-1',
    phone: '+92 321 4458901',
    whatsApp: '+92 321 4458901',
    email: 'nasir.textiles@hotmail.com',
    address: 'SITE Industrial Area, Karachi',
    interestedSector: 'Sector 50 Commercial',
    budgetRange: 'PKR 2 Crore',
    interestedPropertyIds: ['prop-3'],
    paymentStatus: 'Full Payment Ready',
    notes: 'Commercial showroom planning. Immediate funds available via banking transfer.',
    createdAt: '2026-02-20'
  }
];

export const initialDealers: Dealer[] = [
  {
    id: 'deal-1',
    fullName: 'Saleem Siddiqui',
    cnic: '42101-9235180-1',
    cnicMasked: '42101-*******-1',
    phone: '+92 321 8292831',
    whatsApp: '+92 321 8292831',
    email: 'saleem@taisartownhub.com',
    agencyAffiliation: 'TaisarTownHub Senior Advisory',
    role: 'Senior Land Specialist & Scheme 45 Lead',
    commissionType: 'Fixed',
    commissionRate: 20000,
    totalEarnedPKR: 160000,
    pendingCommissionPKR: 20000,
    assignedPropertiesCount: 4,
    completedDealsCount: 8,
    status: 'Active',
    avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=200&q=80',
    createdAt: '2025-06-01'
  },
  {
    id: 'deal-2',
    fullName: 'Kamran Haider',
    cnic: '42301-1836295-4',
    cnicMasked: '42201-*******-5',
    phone: '+92 321 8292832',
    whatsApp: '+92 321 8292832',
    email: 'kamran@taisartownhub.com',
    agencyAffiliation: 'TaisarTownHub Commercial Desk',
    role: 'Commercial Projects & Boulevard Lead',
    commissionType: 'Percentage',
    commissionRate: 1,
    totalEarnedPKR: 350000,
    pendingCommissionPKR: 0,
    assignedPropertiesCount: 3,
    completedDealsCount: 5,
    status: 'Active',
    avatar: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=200&q=80',
    createdAt: '2025-08-15'
  },
  {
    id: 'deal-3',
    fullName: 'Bilal Farooqui',
    cnic: '42301-2947163-9',
    cnicMasked: '42301-*******-9',
    phone: '+92 300 7719283',
    whatsApp: '+92 300 7719283',
    email: 'bilal@taisartownhub.com',
    agencyAffiliation: 'Associate Partner Network',
    role: 'Phase 2 & Affordable Allotments Consultant',
    commissionType: 'Fixed',
    commissionRate: 15000,
    totalEarnedPKR: 75000,
    pendingCommissionPKR: 15000,
    assignedPropertiesCount: 2,
    completedDealsCount: 5,
    status: 'Active',
    avatar: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&w=200&q=80',
    createdAt: '2025-11-01'
  },
  {
    id: 'deal-4',
    fullName: 'Zubair Ahmed Khan',
    cnic: '42401-5048391-3',
    cnicMasked: '42401-*******-3',
    phone: '+92 315 2891901',
    whatsApp: '+92 315 2891901',
    email: 'zubair@taisartownhub.com',
    agencyAffiliation: 'Karachi Modern Realty & Biometric Desk',
    agencyOrBranch: 'Karachi Modern Realty Group',
    role: 'Biometric Documentation & Transfer Specialist',
    commissionType: 'Fixed',
    commissionRate: 25000,
    commissionValue: 25000,
    totalEarnedPKR: 125000,
    pendingCommissionPKR: 25000,
    assignedPropertiesCount: 3,
    dealsClosed: 6,
    completedDealsCount: 6,
    status: 'Active',
    avatar: 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?auto=format&fit=crop&w=200&q=80',
    createdAt: '2025-12-10'
  }
];

export const initialTransactions: Transaction[] = [
  {
    id: 'tx-6301',
    propertyId: 'prop-r66',
    propertyTitle: 'Plot R-66 (120 Sq. Yds) — 100ft Road Facing, Sector 63',
    plotNumber: 'R-66',
    sector: 'Sector 63',
    sellerId: 'sel-1',
    sellerName: 'Haji Abdul Rasheed',
    buyerId: 'buy-1',
    buyerName: 'Muhammad Usman (Overseas UAE)',
    dealerId: 'deal-1',
    dealerName: 'Saleem Siddiqui',
    transactionType: 'Sale',
    agreementAmountPKR: 3500000,
    purchaseAmountPKR: 3200000,
    finalSellingAmountPKR: 3500000,
    advancePaymentPKR: 1000000,
    remainingBalancePKR: 2500000,
    commissionAmountPKR: 20000, // Handwritten note example: 20,000 PKR commission
    commissionStatus: 'Pending',
    transactionDate: '2026-02-16',
    paymentMethod: 'Pay Order',
    paymentStatus: 'Partially Paid',
    status: 'Agreement Signed',
    internalRemarks: 'Handwritten deal record: 24x45 plot on 100ft road. Advance token received in agency account. Full transfer scheduled at MDA for 15th March.',
    documents: ['doc-r66-token', 'doc-r66-allotment']
  },
  {
    id: 'tx-7102',
    propertyId: 'prop-2',
    propertyTitle: '240 Sq. Yd. Corner Park-Facing Executive Plot',
    plotNumber: 'CP-44',
    sector: 'Sector 71',
    sellerId: 'sel-2',
    sellerName: 'Muhammad Akram Qureshi',
    buyerId: 'buy-2',
    buyerName: 'Dr. Ayesha Farooq',
    dealerId: 'deal-1',
    dealerName: 'Saleem Siddiqui',
    transactionType: 'Sale',
    agreementAmountPKR: 7800000,
    purchaseAmountPKR: 7400000,
    finalSellingAmountPKR: 7800000,
    advancePaymentPKR: 7800000,
    remainingBalancePKR: 0,
    commissionAmountPKR: 50000,
    commissionStatus: 'Paid',
    transactionDate: '2026-01-25',
    paymentMethod: 'Bank Transfer',
    paymentStatus: 'Fully Paid',
    status: 'Completed',
    internalRemarks: 'Biometric transfer completed at MDA Head Office. Physical possession handover completed.',
    documents: ['doc-71-transfer']
  },
  {
    id: 'tx-6303',
    dealNumber: 'TXN-2026-0003',
    propertyId: 'prop-3',
    propertyTitle: '200 Sq. Yd. Main Boulevard Commercial Plot',
    plotNumber: 'S-12',
    fileNumber: 'MDA-50-COM-200',
    sector: 'Sector 50',
    sellerId: 'sel-3',
    sellerName: 'Chaudhry Riaz Ahmed',
    buyerId: 'buy-3',
    buyerName: 'Sheikh Nasir Mehmood',
    dealerId: 'deal-3',
    dealerName: 'Bilal Farooqui',
    transactionType: 'Sale',
    agreementAmountPKR: 19000000,
    purchaseAmountPKR: 18200000,
    finalSellingAmountPKR: 19000000,
    advancePaymentPKR: 2000000,
    remainingBalancePKR: 17000000,
    commissionAmountPKR: 45000,
    commissionStatus: 'Unpaid',
    transactionDate: '2026-02-22',
    paymentMethod: 'Bank Transfer',
    paymentStatus: 'Partially Paid',
    status: 'Negotiation',
    internalRemarks: 'Buyer showroom plan under negotiation for the main boulevard commercial plot. Token schedule pending agreement signing.',
    documents: []
  }
];

export const initialPropertyFiles: PropertyFileItem[] = [
  {
    id: 'doc-r66-allotment',
    propertyId: 'prop-r66',
    propertyTitle: 'Plot R-66, Sector 63',
    plotNumber: 'R-66',
    title: 'MDA Original Allotment Order — Plot R-66 Sector 63',
    fileType: 'pdf',
    category: 'Original Allotment File',
    fileSize: '3.4 MB',
    url: 'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=1200&q=80',
    verificationStatus: 'Verified',
    verifiedBy: 'Super Admin',
    verifiedAt: '2026-02-14',
    uploadedAt: '2026-02-12',
    notes: 'Record checked against MDA computerized database. Dimensions 24x45 confirmed.'
  },
  {
    id: 'doc-r66-token',
    propertyId: 'prop-r66',
    propertyTitle: 'Plot R-66, Sector 63',
    plotNumber: 'R-66',
    title: 'Token Advance Deposit Receipt (PKR 10 Lacs)',
    fileType: 'image',
    category: 'Payment Receipt',
    fileSize: '1.2 MB',
    url: 'https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&fit=crop&w=1000&q=80',
    verificationStatus: 'Verified',
    verifiedBy: 'Manager',
    verifiedAt: '2026-02-16',
    uploadedAt: '2026-02-16',
    notes: 'Bank Pay Order #PO-9812401 deposited into client escrow.'
  },
  {
    id: 'doc-r66-demarcation',
    propertyId: 'prop-r66',
    propertyTitle: 'Plot R-66, Sector 63',
    plotNumber: 'R-66',
    title: 'On-Ground Demarcation & GPS Pegging Certificate',
    fileType: 'pdf',
    category: 'Site Demarcation Map',
    fileSize: '4.8 MB',
    url: 'https://images.unsplash.com/photo-1577495508048-b635879837f1?auto=format&fit=crop&w=1200&q=80',
    verificationStatus: 'Verified',
    verifiedBy: 'Super Admin',
    verifiedAt: '2026-02-15',
    uploadedAt: '2026-02-15',
    notes: 'Surveyor physically confirmed 100ft road frontage.'
  },
  {
    id: 'doc-sel1-cnic',
    propertyId: 'prop-r66',
    propertyTitle: 'Plot R-66, Sector 63',
    plotNumber: 'R-66',
    title: 'Seller CNIC Electronic Verification Slip (Masked)',
    fileType: 'pdf',
    category: 'CNIC Copy',
    fileSize: '890 KB',
    url: '#',
    verificationStatus: 'Verified',
    verifiedBy: 'Admin',
    verifiedAt: '2026-02-10',
    uploadedAt: '2026-02-10',
    notes: 'Nadra biometric record authenticated.'
  },
  {
    id: 'doc-70d-pending',
    propertyId: 'prop-1',
    propertyTitle: 'Plot P-112 Sector 70-D',
    plotNumber: 'P-112',
    title: 'Utility Surcharge Clearance Affidavit',
    fileType: 'pdf',
    category: 'Legal Undertaking',
    fileSize: '2.1 MB',
    url: '#',
    verificationStatus: 'Pending Verification',
    uploadedAt: '2026-02-24',
    notes: 'Awaiting K-Electric NOC stamp.'
  }
];


export const initialAuthUsers: AuthUser[] = [
  {
    id: 'usr-1',
    name: 'Syed Moazzam Ali',
    email: 'superadmin@taisartownhub.com',
    role: 'super_admin',
    permissions: []
  },
  {
    id: 'usr-2',
    name: 'Saleem Siddiqui',
    email: 'admin@taisartownhub.com',
    role: 'admin',
    permissions: []
  },
  {
    id: 'usr-3',
    name: 'Kamran Haider',
    email: 'manager@taisartownhub.com',
    role: 'manager',
    permissions: []
  },
  {
    id: 'usr-4',
    name: 'Bilal Farooqui',
    email: 'dealer@taisartownhub.com',
    role: 'dealer',
    permissions: [],
    dealerId: 'deal-3'
  },
  {
    id: 'usr-5',
    name: 'Zainab Fatima',
    email: 'operator@taisartownhub.com',
    role: 'operator',
    permissions: []
  }
];
