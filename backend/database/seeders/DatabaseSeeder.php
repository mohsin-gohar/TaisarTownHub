<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BusinessRecord;
use App\Models\Dealer;
use App\Models\User;
use App\Models\Property;
use App\Models\AgencyVideo;
use App\Models\VideoComment;
use App\Models\Project;
use App\Models\Inquiry;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedBusinessRecords();

        // 1. Seed 12 Scheme 45 Verified Agencies
        $dealers = [
            [
                'id' => 'deal-1',
                'fullName' => 'Saleem Siddiqui',
                'cnic' => '42101-9235180-1',
                'cnicMasked' => '42101-*******-1',
                'phone' => '+92 321 8292831',
                'email' => 'saleem@taisartownhub.com',
                'agencyAffiliation' => 'TaisarTownHub Premier Advisory',
                'agencyOrBranch' => 'TaisarTownHub Premier Advisory',
                'role' => 'Senior Land Specialist & Scheme 45 Lead',
                'commissionType' => 'Fixed',
                'commissionRate' => 20000,
                'totalEarnedPKR' => 280000,
                'pendingCommissionPKR' => 20000,
                'assignedPropertiesCount' => 4,
                'completedDealsCount' => 14,
                'dealsClosed' => 14,
                'status' => 'Active',
                'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=200&q=80',
            ],
            [
                'id' => 'deal-2',
                'fullName' => 'Kamran Haider',
                'cnic' => '42301-1836295-4',
                'cnicMasked' => '42201-*******-5',
                'phone' => '+92 321 8292832',
                'email' => 'kamran@taisartownhub.com',
                'agencyAffiliation' => 'Al-Samnan Elite Estates & Builders',
                'agencyOrBranch' => 'Al-Samnan Elite Estates & Builders',
                'role' => 'Commercial Projects & 150ft Boulevard Specialist',
                'commissionType' => 'Percentage',
                'commissionRate' => 1,
                'totalEarnedPKR' => 450000,
                'pendingCommissionPKR' => 0,
                'assignedPropertiesCount' => 3,
                'completedDealsCount' => 11,
                'dealsClosed' => 11,
                'status' => 'Active',
                'avatar' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=200&q=80',
            ],
            [
                'id' => 'deal-3',
                'fullName' => 'Bilal Farooqui',
                'cnic' => '42301-2947163-9',
                'cnicMasked' => '42301-*******-9',
                'phone' => '+92 300 7719283',
                'email' => 'bilal@taisartownhub.com',
                'agencyAffiliation' => 'Bismillah Associates & Property Linkers',
                'agencyOrBranch' => 'Bismillah Associates & Property Linkers',
                'role' => 'Phase 2 Affordable Allotments Lead',
                'commissionType' => 'Fixed',
                'commissionRate' => 15000,
                'totalEarnedPKR' => 195000,
                'pendingCommissionPKR' => 15000,
                'assignedPropertiesCount' => 3,
                'completedDealsCount' => 13,
                'dealsClosed' => 13,
                'status' => 'Active',
                'avatar' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&w=200&q=80',
            ],
            [
                'id' => 'deal-4',
                'fullName' => 'Zubair Ahmed Khan',
                'cnic' => '42401-5048391-3',
                'cnicMasked' => '42401-*******-3',
                'phone' => '+92 315 2891901',
                'email' => 'zubair@taisartownhub.com',
                'agencyAffiliation' => 'Karachi Modern Realty Group',
                'agencyOrBranch' => 'Karachi Modern Realty Group',
                'role' => 'Biometric Documentation & Transfer Specialist',
                'commissionType' => 'Fixed',
                'commissionRate' => 25000,
                'totalEarnedPKR' => 350000,
                'pendingCommissionPKR' => 25000,
                'assignedPropertiesCount' => 3,
                'completedDealsCount' => 16,
                'dealsClosed' => 16,
                'status' => 'Active',
                'avatar' => 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?auto=format&fit=crop&w=200&q=80',
            ],
            [
                'id' => 'deal-5',
                'fullName' => 'Rashid Minhas',
                'cnic' => '42201-6789123-5',
                'cnicMasked' => '42201-*******-5',
                'phone' => '+92 333 5192840',
                'email' => 'rashid.shaheen@taisartownhub.com',
                'agencyAffiliation' => 'Shaheen Estate & Investment Consultants',
                'agencyOrBranch' => 'Shaheen Estate & Investment Consultants',
                'role' => 'Overseas Land Banking & Sector 70-D Specialist',
                'commissionType' => 'Fixed',
                'commissionRate' => 20000,
                'totalEarnedPKR' => 220000,
                'pendingCommissionPKR' => 0,
                'assignedPropertiesCount' => 2,
                'completedDealsCount' => 10,
                'dealsClosed' => 10,
                'status' => 'Active',
                'avatar' => 'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&w=200&q=80',
            ],
            [
                'id' => 'deal-6',
                'fullName' => 'Haji Abdul Rehman',
                'cnic' => '42101-3829104-7',
                'cnicMasked' => '42101-*******-7',
                'phone' => '+92 322 4910283',
                'email' => 'madina.estate@taisartownhub.com',
                'agencyAffiliation' => 'Madina Real Estate & Builders',
                'agencyOrBranch' => 'Madina Real Estate & Builders',
                'role' => 'Residential Plot Files & Ready Villa Construction',
                'commissionType' => 'Percentage',
                'commissionRate' => 1,
                'totalEarnedPKR' => 310000,
                'pendingCommissionPKR' => 20000,
                'assignedPropertiesCount' => 3,
                'completedDealsCount' => 15,
                'dealsClosed' => 15,
                'status' => 'Active',
                'avatar' => 'https://images.unsplash.com/photo-1566492031773-4f4e44671857?auto=format&fit=crop&w=200&q=80',
            ],
            [
                'id' => 'deal-7',
                'fullName' => 'Engr. Faisal Mehmood',
                'cnic' => '42501-8392018-2',
                'cnicMasked' => '42501-*******-2',
                'phone' => '+92 345 8829104',
                'email' => 'faisal.apex@taisartownhub.com',
                'agencyAffiliation' => 'Apex Horizon Property Advisors',
                'agencyOrBranch' => 'Apex Horizon Property Advisors',
                'role' => 'Town Planning & Demarcation Architecture Lead',
                'commissionType' => 'Fixed',
                'commissionRate' => 25000,
                'totalEarnedPKR' => 175000,
                'pendingCommissionPKR' => 0,
                'assignedPropertiesCount' => 2,
                'completedDealsCount' => 8,
                'dealsClosed' => 8,
                'status' => 'Active',
                'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80',
            ],
            [
                'id' => 'deal-8',
                'fullName' => 'Syed Raza Hussain',
                'cnic' => '42000-1928471-3',
                'cnicMasked' => '42000-*******-3',
                'phone' => '+92 301 9283746',
                'email' => 'raza.legal@taisartownhub.com',
                'agencyAffiliation' => 'Al-Raza Estate & Legal Document Desk',
                'agencyOrBranch' => 'Al-Raza Estate & Legal Document Desk',
                'role' => 'Certified Title Verifier & Allotment Search Specialist',
                'commissionType' => 'Fixed',
                'commissionRate' => 20000,
                'totalEarnedPKR' => 420000,
                'pendingCommissionPKR' => 20000,
                'assignedPropertiesCount' => 3,
                'completedDealsCount' => 21,
                'dealsClosed' => 21,
                'status' => 'Active',
                'avatar' => 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?auto=format&fit=crop&w=200&q=80',
            ],
            [
                'id' => 'deal-9',
                'fullName' => 'Chaudhry Waqas',
                'cnic' => '42101-7291048-9',
                'cnicMasked' => '42101-*******-9',
                'phone' => '+92 312 7748192',
                'email' => 'waqas.goldengate@taisartownhub.com',
                'agencyAffiliation' => 'Golden Gate Realtors Scheme 45',
                'agencyOrBranch' => 'Golden Gate Realtors Scheme 45',
                'role' => 'Phase 3 Farmhouses & Agro Land Lead',
                'commissionType' => 'Percentage',
                'commissionRate' => 1.5,
                'totalEarnedPKR' => 390000,
                'pendingCommissionPKR' => 35000,
                'assignedPropertiesCount' => 2,
                'completedDealsCount' => 9,
                'dealsClosed' => 9,
                'status' => 'Active',
                'avatar' => 'https://images.unsplash.com/photo-1522075469751-3a6694fb2f61?auto=format&fit=crop&w=200&q=80',
            ],
            [
                'id' => 'deal-10',
                'fullName' => 'Muhammad Usman',
                'cnic' => '42301-4491028-1',
                'cnicMasked' => '42301-*******-1',
                'phone' => '+92 334 2918475',
                'email' => 'usman.subhan@taisartownhub.com',
                'agencyAffiliation' => 'Subhan Allah Properties & File Transfer',
                'agencyOrBranch' => 'Subhan Allah Properties & File Transfer',
                'role' => '120 Sq Yd Allotment Orders & NDC Processing Lead',
                'commissionType' => 'Fixed',
                'commissionRate' => 15000,
                'totalEarnedPKR' => 240000,
                'pendingCommissionPKR' => 15000,
                'assignedPropertiesCount' => 2,
                'completedDealsCount' => 14,
                'dealsClosed' => 14,
                'status' => 'Active',
                'avatar' => 'https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?auto=format&fit=crop&w=200&q=80',
            ],
            [
                'id' => 'deal-11',
                'fullName' => 'Gohar Abbas',
                'cnic' => '42401-9928103-6',
                'cnicMasked' => '42401-*******-6',
                'phone' => '+92 313 8819203',
                'email' => 'gohar.sons@taisartownhub.com',
                'agencyAffiliation' => 'Gohar & Sons Prime Real Estate',
                'agencyOrBranch' => 'Gohar & Sons Prime Real Estate',
                'role' => 'West Open & Corner Plots Specialist',
                'commissionType' => 'Fixed',
                'commissionRate' => 20000,
                'totalEarnedPKR' => 360000,
                'pendingCommissionPKR' => 20000,
                'assignedPropertiesCount' => 3,
                'completedDealsCount' => 17,
                'dealsClosed' => 17,
                'status' => 'Active',
                'avatar' => 'https://images.unsplash.com/photo-1501196354995-cbb51c65aaea?auto=format&fit=crop&w=200&q=80',
            ],
            [
                'id' => 'deal-12',
                'fullName' => 'Tariq Mehmood',
                'cnic' => '42101-5582910-4',
                'cnicMasked' => '42101-*******-4',
                'phone' => '+92 321 9918234',
                'email' => 'tariq.malir@taisartownhub.com',
                'agencyAffiliation' => 'Malir Cantt & Taisar Linkers',
                'agencyOrBranch' => 'Malir Cantt & Taisar Linkers',
                'role' => 'Malir Expressway & Scheme 45 Connectivity Advisor',
                'commissionType' => 'Percentage',
                'commissionRate' => 1,
                'totalEarnedPKR' => 280000,
                'pendingCommissionPKR' => 0,
                'assignedPropertiesCount' => 2,
                'completedDealsCount' => 12,
                'dealsClosed' => 12,
                'status' => 'Active',
                'avatar' => 'https://images.unsplash.com/photo-1492562080023-ab3db95bfbce?auto=format&fit=crop&w=200&q=80',
            ],
        ];

        foreach ($dealers as $d) {
            Dealer::updateOrCreate(['id' => $d['id']], $d);
        }

        // 2. Seed Key Properties
        $properties = [
            [
                'id' => 'prop-r66',
                'plotNumber' => 'R-66',
                'fileNumber' => 'MDA-63-R66-45',
                'title' => 'Plot R-66 (120 Sq. Yds) — 100ft Road Facing, Sector 63',
                'description' => 'Prime 120 square yard residential plot based on verified handwritten record specifications. Features 24-foot street frontage by 45-foot depth, directly facing the prestigious 100ft arterial boulevard in Sector 63.',
                'pricePKR' => 3500000,
                'formattedPrice' => 'PKR 35 Lacs',
                'location' => 'Plot R-66, 100ft Road, Sector 63, Block A, Taiser Town Scheme 45, Karachi',
                'phase' => 'Phase 2',
                'sector' => 'Sector 63',
                'block' => 'Block A',
                'sizeSqYards' => 120,
                'roadWidthFeet' => 100,
                'type' => 'Residential Plot',
                'status' => 'Available',
                'isFeatured' => true,
                'possessionReady' => true,
                'mdaApproved' => true,
                'westOpen' => true,
                'cornerPlot' => false,
                'parkFacing' => false,
                'mainBoulevard' => true,
                'images' => [
                    'https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&fit=crop&w=1000&q=80',
                    'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=1000&q=80'
                ],
                'features' => ['100ft Road Facing', '24ft Frontage x 45ft Depth', 'MDA Computerized Allotment', 'Leveled Land', 'Ready for Biometric Transfer'],
                'contactPerson' => 'Saleem Siddiqui',
                'contactPhone' => '+92 321 8292831',
                'assignedDealerId' => 'deal-1',
            ],
            [
                'id' => 'prop-1',
                'plotNumber' => 'P-112',
                'fileNumber' => 'MDA-70D-112',
                'title' => '120 Sq. Yd. Prime West-Open Residential Plot',
                'description' => 'Exceptional 120 square yard plot positioned in the most sought-after Sector 70-D. West open orientation guarantees natural sea-breeze airflow during Karachi summers.',
                'pricePKR' => 3600000,
                'formattedPrice' => 'PKR 36 Lacs',
                'location' => 'Sector 70-D, Street 14, Taiser Town Scheme 45, Karachi',
                'phase' => 'Phase 1',
                'sector' => 'Sector 70-D',
                'sizeSqYards' => 120,
                'type' => 'Residential Plot',
                'status' => 'Hot Deal',
                'isFeatured' => true,
                'possessionReady' => true,
                'mdaApproved' => true,
                'westOpen' => true,
                'cornerPlot' => false,
                'parkFacing' => false,
                'mainBoulevard' => false,
                'images' => [
                    'https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&fit=crop&w=1000&q=80'
                ],
                'features' => ['West Open', '40ft Carpeted Street', 'MDA Verified File', 'Ready for Construction'],
                'contactPerson' => 'Saleem Siddiqui',
                'contactPhone' => '+92 321 8292831',
                'assignedDealerId' => 'deal-1',
            ],
            [
                'id' => 'prop-2',
                'plotNumber' => 'CP-44',
                'fileNumber' => 'MDA-71-CP44',
                'title' => '240 Sq. Yd. Corner Park-Facing Executive Plot',
                'description' => 'Prestigious corner plot facing a 2-acre green municipal park. Ideal for building a luxury duplex or standalone bungalow with dual street access in Sector 71.',
                'pricePKR' => 7800000,
                'formattedPrice' => 'PKR 78 Lacs',
                'location' => 'Sector 71, Block B, Taiser Town Scheme 45, Karachi',
                'phase' => 'Phase 1',
                'sector' => 'Sector 71',
                'sizeSqYards' => 240,
                'type' => 'Residential Plot',
                'status' => 'Available',
                'isFeatured' => true,
                'possessionReady' => true,
                'mdaApproved' => true,
                'westOpen' => true,
                'cornerPlot' => true,
                'parkFacing' => true,
                'images' => [
                    'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=1000&q=80'
                ],
                'features' => ['Corner Plot', 'Direct Park Facing', '60ft Wide Road', 'Immediate Physical Possession'],
                'contactPerson' => 'Rashid Minhas',
                'contactPhone' => '+92 333 5192840',
                'assignedDealerId' => 'deal-5',
            ],
            [
                'id' => 'prop-3',
                'plotNumber' => 'C-80',
                'fileNumber' => 'MDA-50-C80',
                'title' => '200 Sq. Yd. Main Boulevard Commercial Plot',
                'description' => 'High-visibility commercial plot on the bustling 150ft main arterial artery connecting Taiser Town to Northern Bypass.',
                'pricePKR' => 19500000,
                'formattedPrice' => 'PKR 1.95 Crore',
                'location' => 'Main 150ft Boulevard, Sector 50, Taiser Town, Karachi',
                'phase' => 'Phase 1',
                'sector' => 'Sector 50',
                'sizeSqYards' => 200,
                'type' => 'Commercial Plot',
                'status' => 'Available',
                'isFeatured' => true,
                'images' => [
                    'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=1000&q=80'
                ],
                'features' => ['150ft Main Boulevard', 'Ground + 4 Storey Approval', 'Commercial FAR Cleared'],
                'contactPerson' => 'Kamran Haider',
                'contactPhone' => '+92 321 8292832',
                'assignedDealerId' => 'deal-2',
            ]
        ];

        foreach ($properties as $p) {
            Property::updateOrCreate(['id' => $p['id']], $p);
        }

        // 3. Seed Agency Videos & Comments
        $videos = [
            [
                'id' => 'vid-1',
                'title' => 'Sector 70-D (120 Sq. Yds) On-Ground Possession & 40ft Street Demarcation Walkthrough',
                'description' => 'Exclusive on-site walkthrough by Saleem Siddiqui at Sector 70-D, Block A. Inspecting newly carpeted 40ft roads, underground water lines, K-Electric infrastructure, and immediate possession handover guidelines.',
                'videoUrl' => '/sample-video.mp4',
                'thumbnailUrl' => 'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=1000&q=80',
                'agencyId' => 'deal-1',
                'agencyName' => 'TaisarTownHub Premier Advisory',
                'dealerName' => 'Saleem Siddiqui',
                'dealerAvatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=200&q=80',
                'phase' => 'Phase 1',
                'sector' => 'Sector 70-D',
                'duration' => '3:45',
                'likes' => 148,
                'views' => 1240,
                'tags' => ['Possession', '120 Sq Yds', 'Phase 1', 'Demarcation'],
            ],
            [
                'id' => 'vid-2',
                'title' => 'Main 150ft Commercial Boulevard — Sector 50 High-Rise Arcade Opportunities',
                'description' => 'Kamran Haider explores prime commercial plots on the 150ft wide central boulevard of Sector 50.',
                'videoUrl' => '/sample-video.mp4',
                'thumbnailUrl' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=1000&q=80',
                'agencyId' => 'deal-2',
                'agencyName' => 'Al-Samnan Elite Estates & Builders',
                'dealerName' => 'Kamran Haider',
                'dealerAvatar' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=200&q=80',
                'phase' => 'Phase 1',
                'sector' => 'Sector 50',
                'duration' => '4:20',
                'likes' => 92,
                'views' => 890,
                'tags' => ['Commercial', 'Boulevard', 'Phase 1'],
            ],
            [
                'id' => 'vid-3',
                'title' => 'Phase 2 Ground Reality — Balloted Sectors 78 & 80 Development Progress',
                'description' => 'Bilal Farooqui from Bismillah Associates visits Sector 78 and Sector 80 in Phase 2.',
                'videoUrl' => '/sample-video.mp4',
                'thumbnailUrl' => 'https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&fit=crop&w=1000&q=80',
                'agencyId' => 'deal-3',
                'agencyName' => 'Bismillah Associates & Property Linkers',
                'dealerName' => 'Bilal Farooqui',
                'dealerAvatar' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&w=200&q=80',
                'phase' => 'Phase 2',
                'sector' => 'Sector 78',
                'duration' => '5:15',
                'likes' => 210,
                'views' => 1850,
                'tags' => ['Phase 2', 'Sector 78', 'Affordable'],
            ]
        ];

        foreach ($videos as $v) {
            AgencyVideo::updateOrCreate(['id' => $v['id']], $v);
        }

        // Comments
        VideoComment::updateOrCreate(['id' => 'comm-101'], [
            'agency_video_id' => 'vid-1',
            'userName' => 'Hamza Khan (Dubai Overseas Investor)',
            'comment' => 'Very clear explanation Saleem sb. Are water boring permissions granted right away after transfer?',
            'isDealer' => false
        ]);

        VideoComment::updateOrCreate(['id' => 'comm-102'], [
            'agency_video_id' => 'vid-1',
            'userName' => 'Saleem Siddiqui',
            'comment' => 'Yes Hamza bhai, sweet water sub-soil is available at 180-220ft in Sector 70-D. Bore permission is certified along with the building plan.',
            'isDealer' => true
        ]);

        // 4. Seed Inquiries
        Inquiry::updateOrCreate(['id' => 'inq-1'], [
            'fullName' => 'Muhammad Tariq Chaudhry',
            'email' => 'tariq.chaudhry@gmail.com',
            'phone' => '+92 300 2489102',
            'subject' => 'Inquiry regarding 120 Sq. Yd. Plot in Sector 70-D',
            'message' => 'Salam, I am an overseas Pakistani currently residing in Dubai. I am keen to invest in 2 residential plots in Sector 70-D. Can your team verify if physical possession is ready?',
            'preferredPropertyOrProject' => '120 Sq. Yd. Prime West-Open Residential Plot',
            'budgetRange' => 'PKR 70 - 80 Lacs',
            'status' => 'Pending',
            'adminNotes' => 'Overseas buyer from UAE. Very interested in possession plots.',
            'source' => 'Web Portal'
        ]);
    }

    /**
     * Load every business collection into MySQL. Existing rows are kept so a
     * later db:seed does not wipe staff edits. Empty collections are filled.
     */
    private function seedBusinessRecords(): void
    {
        $path = database_path('seed-data/workspace.json');
        if (!is_file($path)) {
            return;
        }

        $data = json_decode(file_get_contents($path), true);
        if (!is_array($data)) {
            return;
        }

        foreach ($data['staff'] ?? [] as $staff) {
            if (empty($staff['email']) || empty($staff['password'])) {
                continue;
            }
            User::updateOrCreate(
                ['email' => $staff['email']],
                [
                    'name' => $staff['name'],
                    'password' => $staff['password'],
                    'role' => $staff['role'] ?? 'operator',
                    'dealer_id' => $staff['dealer_id'] ?? null,
                ]
            );
        }

        $lists = [
            'projects', 'properties', 'dealers', 'sellers', 'buyers',
            'transactions', 'payments', 'commissions', 'files', 'plots',
            'media', 'videos', 'inquiries', 'testimonials',
        ];

        foreach ($lists as $collection) {
            if (BusinessRecord::where('collection', $collection)->exists()) {
                continue;
            }
            foreach ($data[$collection] ?? [] as $row) {
                if (!is_array($row) || empty($row['id'])) {
                    continue;
                }
                BusinessRecord::put($collection, (string) $row['id'], $row);
            }
        }

        if (!BusinessRecord::where('collection', 'cms')->where('record_id', 'default')->exists() && is_array($data['cms'] ?? null)) {
            BusinessRecord::put('cms', 'default', $data['cms']);
        }
    }
}
