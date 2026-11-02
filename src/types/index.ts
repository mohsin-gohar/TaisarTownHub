export type PropertyType = 
  | 'Residential Plot'
  | 'Commercial Plot'
  | 'House / Villa'
  | 'Apartment'
  | 'Commercial Shop'
  | 'Farmhouse';

export type PropertyStatus = 
  | 'Available'
  | 'Reserved'
  | 'Under Negotiation'
  | 'Under Deal'
  | 'Sold'
  | 'Transferred'
  | 'On Hold'
  | 'Cancelled'
  | 'Hot Deal'
  | 'Under Offer';

export type ProjectStatus = 'Under Development' | 'Ready for Possession' | 'Booking Open' | 'Upcoming';

export type ProjectCategory = 
  | 'Residential Projects'
  | 'Commercial Projects'
  | 'Housing Societies'
  | 'Development Projects';

export interface Property {
  id: string;
  title: string;
  description: string;
  pricePKR: number; // Public demand price in PKR
  formattedPrice: string; // e.g. "PKR 35 Lacs" or "PKR 1.25 Crore"
  location: string; // e.g. "Sector 63, Street 8, Taiser Town Scheme 45, Karachi"
  phase?: 'Phase 1' | 'Phase 2' | 'Phase 3'; // Taiser Town has 3 distinct master phases
  sector: string; // e.g. "Sector 63" or "Sector 70-D"
  block?: string; // e.g. "Block A", "Block B"
  streetNumber?: string; // e.g. "Street 12"
  plotNumber?: string; // e.g. "R-66" (from handwritten requirements)
  fileNumber?: string; // e.g. "MDA-63-9182"
  sizeSqYards: number; // e.g. 80, 120, 240, 400
  sizeUnit?: 'Sq. Yds' | 'Marla';
  frontFeet?: number; // e.g. 24ft frontage (handwritten spec)
  frontMeasurementFt?: number;
  depthFeet?: number; // e.g. 45ft depth (handwritten spec)
  depthMeasurementFt?: number;
  roadWidthFeet?: number; // e.g. 100ft road
  roadWidthFt?: number;
  isRoadFacing?: boolean;
  is100ftRoadFacing?: boolean;
  type: PropertyType;
  status: PropertyStatus;
  isFeatured: boolean;
  images: string[];
  videoUrl?: string;
  features: string[];
  bedrooms?: number;
  bathrooms?: number;
  possessionReady: boolean;
  mdaApproved: boolean;
  cornerPlot?: boolean;
  westOpen?: boolean;
  parkFacing?: boolean;
  mainBoulevard?: boolean;
  createdDate: string;
  contactPerson: string;
  contactPhone: string;
  contactWhatsApp: string;

  // Private ERP Fields (Never exposed on public website views)
  purchasePricePKR?: number; // Private cost from seller
  privatePurchasePricePKR?: number;
  sellingPricePKR?: number; // Agreed private selling amount
  sellerId?: string;
  sellerName?: string;
  buyerId?: string;
  buyerName?: string;
  assignedDealerId?: string;
  assignedDealerName?: string;
  internalNotes?: string;
  /** Soft-delete (audit): record is hidden, not destroyed. */
  deletedAt?: string;
  deletedBy?: string;
}

export type PublicProperty = Omit<
  Property,
  | 'purchasePricePKR'
  | 'privatePurchasePricePKR'
  | 'sellingPricePKR'
  | 'sellerId'
  | 'sellerName'
  | 'buyerId'
  | 'buyerName'
  | 'internalNotes'
  | 'deletedAt'
  | 'deletedBy'
>;

export interface Seller {
  id: string;
  fullName: string;
  cnic?: string;
  cnicMasked: string; // Sensitive data masked, e.g. 42101-*******-3
  phone: string;
  whatsApp?: string;
  email?: string;
  address?: string;
  ownedPropertyIds?: string[];
  ownedProperties?: string[];
  fileNumbers?: string[];
  documentCount?: number;
  totalPayoutReceivedPKR?: number;
  bankAccountTitle?: string;
  bankName?: string;
  accountNumber?: string;
  notes?: string;
  createdAt?: string;
  /** Soft-delete (audit): record is hidden, not destroyed. */
  deletedAt?: string;
  deletedBy?: string;
}

export type BuyerPaymentStatus =
  | 'Token Paid'
  | 'Advance Paid'
  | 'Searching'
  | 'Full Payment Ready'
  | 'Fully Paid'
  | 'Pending Installment';

export interface Buyer {
  id: string;
  fullName: string;
  cnic?: string;
  cnicMasked: string;
  phone: string;
  whatsApp?: string;
  email?: string;
  address?: string;
  interestedSector: string;
  budgetRange?: string;
  budgetPKR?: number;
  advancePaidPKR?: number;
  interestedPlotSize?: string;
  assignedDealerId?: string;
  interestedPropertyIds?: string[];
  interestedProperties?: string[];
  paymentStatus: BuyerPaymentStatus;
  notes?: string;
  createdAt?: string;
  /** Soft-delete (audit): record is hidden, not destroyed. */
  deletedAt?: string;
  deletedBy?: string;
}

export type CommissionType = 'Fixed' | 'Percentage' | 'Custom' | 'fixed' | 'percentage' | 'custom';

export interface Dealer {
  id: string;
  fullName: string;
  cnic?: string;
  cnicMasked: string;
  phone: string;
  whatsApp?: string;
  email?: string;
  agencyAffiliation?: string;
  agencyOrBranch?: string;
  role?: string; // e.g. 'Senior Land Specialist', 'Commercial Consultant'
  commissionType: CommissionType;
  commissionRate?: number; // e.g. 20000 (PKR fixed) or 1 (%)
  commissionValue?: number;
  totalEarnedPKR?: number;
  totalCommissionEarnedPKR?: number;
  totalCommissionPaidPKR?: number;
  pendingCommissionPKR?: number;
  assignedPropertiesCount?: number;
  dealsClosed?: number;
  completedDealsCount?: number;
  status: 'Active' | 'Inactive';
  avatar?: string;
  createdAt?: string;
  /** Soft-delete (audit): record is hidden, not destroyed. */
  deletedAt?: string;
  deletedBy?: string;
}

export type TransactionStatus = 
  | 'Draft'
  | 'Negotiation'
  | 'Agreement Signed'
  | 'Payment Pending'
  | 'Partially Paid'
  | 'Fully Paid'
  | 'Completed'
  | 'Cancelled'
  | 'Token Paid'
  | 'Advance Received'
  | 'Under Transfer';

export type PaymentStatus = 'Unpaid' | 'Partially Paid' | 'Fully Paid';
export type CommissionStatus = 'Pending' | 'Paid' | 'Unpaid' | 'Partially Paid' | 'Cancelled';

export interface Transaction {
  id: string;
  dealNumber?: string;
  dealDate?: string;
  propertyId: string;
  propertyTitle?: string;
  plotNumber: string;
  fileNumber?: string;
  sector: string;
  sellerId: string;
  sellerName: string;
  buyerId: string;
  buyerName: string;
  dealerId?: string;
  dealerName?: string;
  transactionType?: 'Sale' | 'Purchase';
  agreementAmountPKR?: number;
  purchaseAmountPKR?: number;
  finalSellingAmountPKR?: number;
  dealAmountPKR?: number;
  advancePaymentPKR?: number;
  advancePKR?: number;
  remainingBalancePKR?: number;
  balancePKR?: number;
  commissionAmountPKR?: number;
  commissionPKR?: number;
  commissionPaidPKR?: number;
  commissionStatus: CommissionStatus;
  transactionDate?: string;
  expectedPossessionDate?: string;
  paymentMethod?: 'Pay Order' | 'Bank Transfer' | 'Cash / Cheque';
  paymentMode?: string;
  paymentStatus?: PaymentStatus;
  status: TransactionStatus;
  internalRemarks?: string;
  notes?: string;
  documents?: string[];
  /** Void (audit) trail — void replaces hard delete (P1-1). */
  voided?: boolean;
  voidReason?: string;
  voidedBy?: string;
  voidedAt?: string;
  /** Soft-delete (audit). */
  deletedAt?: string;
  deletedBy?: string;
}

/** Payment ledger row (P1-2): one entry per money movement on a deal. */
export interface PaymentEntry {
  id: string;
  transactionId: string;
  amountPKR: number;
  method: 'Pay Order' | 'Bank Transfer' | 'Cash / Cheque' | 'Online Transfer';
  paidAt: string;
  recordedBy: string;
  note?: string;
}

/** Commission ledger row (P1-3): accrual milestone + payout history per deal. */
export interface CommissionEntry {
  id: string;
  transactionId: string;
  dealerId?: string;
  dealerName?: string;
  amountPKR: number;
  status: 'Pending Accrual' | 'Accrued' | 'Partially Paid' | 'Paid' | 'Cancelled';
  accruedOn?: string;
  paidPKR: number;
  payouts: Array<{ id: string; amountPKR: number; paidAt: string; recordedBy: string; note?: string }>;
  /** Bookkeeping note: agreed rule / reason (not private financial data). */
  note?: string;
}

export type DocumentCategory = 
  | 'Original Allotment File'
  | 'Allotment Order'
  | 'Possession Letter'
  | 'Possession Slip'
  | 'Transfer Order'
  | 'NDC Certificate'
  | 'CNIC Copy'
  | 'Payment Receipt'
  | 'NOC'
  | 'Site Plan'
  | 'Site Demarcation Map'
  | 'Sale Agreement'
  | 'Legal Undertaking'
  | 'Other';

export type DocumentVerificationStatus = 
  | 'Pending Verification'
  | 'Verified'
  | 'Rejected'
  | 'Expired';

export interface PropertyFileItem {
  id: string;
  propertyId?: string;
  propertyTitle?: string;
  plotNumber?: string;
  sector?: string;
  fileNumber?: string;
  documentUrl?: string;
  rejectionReason?: string;
  title: string;
  fileType?: 'pdf' | 'doc' | 'image';
  category: DocumentCategory;
  fileSize?: string;
  url?: string;
  verificationStatus: DocumentVerificationStatus;
  verifiedBy?: string;
  verifiedAt?: string;
  uploadedAt?: string;
  notes?: string;
}

export interface PlotMarker {
  id: string;
  plotNumber: string; // e.g. "R-66"
  sector: string; // e.g. "Sector 63"
  subSector?: string; // e.g. "Block A"
  block?: string;
  street?: string;
  sizeSqYards: number;
  dimensions?: string; // e.g. "24' x 45'"
  roadWidth?: string | number; // e.g. "100ft Road" or 100
  status: PropertyStatus | 'Available' | 'Token Paid' | 'Under Deal' | 'Sold';
  pricePKR?: number;
  roadFacing?: boolean;
  is100ftRoadFacing?: boolean;
  corner?: boolean;
  isCorner?: boolean;
  westOpen?: boolean;
  isWestOpen?: boolean;
  gridCol?: number;
  gridRow?: number;
  propertyId?: string;
  /** Fictional presentation label shown on the public plot map (NOT seller data). */
  markerLabel?: string;
}

export type UserRole = 
  | 'Super Admin'
  | 'Admin'
  | 'Manager'
  | 'Property Dealer'
  | 'Data Entry Operator'
  | 'super_admin'
  | 'admin'
  | 'manager'
  | 'dealer'
  | 'operator';

export interface AuthUser {
  id: string;
  name: string;
  email: string;
  role: UserRole;
  permissions: string[];
  /** Dealer accounts: the dealer profile this user acts as (scoped data). */
  dealerId?: string;
}

export interface ProjectFacility {
  name: string;
  iconName: string;
  description: string;
}

export interface ProjectDocument {
  id: string;
  title: string;
  fileType: 'pdf' | 'doc' | 'image';
  fileSize: string;
  url: string;
}

export interface Project {
  id: string;
  title: string;
  tagline: string;
  category: ProjectCategory;
  location: string;
  sector: string;
  description: string;
  status: ProjectStatus;
  isFeatured: boolean;
  coverImage: string;
  galleryImages: string[];
  videoUrl?: string;
  facilities: ProjectFacility[];
  documents: ProjectDocument[];
  startingPricePKR: number;
  plotSizes: string[];
  masterPlanImage?: string;
  completionYear: string;
  developerName: string;
  createdDate: string;
  /** Soft-delete (audit). */
  deletedAt?: string;
  deletedBy?: string;
}

export interface MediaItem {
  id: string;
  title: string;
  url: string;
  type: 'image' | 'video' | 'pdf' | 'document';
  folder: 'Projects' | 'Properties' | 'General Photos' | 'Videos' | 'Documents' | 'Banners';
  fileSizeKB: number;
  uploadedAt: string;
  usageCount: number;
  dimensions?: string;
  usedInTitles?: string[];
}

export type InquiryStatus = 'Pending' | 'Contacted' | 'In Progress' | 'Resolved';

export interface Inquiry {
  id: string;
  fullName: string;
  email?: string;
  phone: string;
  subject: string;
  message: string;
  preferredPropertyOrProject?: string;
  budgetRange?: string;
  status: InquiryStatus;
  submittedAt: string;
  adminNotes?: string;
  source: 'Hero Search' | 'Property Page' | 'Project Page' | 'Contact Form' | 'WhatsApp';
}

export interface Testimonial {
  id: string;
  clientName: string;
  designation: string;
  quote: string;
  avatar: string;
  propertyPurchased: string;
  rating: number;
  isVerifiedDemo: boolean;
}

export interface CMSConfig {
  agencyName: string;
  tagline: string;
  heroHeadline: string;
  heroSubtitle: string;
  heroBackground: string;
  statsProjectsCount: string;
  statsPropertiesCount: string;
  statsSatisfiedClients: string;
  statsAcresPlanned: string;
  aboutTitle: string;
  aboutDescription: string;
  aboutMission: string;
  aboutVision: string;
  officeAddress: string;
  contactPhone: string;
  contactWhatsApp: string;
  contactEmail: string;
  workingHours: string;
  googleMapEmbedUrl: string;
  facebookUrl: string;
  youtubeUrl: string;
  instagramUrl: string;
  seoTitle: string;
  seoDescription: string;
  noticeBannerText: string;
}

export type ClientThemeId = 'taisartown-navy' | 'samnan-emerald' | 'karachi-obsidian';

export interface ClientTheme {
  id: ClientThemeId;
  name: string;
  agencyName: string;
  tagline: string;
  primaryColor: string; // hex
  primaryAccent: string; // hex gold / emerald / coral
  headerBg: string;
  heroBadge: string;
  description: string;
}

export type DeviceViewport = 'desktop' | 'tablet' | 'mobile';




