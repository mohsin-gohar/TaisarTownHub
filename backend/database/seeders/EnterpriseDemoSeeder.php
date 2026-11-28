<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\AgencyMember;
use App\Models\AgencySubscription;
use App\Models\Inquiry;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Local/demo multi-tenant fixture.
 *
 * The base DatabaseSeeder only creates legacy platform rows (no agency), which
 * leaves every enterprise screen without an agency context. This seeder wires
 * a single verified demo agency and attaches the seeded staff to it so the
 * agency dashboard, staff, vault, CRM, media studio, subscription and finance
 * desks are reachable in a local environment.
 *
 * Idempotent: safe to run repeatedly (updateOrCreate everywhere).
 */
class EnterpriseDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('EnterpriseDemoSeeder must never run in production.');
        }

        $admin = User::where('email', 'admin@taisartownhub.com')->first();
        if (!$admin) {
            return;
        }

        $agency = Agency::updateOrCreate(
            ['id' => 'agency-demo'],
            [
                'name' => 'TaisarTownHub Premier Advisory',
                'slug' => 'taisartownhub-premier-advisory',
                'description' => 'Flagship Scheme 45 advisory desk — verified files, on-ground possession and escrow-backed transfers.',
                'email' => 'hello@premier-advisory.pk',
                'phone' => '+92 321 8292831',
                'whatsapp' => '+92 321 8292831',
                'office_address' => 'Plot 45-C, 100ft Boulevard, Sector 63, Scheme 45',
                'city' => 'Karachi',
                'registration_number' => 'S45-ADV-0001',
                'website' => 'https://taisartownhub.com',
                'status' => 'active',
                'verification_status' => 'verified',
                'verified_at' => now(),
                'owner_user_id' => $admin->id,
            ]
        );

        $memberships = [
            ['admin@taisartownhub.com', 'role_agency_owner'],
            ['manager@taisartownhub.com', 'role_agency_manager'],
            ['dealer@taisartownhub.com', 'role_agent'],
            ['operator@taisartownhub.com', 'role_document_manager'],
        ];
        foreach ($memberships as [$email, $roleId]) {
            $user = User::where('email', $email)->first();
            if (!$user) {
                continue;
            }
            AgencyMember::updateOrCreate(
                ['agency_id' => $agency->id, 'user_id' => $user->id],
                ['role_id' => $roleId, 'status' => 'active']
            );
        }

        // Subscription: prefer the seeded default plan, fall back to any plan.
        $plan = SubscriptionPlan::where('is_default', true)->first()
            ?: SubscriptionPlan::orderBy('sort_order')->first();
        if ($plan) {
            AgencySubscription::updateOrCreate(
                ['agency_id' => $agency->id],
                [
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'starts_at' => now()->startOfMonth(),
                    'ends_at' => now()->addYear(),
                ]
            );
        }

        // Never bulk-claim legacy or user-owned records for a demo tenant.
        // Ownership must come from verified migration evidence or an explicit,
        // reviewed assignment—not from a demo seeder.
        // A few CRM leads so the pipeline desk is not empty.
        $leadSeeds = [
            [
                'id' => 'inq-demo-1',
                'fullName' => 'Hamza Khan',
                'email' => 'hamza.khan@example.com',
                'phone' => '+92 300 1234567',
                'subject' => 'Investment inquiry — Sector 70-D',
                'message' => 'Looking for two 120 sq yd possession plots for overseas investment.',
                'preferredPropertyOrProject' => 'Sector 70-D',
                'budgetRange' => 'PKR 70 - 80 Lacs',
                'status' => 'Pending',
                'lead_status' => 'new',
                'source' => 'Website',
            ],
            [
                'id' => 'inq-demo-2',
                'fullName' => 'Ayesha Siddiqui',
                'email' => 'ayesha.s@example.com',
                'phone' => '+92 321 9876543',
                'subject' => 'Commercial plot — 150ft Boulevard',
                'message' => 'Need a 200 sq yd commercial plot with ground+4 approval.',
                'preferredPropertyOrProject' => 'Sector 50 Commercial',
                'budgetRange' => 'PKR 1.9 - 2.1 Crore',
                'status' => 'Contacted',
                'lead_status' => 'contacted',
                'source' => 'Referral',
            ],
            [
                'id' => 'inq-demo-3',
                'fullName' => 'Usman Traders',
                'email' => 'usman.traders@example.com',
                'phone' => '+92 333 4455667',
                'subject' => 'Farmhouse — Phase 3',
                'message' => 'Interested in an acre farmhouse plot near the canal.',
                'preferredPropertyOrProject' => 'Phase 3 Farmhouses',
                'budgetRange' => 'PKR 1.2 Crore',
                'status' => 'Qualified',
                'lead_status' => 'qualified',
                'source' => 'Walk-in',
            ],
        ];
        foreach ($leadSeeds as $lead) {
            Inquiry::updateOrCreate(
                ['id' => $lead['id']],
                array_merge($lead, ['agency_id' => $agency->id])
            );
        }

        // Do not fabricate a finance transaction against a legacy property/dealer.
        // A dedicated demo-owned property and dealer must exist before a deal is seeded.
    }
}
