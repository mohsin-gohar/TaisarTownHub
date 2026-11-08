import { AgencyVideo } from '../types';

export const initialAgencyVideos: AgencyVideo[] = [
  {
    id: 'vid-1',
    title: 'Sector 70-D (120 Sq. Yds) On-Ground Possession & 40ft Street Demarcation Walkthrough',
    description: 'Exclusive on-site walkthrough by Saleem Siddiqui at Sector 70-D, Block A. Inspecting newly carpeted 40ft roads, underground water lines, K-Electric infrastructure, and immediate possession handover guidelines for 120 sq yard residential plots.',
    videoUrl: '/sample-video.mp4',
    thumbnailUrl: 'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=1000&q=80',
    agencyId: 'deal-1',
    agencyName: 'TaisarTownHub Premier Advisory',
    dealerName: 'Saleem Siddiqui',
    dealerAvatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=200&q=80',
    phase: 'Phase 1',
    sector: 'Sector 70-D',
    duration: '3:45',
    uploadedAt: '2 days ago',
    likes: 148,
    views: 1240,
    tags: ['Possession', '120 Sq Yds', 'Phase 1', 'Demarcation'],
    comments: [
      {
        id: 'comm-101',
        userName: 'Hamza Khan (Dubai Overseas Investor)',
        comment: 'Very clear explanation Saleem sb. Are water boring permissions granted right away after transfer?',
        createdAt: '1 day ago',
        isDealer: false
      },
      {
        id: 'comm-102',
        userName: 'Saleem Siddiqui',
        comment: 'Yes Hamza bhai, sweet water sub-soil is available at 180-220ft in Sector 70-D. Bore permission is certified along with the building plan.',
        createdAt: '18 hours ago',
        isDealer: true
      },
      {
        id: 'comm-103',
        userName: 'Rashid Minhas',
        comment: 'Great coverage. Sector 70-D is currently yielding the highest capital appreciation in Phase 1.',
        createdAt: '14 hours ago',
        isDealer: true
      }
    ]
  },
  {
    id: 'vid-2',
    title: 'Main 150ft Commercial Boulevard — Sector 50 High-Rise Arcade Opportunities',
    description: 'Kamran Haider explores prime commercial plots on the 150ft wide central boulevard of Sector 50. Detailed breakdown of MDA commercial FAR regulations, basement parking mandates, and expected rental yields.',
    videoUrl: '/sample-video.mp4',
    thumbnailUrl: 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=1000&q=80',
    agencyId: 'deal-2',
    agencyName: 'Al-Samnan Elite Estates & Builders',
    dealerName: 'Kamran Haider',
    dealerAvatar: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=200&q=80',
    phase: 'Phase 1',
    sector: 'Sector 50',
    duration: '4:20',
    uploadedAt: '4 days ago',
    likes: 92,
    views: 890,
    tags: ['Commercial', 'Boulevard', 'Phase 1', 'High Yield'],
    comments: [
      {
        id: 'comm-201',
        userName: 'Naveed Akhtar',
        comment: 'What is the ground plus floors permission on 150ft road?',
        createdAt: '3 days ago',
        isDealer: false
      },
      {
        id: 'comm-202',
        userName: 'Kamran Haider',
        comment: 'Commercial plots on 150ft road allow Ground + 4 floors as per standard MDA bylaws, expandable with compounding fees.',
        createdAt: '2 days ago',
        isDealer: true
      }
    ]
  },
  {
    id: 'vid-3',
    title: 'Phase 2 Ground Reality — Balloted Sectors 78 & 80 Development Progress',
    description: 'Bilal Farooqui from Bismillah Associates visits Sector 78 and Sector 80 in Phase 2. Detailed update on land leveling, survey stone markings, electrical grid posts, and upcoming possession announcements.',
    videoUrl: '/sample-video.mp4',
    thumbnailUrl: 'https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&fit=crop&w=1000&q=80',
    agencyId: 'deal-3',
    agencyName: 'Bismillah Associates & Property Linkers',
    dealerName: 'Bilal Farooqui',
    dealerAvatar: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&w=200&q=80',
    phase: 'Phase 2',
    sector: 'Sector 78',
    duration: '5:15',
    uploadedAt: '5 days ago',
    likes: 210,
    views: 1850,
    tags: ['Phase 2', 'Sector 78', 'Affordable', 'Installments'],
    comments: [
      {
        id: 'comm-301',
        userName: 'Farrukh Siddiqui',
        comment: 'Are files in Sector 80 computerised or manual paper receipts?',
        createdAt: '4 days ago',
        isDealer: false
      },
      {
        id: 'comm-302',
        userName: 'Bilal Farooqui',
        comment: 'All files we handle are 100% computerized with MDA barcode confirmation and biometric eligibility.',
        createdAt: '3 days ago',
        isDealer: true
      }
    ]
  },
  {
    id: 'vid-4',
    title: 'NADRA Biometric Verification & File Transfer Process Demystified',
    description: 'Zubair Ahmed Khan from Karachi Modern Realty guides you through the exact legal steps required to verify your Scheme 45 allotment file, clear NDC dues, and undergo biometric transfer at MDA Headquarters.',
    videoUrl: '/sample-video.mp4',
    thumbnailUrl: 'https://images.unsplash.com/photo-1450133064473-71024230f91b?auto=format&fit=crop&w=1000&q=80',
    agencyId: 'deal-4',
    agencyName: 'Karachi Modern Realty Group',
    dealerName: 'Zubair Ahmed Khan',
    dealerAvatar: 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?auto=format&fit=crop&w=200&q=80',
    phase: 'Phase 1',
    sector: 'Legal Transfer Desk',
    duration: '6:02',
    uploadedAt: '1 week ago',
    likes: 318,
    views: 3120,
    tags: ['Biometric', 'MDA Clearance', 'Legal', 'Transfer'],
    comments: [
      {
        id: 'comm-401',
        userName: 'Tariq Mehmood',
        comment: 'Crucial information. Every buyer must ensure biometric verification before releasing the final pay order.',
        createdAt: '6 days ago',
        isDealer: true
      },
      {
        id: 'comm-402',
        userName: 'Asim Ghafoor',
        comment: 'Very helpful brother. How many days does the official transfer letter take after biometric?',
        createdAt: '5 days ago',
        isDealer: false
      },
      {
        id: 'comm-403',
        userName: 'Zubair Ahmed Khan',
        comment: 'Normal processing is 15-20 working days. Fast-track urgent verification takes approximately 7 days.',
        createdAt: '5 days ago',
        isDealer: true
      }
    ]
  },
  {
    id: 'vid-5',
    title: 'Ready 120 Sq. Yd. Modern Duplex Villa Construction & Interior Walkthrough',
    description: 'Haji Abdul Rehman showcases an architecturally finished 120 sq. yd single-unit duplex villa in Sector 71. Featuring Spanish tiles, fall ceilings, solar inverter conduit, and imported sanitary fittings.',
    videoUrl: '/sample-video.mp4',
    thumbnailUrl: 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1000&q=80',
    agencyId: 'deal-6',
    agencyName: 'Madina Real Estate & Builders',
    dealerName: 'Haji Abdul Rehman',
    dealerAvatar: 'https://images.unsplash.com/photo-1566492031773-4f4e44671857?auto=format&fit=crop&w=200&q=80',
    phase: 'Phase 1',
    sector: 'Sector 71',
    duration: '3:15',
    uploadedAt: '1 week ago',
    likes: 175,
    views: 1450,
    tags: ['Duplex Villa', 'Constructed Home', 'Sector 71', 'Luxury Interior'],
    comments: [
      {
        id: 'comm-501',
        userName: 'Dr. Shahzad',
        comment: 'What is the demand for this ready bungalow?',
        createdAt: '6 days ago',
        isDealer: false
      },
      {
        id: 'comm-502',
        userName: 'Haji Abdul Rehman',
        comment: 'Demand is PKR 1.45 Crore with full fixtures and immediate move-in key handover.',
        createdAt: '5 days ago',
        isDealer: true
      }
    ]
  },
  {
    id: 'vid-6',
    title: 'Sector 84 Farmhouse Enclave Drone Survey & Mountain Valley Scenic Views',
    description: 'Chaudhry Waqas takes you on an aerial drone flight over Sector 84 Farmhouse Enclave in Phase 3. Lush open vistas, boundary walls, security checkpoints, and organic agro farming projects.',
    videoUrl: '/sample-video.mp4',
    thumbnailUrl: 'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?auto=format&fit=crop&w=1000&q=80',
    agencyId: 'deal-9',
    agencyName: 'Golden Gate Realtors Scheme 45',
    dealerName: 'Chaudhry Waqas',
    dealerAvatar: 'https://images.unsplash.com/photo-1522075469751-3a6694fb2f61?auto=format&fit=crop&w=200&q=80',
    phase: 'Phase 3',
    sector: 'Sector 84',
    duration: '4:50',
    uploadedAt: '2 weeks ago',
    likes: 198,
    views: 2280,
    tags: ['Farmhouses', 'Phase 3', 'Drone Tour', 'Agro Land'],
    comments: [
      {
        id: 'comm-601',
        userName: 'Salman Pirzada',
        comment: 'Stunning landscape. Is sweet water bore successful here?',
        createdAt: '1 week ago',
        isDealer: false
      },
      {
        id: 'comm-602',
        userName: 'Chaudhry Waqas',
        comment: 'Yes sir, sweet water aquifer is active at 160ft depth. Multiple farmhouses already have functional swimming pools and fruit orchards.',
        createdAt: '1 week ago',
        isDealer: true
      }
    ]
  }
];
