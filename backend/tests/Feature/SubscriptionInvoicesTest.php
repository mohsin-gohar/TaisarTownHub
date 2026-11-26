<?php

namespace Tests\Feature;

use App\Models\AgencySubscription;
use App\Models\SubscriptionInvoice;
use Tests\Feature\EnterpriseTestCase;

/**
 * Subscription invoices — admin raise/verify with immutable paid history,
 * plus the agency-side read of its own billing data only.
 */
class SubscriptionInvoicesTest extends EnterpriseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatformRole('super_admin');
    }

    private function admin()
    {
        return $this->makeUser('super_admin');
    }

    public function test_invoice_number_sequence_is_monotonic_even_if_a_reservation_is_not_saved(): void
    {
        $first = SubscriptionInvoice::nextNumber();
        $second = SubscriptionInvoice::nextNumber();

        $this->assertMatchesRegularExpression('/^INV-\d{4}-\d{6}$/', $first);
        $this->assertMatchesRegularExpression('/^INV-\d{4}-\d{6}$/', $second);
        $this->assertSame(
            (int) substr($first, -6) + 1,
            (int) substr($second, -6)
        );

        // A failed invoice insert must not cause a later invoice to reuse an
        // already-reserved number; the sequence row is the source of truth.
        $third = SubscriptionInvoice::nextNumber();
        $this->assertSame((int) substr($second, -6) + 1, (int) substr($third, -6));
    }

    public function test_invoice_sequence_reconciles_with_imported_legacy_numbers(): void
    {
        [, $agency] = $this->agencyUser(['agency.view'], 'agent');
        $year = now()->format('Y');

        SubscriptionInvoice::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'invoice_number' => "INV-{$year}-000009",
            'agency_id' => $agency->id,
            'amount_pkr' => 1000,
            'tax_pkr' => 0,
            'total_pkr' => 1000,
            'currency' => 'PKR',
            'status' => 'pending',
        ]);

        $this->assertSame("INV-{$year}-000010", SubscriptionInvoice::nextNumber());
    }

    public function test_admin_can_raise_and_verify_an_invoice_exactly_once(): void
    {
        $admin = $this->admin();
        [, $agency] = $this->agencyUser(['agency.view'], 'agent');
        $plan = $this->makePlan();
        $h = fn () => $this->authHeaders($this->tokenFor($admin));

        $created = $this->postJson('/api/admin/subscription-invoices', [
            'agency_id' => $agency->id,
            'plan_id' => $plan->id,
            'amount_pkr' => 10000,
            'tax_pkr' => 500,
            'period_start' => '2026-11-01',
            'period_end' => '2026-11-30',
            'notes' => 'November cycle',
        ], $h())
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('invoice.status', 'pending')
            ->assertJsonPath('invoice.total_pkr', 10500)
            ->assertJsonPath('invoice.agency_id', $agency->id);

        $this->assertMatchesRegularExpression('/^INV-\d{4}-\d{6}$/', (string) $created->json('invoice.invoice_number'));

        $invoiceId = $created->json('invoice.id');

        // Verify marks it paid, stamps payment details + verifier.
        $this->postJson('/api/admin/subscription-invoices/' . $invoiceId . '/verify', [
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'TRX-991200',
        ], $h())
            ->assertOk()
            ->assertJsonPath('invoice.status', 'paid');

        $row = SubscriptionInvoice::find($invoiceId);
        $this->assertSame('paid', $row->status);
        $this->assertSame('bank_transfer', $row->payment_method);
        $this->assertSame('TRX-991200', $row->payment_reference);
        $this->assertNotNull($row->paid_at);
        $this->assertSame($admin->id, $row->verified_by);

        // A paid plan invoice must activate the agency subscription atomically.
        $subscription = AgencySubscription::where('agency_id', $agency->id)->firstOrFail();
        $this->assertSame($plan->id, $subscription->plan_id);
        $this->assertSame('active', $subscription->status);
        $this->assertSame('manual', $subscription->provider);
        $this->assertFalse((bool) $subscription->auto_renew);
        $this->assertSame('2026-11-01', $subscription->starts_at->toDateString());
        $this->assertSame('2026-11-30', $subscription->ends_at->toDateString());

        // Paid invoices are financial history — never modifiable again.
        $this->postJson('/api/admin/subscription-invoices/' . $invoiceId . '/verify', [
            'payment_method' => 'cash',
        ], $h())
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_manual_invoice_cannot_replace_an_active_stripe_subscription(): void
    {
        $admin = $this->admin();
        [, $agency] = $this->agencyUser(['agency.view'], 'agent');
        $plan = $this->makePlan();
        $stripe = AgencySubscription::create([
            'agency_id' => $agency->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'auto_renew' => true,
            'stripe_subscription_id' => 'sub-existing-recurring',
            'provider' => 'stripe',
        ]);

        $headers = $this->authHeaders($this->tokenFor($admin));
        $created = $this->postJson('/api/admin/subscription-invoices', [
            'agency_id' => $agency->id,
            'plan_id' => $plan->id,
            'amount_pkr' => 10000,
            'period_start' => now()->toDateString(),
            'period_end' => now()->addMonth()->toDateString(),
        ], $headers)->assertStatus(201);

        $invoiceId = $created->json('invoice.id');
        $this->postJson('/api/admin/subscription-invoices/' . $invoiceId . '/verify', [
            'payment_method' => 'bank_transfer',
        ], $headers)
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertSame('pending', SubscriptionInvoice::findOrFail($invoiceId)->status);
        $this->assertSame('stripe', $stripe->fresh()->provider);
        $this->assertSame('sub-existing-recurring', $stripe->fresh()->stripe_subscription_id);
        $this->assertTrue((bool) $stripe->fresh()->auto_renew);
    }

    public function test_void_invoices_cannot_be_paid_and_numbers_stay_unique(): void
    {
        $admin = $this->admin();
        [$member, $agency] = $this->agencyUser(['agency.view'], 'agent');
        $h = fn () => $this->authHeaders($this->tokenFor($admin));

        $a = $this->postJson('/api/admin/subscription-invoices', [
            'agency_id' => $agency->id, 'amount_pkr' => 5000,
        ], $h())->assertStatus(201);
        $b = $this->postJson('/api/admin/subscription-invoices', [
            'agency_id' => $agency->id, 'amount_pkr' => 7000,
        ], $h())->assertStatus(201);

        $this->assertNotSame($a->json('invoice.invoice_number'), $b->json('invoice.invoice_number'));

        // Void it directly (pending → void is a staff transition).
        SubscriptionInvoice::where('id', $a->json('invoice.id'))->update(['status' => 'void']);

        $this->postJson('/api/admin/subscription-invoices/' . $a->json('invoice.id') . '/verify', [
            'payment_method' => 'cash',
        ], $h())
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_agency_sees_only_its_own_invoices_and_outstanding_totals(): void
    {
        $admin = $this->admin();
        $h = fn () => $this->authHeaders($this->tokenFor($admin));

        [$memberA, $agencyA] = $this->agencyUser(['agency.view'], 'agent');
        [$memberB, $agencyB] = $this->agencyUser(['agency.view'], 'agent');

        $this->postJson('/api/admin/subscription-invoices', [
            'agency_id' => $agencyA->id, 'amount_pkr' => 12000, 'tax_pkr' => 1000,
        ], $h())->assertStatus(201);
        $this->postJson('/api/admin/subscription-invoices', [
            'agency_id' => $agencyA->id, 'amount_pkr' => 8000,
        ], $h())->assertStatus(201);
        $this->postJson('/api/admin/subscription-invoices', [
            'agency_id' => $agencyB->id, 'amount_pkr' => 3000,
        ], $h())->assertStatus(201);

        $listA = $this->getJson('/api/agency/subscription/invoices', $this->authHeaders($this->tokenFor($memberA)))
            ->assertOk()
            ->assertJsonPath('totals.outstanding_pkr', 21000)
            ->assertJsonPath('totals.paid_pkr', 0);
        $this->assertSame(2, count($listA->json('invoices')));

        $listB = $this->getJson('/api/agency/subscription/invoices', $this->authHeaders($this->tokenFor($memberB)))
            ->assertOk()
            ->assertJsonPath('totals.outstanding_pkr', 3000);
        $this->assertSame(1, count($listB->json('invoices')));

        // Agency accounts cannot use the admin endpoints.
        $this->getJson('/api/admin/subscription-invoices', $this->authHeaders($this->tokenFor($memberA)))
            ->assertStatus(403);
        $this->postJson('/api/admin/subscription-invoices', [
            'agency_id' => $agencyB->id, 'amount_pkr' => 1,
        ], $this->authHeaders($this->tokenFor($memberA)))
            ->assertStatus(403);
    }

    public function test_paid_totals_reflect_verified_invoices(): void
    {
        $admin = $this->admin();
        $h = fn () => $this->authHeaders($this->tokenFor($admin));
        [$member, $agency] = $this->agencyUser(['agency.view'], 'agent');

        $paid = $this->postJson('/api/admin/subscription-invoices', [
            'agency_id' => $agency->id, 'amount_pkr' => 2000,
        ], $h())->assertStatus(201);
        $this->postJson('/api/admin/subscription-invoices', [
            'agency_id' => $agency->id, 'amount_pkr' => 3000,
        ], $h())->assertStatus(201);

        $this->postJson('/api/admin/subscription-invoices/' . $paid->json('invoice.id') . '/verify', [
            'payment_method' => 'online',
        ], $h())->assertOk();

        $list = $this->getJson('/api/agency/subscription/invoices', $this->authHeaders($this->tokenFor($member)))
            ->assertOk()
            ->assertJsonPath('totals.paid_pkr', 2000)
            ->assertJsonPath('totals.outstanding_pkr', 3000);
        $this->assertSame(2, count($list->json('invoices')));
    }
}
