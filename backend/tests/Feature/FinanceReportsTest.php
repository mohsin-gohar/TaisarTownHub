<?php

namespace Tests\Feature;

use App\Models\Commission;
use App\Models\Expense;
use App\Models\Inquiry;
use App\Models\SubscriptionInvoice;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use Illuminate\Support\Str;
use Tests\Feature\EnterpriseTestCase;

/**
 * G-12 — date-filtered financial reports, computed server-side from the real
 * ledger. Nothing is hardcoded; the window excludes out-of-range rows.
 */
class FinanceReportsTest extends EnterpriseTestCase
{
    private function makeDeal(string $agencyId, array $attrs = []): Transaction
    {
        return Transaction::create(array_merge([
            'id' => 'txn_' . \Illuminate\Support\Str::random(10),
            'dealNumber' => 'DEAL-' . \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(6)),
            'propertyId' => null,
            'propertyTitle' => 'Report Plot ' . \Illuminate\Support\Str::random(4),
            'sellerName' => 'Seller ' . \Illuminate\Support\Str::random(4),
            'buyerName' => 'Buyer ' . \Illuminate\Support\Str::random(4),
            'agency_id' => $agencyId,
            'transactionType' => 'sale',
            'agreementAmountPKR' => 500000,
            'dealStatus' => 'Active',
            'created_at' => now(),
        ], $attrs));
    }

    public function test_agency_report_is_permission_gated(): void
    {
        [$noReport] = $this->agencyUser(['finance.view'], 'agent');
        $this->getJson('/api/agency/reports', $this->authHeaders($this->tokenFor($noReport)))
            ->assertStatus(403);

        [$user] = $this->agencyUser(['finance.view', 'finance.view_reports'], 'accountant');
        $this->getJson('/api/agency/reports', $this->authHeaders($this->tokenFor($user)))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('period.from', now()->subDays(29)->toDateString());
    }

    public function test_report_numbers_are_exact_for_the_requested_window(): void
    {
        [$user, $agency] = $this->agencyUser(['finance.view', 'finance.view_reports', 'finance.manage_payments'], 'accountant');

        // Today: two deals (one voided), a payment, an expense, commissions.
        $deal = $this->makeDeal($agency->id, ['agreementAmountPKR' => 500000]);
        $this->makeDeal($agency->id, ['agreementAmountPKR' => 777000, 'voided_at' => now(), 'void_reason' => 'Test void']);

        TransactionPayment::create([
            'agency_id' => $agency->id,
            'transaction_id' => $deal->id,
            'amount' => 200000,
            'type' => 'advance',
            'method' => 'bank_transfer',
            'received_at' => now(),
        ]);

        Commission::create([
            'agency_id' => $agency->id,
            'transaction_id' => $deal->id,
            'beneficiary_type' => 'dealer',
            'amount' => 3000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);
        Commission::create([
            'agency_id' => $agency->id,
            'transaction_id' => $deal->id,
            'beneficiary_type' => 'dealer',
            'amount' => 1200,
            'status' => 'accrued',
            'accrued_at' => now(),
        ]);

        Expense::create([
            'id' => 'exp_' . \Illuminate\Support\Str::random(10),
            'agency_id' => $agency->id,
            'expense_date' => now()->toDateString(),
            'category' => 'office',
            'amount_pkr' => 5000,
        ]);
        Expense::create([
            'id' => 'exp_' . \Illuminate\Support\Str::random(10),
            'agency_id' => $agency->id,
            'expense_date' => now()->toDateString(),
            'category' => 'marketing',
            'amount_pkr' => 2500,
        ]);

        $report = $this->getJson('/api/agency/reports?from=' . now()->toDateString() . '&to=' . now()->toDateString(), $this->authHeaders($this->tokenFor($user)))
            ->assertOk();

        $this->assertSame(1, $report->json('deals.count'), 'voided deal excluded from active count');
        $this->assertSame(500000.0, (float) $report->json('deals.agreed_pkr'), 'voided volume excluded');
        $this->assertSame(1, $report->json('deals.voided_count'));
        $this->assertSame(200000.0, (float) $report->json('collected_pkr'));
        $this->assertSame(1, $report->json('payments_count'));
        $this->assertSame(3000.0, (float) $report->json('commissions.paid_pkr'));
        $this->assertSame(1200.0, (float) $report->json('commissions.pending_pkr'));
        $this->assertSame(7500, $report->json('expenses.total_pkr'));
        $this->assertSame(2, count($report->json('expenses.by_category')));
        $this->assertSame(192500.0, (float) $report->json('net_pkr')); // 200000 - 7500

        // A window that excludes everything reports zeros.
        $empty = $this->getJson('/api/agency/reports?from=2020-01-01&to=2020-01-31', $this->authHeaders($this->tokenFor($user)))
            ->assertOk();
        $this->assertSame(0, $empty->json('deals.count'));
        $this->assertSame(0.0, (float) $empty->json('collected_pkr'));
        $this->assertSame(0, $empty->json('expenses.total_pkr'));

        // Tenant scoping: another agency's rows never leak into the report.
        [$foreign, $foreignAgency] = $this->agencyUser(['finance.view', 'finance.view_reports'], 'accountant');
        $this->makeDeal($foreignAgency->id, ['agreementAmountPKR' => 999999]);
        $other = $this->getJson('/api/agency/reports?from=' . now()->toDateString() . '&to=' . now()->toDateString(), $this->authHeaders($this->tokenFor($foreign)))
            ->assertOk();
        $this->assertSame(999999.0, (float) $other->json('deals.agreed_pkr'));
        $this->assertSame(0.0, (float) $other->json('collected_pkr'));
    }

    public function test_agency_report_never_includes_platform_or_foreign_agency_totals(): void
    {
        [$user, $agency] = $this->agencyUser(['finance.view', 'finance.view_reports'], 'accountant');
        [, $foreign] = $this->agencyUser(['finance.view', 'finance.view_reports'], 'accountant');

        // Foreign-agency activity in the same window.
        $this->makeDeal($foreign->id, ['agreementAmountPKR' => 900000]);
        $this->makePublicProperty($foreign);
        Inquiry::create([
            'id' => 'inq-' . Str::uuid(),
            'fullName' => 'Foreign Lead',
            'phone' => '03001112222',
            'message' => 'Foreign inquiry',
            'agency_id' => $foreign->id,
        ]);
        SubscriptionInvoice::create([
            'id' => (string) Str::uuid(),
            'invoice_number' => SubscriptionInvoice::nextNumber(),
            'agency_id' => $foreign->id,
            'amount_pkr' => 7000,
            'total_pkr' => 7000,
            'currency' => 'PKR',
            'status' => 'paid',
            'paid_at' => now(),
        ]);
        SubscriptionInvoice::create([
            'id' => (string) Str::uuid(),
            'invoice_number' => SubscriptionInvoice::nextNumber(),
            'agency_id' => $foreign->id,
            'amount_pkr' => 1000,
            'total_pkr' => 1000,
            'currency' => 'PKR',
            'status' => 'pending',
        ]);

        // The acting agency's own activity.
        $this->makePublicProperty($agency);
        Inquiry::create([
            'id' => 'inq-' . Str::uuid(),
            'fullName' => 'Own Lead',
            'phone' => '03003334444',
            'message' => 'Own inquiry',
            'agency_id' => $agency->id,
        ]);
        SubscriptionInvoice::create([
            'id' => (string) Str::uuid(),
            'invoice_number' => SubscriptionInvoice::nextNumber(),
            'agency_id' => $agency->id,
            'amount_pkr' => 2500,
            'total_pkr' => 2500,
            'currency' => 'PKR',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $today = now()->toDateString();
        $report = $this->getJson(
            '/api/agency/reports?from=' . $today . '&to=' . $today,
            $this->authHeaders($this->tokenFor($user))
        )->assertOk();

        // Platform-wide agency counts must not leak into an agency report.
        $this->assertSame(1, $report->json('agencies.total'));
        $this->assertSame(1, $report->json('agencies.verified'));
        $this->assertSame(1, $report->json('listings.created_in_range'));
        $this->assertSame(1, $report->json('listings.public'));
        $this->assertSame(1, $report->json('leads_in_range'));

        // Invoices are the agency's own billing only.
        $this->assertSame(2500.0, (float) $report->json('invoices.paid_pkr'));
        $this->assertSame(1, $report->json('invoices.paid_count'));
        $this->assertSame(0.0, (float) $report->json('invoices.outstanding_pkr'));

        // Deals/collected remain tenant-scoped as before.
        $this->assertSame(0, $report->json('deals.count'));
        $this->assertSame(0.0, (float) $report->json('collected_pkr'));
    }

    public function test_platform_report_requires_admin_view_reports_and_reports_all_agencies(): void
    {
        [$member] = $this->agencyUser(['finance.view_reports'], 'accountant');
        $this->getJson('/api/admin/reports', $this->authHeaders($this->tokenFor($member)))
            ->assertStatus(403);

        $this->seedPlatformRole('super_admin');
        $admin = $this->makeUser('super_admin');

        $report = $this->getJson('/api/admin/reports?from=' . now()->toDateString() . '&to=' . now()->toDateString(), $this->authHeaders($this->tokenFor($admin)))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertArrayHasKey('agencies', $report->json());
        $this->assertArrayHasKey('invoices', $report->json());
        $this->assertArrayHasKey('deals', $report->json());
        $this->assertGreaterThanOrEqual(1, $report->json('agencies.total'));
    }
}
