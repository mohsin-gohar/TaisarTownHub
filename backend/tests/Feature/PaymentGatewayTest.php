<?php

namespace Tests\Feature;

use App\Models\AgencySubscription;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Payment gateway contract:
 *  - webhooks are reachable WITHOUT a Sanctum token (gateways cannot log in)
 *    and remain protected by their own signature checks;
 *  - Flutterwave verification is agency-scoped and requires billing rights;
 *  - a successful verification activates the subscription exactly once.
 */
class PaymentGatewayTest extends EnterpriseTestCase
{
    private function makePlan(array $attrs = []): SubscriptionPlan
    {
        return SubscriptionPlan::create(array_merge([
            'key' => 'plan-' . Str::random(8),
            'name' => 'Professional',
            'price' => 5000,
            'currency' => 'PKR',
            'interval' => 'month',
            'is_active' => true,
        ], $attrs));
    }

    private function makeInvoice(string $agencyId, SubscriptionPlan $plan, array $attrs = []): SubscriptionInvoice
    {
        return SubscriptionInvoice::create(array_merge([
            'id' => (string) Str::uuid(),
            'invoice_number' => SubscriptionInvoice::nextNumber(),
            'agency_id' => $agencyId,
            'plan_id' => $plan->id,
            'amount_pkr' => 5000,
            'tax_pkr' => 0,
            'total_pkr' => 5000,
            'currency' => 'PKR',
            'status' => 'pending',
        ], $attrs));
    }

    private function stripeSignature(string $payload, string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        return 't=' . $timestamp . ',v1=' . hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    }

    public function test_payment_webhooks_are_public_but_signature_guarded(): void
    {
        config([
            'services.stripe.webhook' => 'whsec_test_secret',
            'services.flutterwave.webhook_secret' => 'fw_test_hash',
        ]);

        $this->asGuest();

        foreach (['/api/payments/webhook/stripe', '/api/v1/payments/webhook/stripe'] as $route) {
            // A gateway has no Sanctum token: a 401 here would mean real
            // deliveries are dropped before signature verification runs.
            $this->postJson($route, ['type' => 'checkout.session.completed'])
                ->assertStatus(400)
                ->assertJsonPath('message', 'Invalid signature.');
        }

        foreach (['/api/payments/webhook/flutterwave', '/api/v1/payments/webhook/flutterwave'] as $route) {
            $this->postJson($route, ['status' => 'successful', 'tx_ref' => 'fw-1'])
                ->assertStatus(400)
                ->assertJsonPath('message', 'Invalid signature.');
        }
    }

    public function test_flutterwave_webhook_rejects_everything_when_no_secret_is_configured(): void
    {
        // Regression: null header vs null config previously compared equal,
        // so an unsigned webhook could mark an invoice paid.
        config(['services.flutterwave.webhook_secret' => null]);

        $this->asGuest();
        $this->postJson('/api/payments/webhook/flutterwave', [
            'status' => 'successful',
            'tx_ref' => 'anything',
        ])->assertStatus(400)->assertJsonPath('message', 'Invalid signature.');
    }

    public function test_stripe_webhook_marks_invoice_paid_and_activates_subscription(): void
    {
        $secret = 'whsec_test_secret';
        config(['services.stripe.webhook' => $secret]);

        $plan = $this->makePlan();
        [$user, $agency] = $this->agencyUser(['agency.view', 'agency.subscribe']);
        $invoice = $this->makeInvoice($agency->id, $plan, ['stripe_session_id' => 'cs_test_123']);

        $payload = json_encode([
            'id' => 'evt_test_1',
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_test_123',
                'client_reference_id' => $agency->id,
                'metadata' => [
                    'agency_id' => $agency->id,
                    'plan_id' => (string) $plan->id,
                ],
                'payment_intent' => 'pi_test_123',
                'subscription' => 'sub_test_123',
                'payment_status' => 'paid',
                'amount_total' => 500000,
                'currency' => 'pkr',
            ]],
        ]);

        $this->asGuest();
        $this->call(
            'POST',
            '/api/payments/webhook/stripe',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => $this->stripeSignature($payload, $secret),
            ],
            $payload
        )->assertOk()->assertJsonPath('success', true);

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('pi_test_123', $invoice->fresh()->stripe_payment_intent_id);
        $this->assertDatabaseHas('agency_subscriptions', [
            'agency_id' => $agency->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'stripe_subscription_id' => 'sub_test_123',
        ]);
    }

    public function test_flutterwave_webhook_activates_only_a_matching_pending_invoice(): void
    {
        config(['services.flutterwave.webhook_secret' => 'fw_test_hash']);

        $plan = $this->makePlan();
        [$user, $agency] = $this->agencyUser(['agency.view', 'agency.subscribe']);
        $invoice = $this->makeInvoice($agency->id, $plan, ['flutterwave_tx_ref' => 'fw-ref-100']);

        $this->asGuest();

        // Wrong amount must never activate the plan.
        $this->withHeaders(['verif-hash' => 'fw_test_hash'])->postJson('/api/payments/webhook/flutterwave', [
            'status' => 'successful',
            'tx_ref' => 'fw-ref-100',
            'id' => 991,
            'amount' => 1,
            'currency' => 'PKR',
        ])->assertStatus(422);
        $this->assertSame('pending', $invoice->fresh()->status);

        // Flutterwave's real webhook shape nests transaction fields under data.
        $this->withHeaders(['verif-hash' => 'fw_test_hash'])->postJson('/api/payments/webhook/flutterwave', [
            'event' => 'charge.completed',
            'data' => [
                'status' => 'successful',
                'tx_ref' => 'fw-ref-100',
                'id' => 991,
                'amount' => 5000,
                'currency' => 'PKR',
            ],
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('991', $invoice->fresh()->flutterwave_tx_id);
        $this->assertDatabaseHas('agency_subscriptions', [
            'agency_id' => $agency->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
    }

    public function test_failed_stripe_invoice_can_be_paid_on_a_later_retry(): void
    {
        $plan = $this->makePlan();
        [, $agency] = $this->agencyUser(['agency.view', 'agency.subscribe']);
        $subscription = AgencySubscription::create([
            'agency_id' => $agency->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'auto_renew' => true,
            'stripe_subscription_id' => 'sub-retry-event',
            'provider' => 'stripe',
        ]);

        $periodStart = now()->addMonth()->timestamp;
        $periodEnd = now()->addMonths(2)->timestamp;
        $invoice = json_decode(json_encode([
            'id' => 'in-retry-event',
            'subscription' => 'sub-retry-event',
            'amount_due' => 500000,
            'amount_paid' => 500000,
            'currency' => 'pkr',
            'lines' => ['data' => [[
                'period' => ['start' => $periodStart, 'end' => $periodEnd],
            ]]],
        ]));

        $controller = app(\App\Http\Controllers\Api\PaymentController::class);
        $failedHandler = new \ReflectionMethod($controller, 'handleStripeInvoiceFailed');
        $failedHandler->setAccessible(true);
        $failedHandler->invoke($controller, $invoice);

        $this->assertSame('past_due', $subscription->fresh()->status);
        $this->assertDatabaseHas('subscription_invoices', [
            'agency_id' => $agency->id,
            'stripe_invoice_id' => 'in-retry-event',
            'status' => 'failed',
        ]);

        $paidHandler = new \ReflectionMethod($controller, 'handleStripeInvoicePaid');
        $paidHandler->setAccessible(true);
        $paidHandler->invoke($controller, $invoice);

        $this->assertSame('active', $subscription->fresh()->status);
        $this->assertSame($periodEnd, $subscription->fresh()->ends_at->getTimestamp());
        $this->assertDatabaseHas('subscription_invoices', [
            'agency_id' => $agency->id,
            'stripe_invoice_id' => 'in-retry-event',
            'status' => 'paid',
        ]);
        $this->assertSame(1, SubscriptionInvoice::where('stripe_invoice_id', 'in-retry-event')->count());
    }

    public function test_stale_stripe_subscription_events_cannot_regress_a_paid_term(): void
    {
        $plan = $this->makePlan();
        [, $agency] = $this->agencyUser(['agency.view', 'agency.subscribe']);
        $subscription = AgencySubscription::create([
            'agency_id' => $agency->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
            'auto_renew' => true,
            'stripe_subscription_id' => 'sub-stale-event',
            'provider' => 'stripe',
        ]);

        $controller = app(\App\Http\Controllers\Api\PaymentController::class);
        $handler = new \ReflectionMethod($controller, 'handleStripeSubscriptionChanged');
        $handler->setAccessible(true);
        $paidPeriodEnd = $subscription->fresh()->ends_at->getTimestamp();

        $handler->invoke($controller, (object) [
            'id' => 'sub-stale-event',
            'status' => 'past_due',
            'current_period_end' => $paidPeriodEnd - 86400,
        ]);
        $this->assertSame('active', $subscription->fresh()->status);

        $handler->invoke($controller, (object) [
            'id' => 'sub-stale-event',
            'status' => 'past_due',
            'current_period_end' => $paidPeriodEnd + 86400,
        ]);
        $this->assertSame('past_due', $subscription->fresh()->status);

        $failedPeriodEnd = $subscription->fresh()->ends_at->getTimestamp();
        $handler->invoke($controller, (object) [
            'id' => 'sub-stale-event',
            'status' => 'active',
            'current_period_end' => $paidPeriodEnd,
        ]);
        $this->assertSame('past_due', $subscription->fresh()->status);

        $handler->invoke($controller, (object) [
            'id' => 'sub-stale-event',
            'status' => 'active',
            'current_period_end' => $failedPeriodEnd + 86400,
        ]);
        $this->assertSame('active', $subscription->fresh()->status);
        $this->assertGreaterThan($failedPeriodEnd, $subscription->fresh()->ends_at->getTimestamp());
    }

    public function test_flutterwave_webhook_requires_amount_and_currency_before_activation(): void
    {
        config(['services.flutterwave.webhook_secret' => 'fw_test_hash']);

        $plan = $this->makePlan();
        [, $agency] = $this->agencyUser(['agency.view', 'agency.subscribe']);
        $invoice = $this->makeInvoice($agency->id, $plan, ['flutterwave_tx_ref' => 'fw-ref-missing-fields']);

        $this->asGuest();
        $this->withHeaders(['verif-hash' => 'fw_test_hash'])
            ->postJson('/api/payments/webhook/flutterwave', [
                'data' => [
                    'status' => 'successful',
                    'tx_ref' => 'fw-ref-missing-fields',
                    'id' => 992,
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame('pending', $invoice->fresh()->status);
        $this->assertDatabaseMissing('agency_subscriptions', ['agency_id' => $agency->id]);
    }

    public function test_flutterwave_verification_requires_auth_and_billing_permission(): void
    {
        $plan = $this->makePlan();
        [$owner, $agency] = $this->agencyUser(['agency.view', 'agency.subscribe']);
        $invoice = $this->makeInvoice($agency->id, $plan, ['flutterwave_tx_ref' => 'fw-ref-200']);

        $this->asGuest();
        $this->getJson('/api/payments/flutterwave/verify/fw-ref-200')->assertUnauthorized();

        [$viewer, $sameAgency] = $this->agencyUser(['agency.view'], 'viewer', $agency);
        $this->getJson(
            '/api/payments/flutterwave/verify/fw-ref-200',
            $this->authHeaders($this->tokenFor($viewer))
        )->assertForbidden();

        // A member of another agency (even with billing rights) cannot probe
        // or activate this reference.
        [$other, $otherAgency] = $this->agencyUser(['agency.view', 'agency.subscribe']);
        Http::fake();
        $this->getJson(
            '/api/payments/flutterwave/verify/fw-ref-200',
            $this->authHeaders($this->tokenFor($other))
        )->assertNotFound();
        Http::assertNothingSent();
        $this->assertSame('pending', $invoice->fresh()->status);
    }

    public function test_flutterwave_verification_is_agency_scoped_and_returns_no_gateway_pii(): void
    {
        config(['services.flutterwave.secret_key' => 'FLWSECK_TEST-123']);

        $plan = $this->makePlan();
        [$owner, $agency] = $this->agencyUser(['agency.view', 'agency.subscribe']);
        $invoice = $this->makeInvoice($agency->id, $plan, ['flutterwave_tx_ref' => 'fw-ref-300']);

        Http::fake([
            'api.flutterwave.com/*' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => 4242,
                    'status' => 'successful',
                    'amount' => 5000,
                    'currency' => 'PKR',
                    'customer' => ['email' => 'payer@example.test', 'phone_number' => '03001234567'],
                    'card' => ['first_6digits' => '424242'],
                ],
            ], 200),
        ]);

        $response = $this->getJson(
            '/api/payments/flutterwave/verify/fw-ref-300',
            $this->authHeaders($this->tokenFor($owner))
        )->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tx_ref', 'fw-ref-300')
            ->assertJsonPath('data.status', 'successful')
            ->assertJsonMissingPath('data.customer')
            ->assertJsonMissingPath('data.card');

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('flutterwave', $invoice->fresh()->payment_method);
        $this->assertDatabaseHas('agency_subscriptions', [
            'agency_id' => $agency->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/v3/transactions/verify_by_reference')
                && $request['tx_ref'] === 'fw-ref-300';
        });

        // Idempotent: a second verification does not duplicate billing state.
        $this->getJson(
            '/api/payments/flutterwave/verify/fw-ref-300',
            $this->authHeaders($this->tokenFor($owner))
        )->assertOk();
        $this->assertSame(1, AgencySubscription::where('agency_id', $agency->id)->count());
    }

    public function test_flutterwave_verification_rejects_amount_mismatch(): void
    {
        config(['services.flutterwave.secret_key' => 'FLWSECK_TEST-123']);

        $plan = $this->makePlan();
        [$owner, $agency] = $this->agencyUser(['agency.view', 'agency.subscribe']);
        $invoice = $this->makeInvoice($agency->id, $plan, ['flutterwave_tx_ref' => 'fw-ref-400']);

        Http::fake([
            'api.flutterwave.com/*' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => 77,
                    'status' => 'successful',
                    'amount' => 100,
                    'currency' => 'PKR',
                ],
            ], 200),
        ]);

        $this->getJson(
            '/api/payments/flutterwave/verify/fw-ref-400',
            $this->authHeaders($this->tokenFor($owner))
        )->assertStatus(422)->assertJsonPath('success', false);

        $this->assertSame('pending', $invoice->fresh()->status);
        $this->assertDatabaseMissing('agency_subscriptions', ['agency_id' => $agency->id]);
    }

    public function test_subscription_checkout_rejects_untrusted_return_origins(): void
    {
        config([
            'app.url' => 'https://api.taisartownhub.com',
            'cors.allowed_origins' => ['https://taisartownhub.com', 'http://localhost:3000'],
        ]);

        $plan = $this->makePlan();
        [$owner, $agency] = $this->agencyUser(['agency.view', 'agency.subscribe']);

        $this->postJson('/api/payments/subscription/checkout', [
            'plan_id' => $plan->id,
            'success_url' => 'https://attacker.example/fake-success',
            'cancel_url' => 'https://taisartownhub.com/billing/cancel',
        ], $this->authHeaders($this->tokenFor($owner)))
            ->assertStatus(422)
            ->assertJsonValidationErrors('success_url');

        $this->assertDatabaseMissing('subscription_invoices', [
            'agency_id' => $agency->id,
            'plan_id' => $plan->id,
        ]);
    }



    public function test_subscription_checkout_rejects_inactive_plans(): void
    {
        config([
            'app.url' => 'https://api.taisartownhub.com',
            'cors.allowed_origins' => ['https://taisartownhub.com'],
        ]);

        $plan = $this->makePlan(['is_active' => false]);
        [$owner, $agency] = $this->agencyUser(['agency.view', 'agency.subscribe']);

        $this->postJson('/api/payments/subscription/checkout', [
            'plan_id' => $plan->id,
            'success_url' => 'https://taisartownhub.com/billing/success',
            'cancel_url' => 'https://taisartownhub.com/billing/cancel',
        ], $this->authHeaders($this->tokenFor($owner)))
            ->assertStatus(422)
            ->assertJsonValidationErrors('plan_id');

        $this->assertDatabaseMissing('subscription_invoices', [
            'agency_id' => $agency->id,
            'plan_id' => $plan->id,
        ]);
    }

    public function test_checkout_rejects_legacy_non_pkr_or_fractional_plan_prices(): void
    {
        config([
            'app.url' => 'https://api.taisartownhub.com',
            'cors.allowed_origins' => ['https://taisartownhub.com'],
        ]);

        [$owner, $agency] = $this->agencyUser(['agency.view', 'agency.subscribe']);
        $plans = [
            $this->makePlan(['currency' => 'USD']),
            $this->makePlan(['price' => 5000.50, 'currency' => 'PKR']),
        ];

        foreach ($plans as $plan) {
            $this->postJson('/api/payments/subscription/checkout', [
                'plan_id' => $plan->id,
                'success_url' => 'https://taisartownhub.com/billing/success',
                'cancel_url' => 'https://taisartownhub.com/billing/cancel',
            ], $this->authHeaders($this->tokenFor($owner)))
                ->assertStatus(422)
                ->assertJsonPath('success', false);
        }

        $this->assertDatabaseMissing('subscription_invoices', ['agency_id' => $agency->id]);
    }

    public function test_stripe_checkout_webhook_rejects_currency_or_amount_mismatch(): void
    {
        $secret = 'whsec_test_secret';
        config(['services.stripe.webhook' => $secret]);

        $plan = $this->makePlan();
        [$owner, $agency] = $this->agencyUser(['agency.view', 'agency.subscribe']);
        $invoice = $this->makeInvoice($agency->id, $plan, ['stripe_session_id' => 'cs_bad_amount']);

        $payload = json_encode([
            'id' => 'evt_bad_amount',
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_bad_amount',
                'client_reference_id' => $agency->id,
                'metadata' => ['agency_id' => $agency->id, 'plan_id' => (string) $plan->id],
                'payment_intent' => 'pi_bad_amount',
                'subscription' => 'sub_bad_amount',
                'payment_status' => 'paid',
                'amount_total' => 100,
                'currency' => 'pkr',
            ]],
        ]);

        $this->asGuest();
        $this->call(
            'POST',
            '/api/payments/webhook/stripe',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => $this->stripeSignature($payload, $secret),
            ],
            $payload
        )->assertOk()->assertJsonPath('success', true);

        $this->assertSame('pending', $invoice->fresh()->status);
        $this->assertDatabaseMissing('agency_subscriptions', ['agency_id' => $agency->id]);
    }



    public function test_stripe_invoice_events_update_subscription_using_gateway_subscription_id(): void
    {
        $secret = 'whsec_invoice_events';
        config(['services.stripe.webhook' => $secret]);

        $plan = $this->makePlan();
        [, $agency] = $this->agencyUser(['agency.view', 'agency.subscribe']);
        AgencySubscription::create([
            'agency_id' => $agency->id,
            'plan_id' => $plan->id,
            'status' => 'past_due',
            'stripe_subscription_id' => 'sub_invoice_event_1',
        ]);

        $this->asGuest();
        $periodStart = now()->timestamp;
        $paidPeriodEnd = now()->addMonth()->timestamp;
        $failedPeriodEnd = now()->addMonths(2)->timestamp;

        foreach ([
            ['invoice.payment_succeeded', 'active', 'in_invoice_paid_1', $paidPeriodEnd],
            ['invoice.payment_failed', 'past_due', 'in_invoice_failed_2', $failedPeriodEnd],
        ] as [$eventType, $expectedStatus, $invoiceId, $periodEnd]) {
            $payload = json_encode([
                'id' => 'evt_' . str_replace('.', '_', $eventType),
                'type' => $eventType,
                'data' => ['object' => [
                    'id' => $invoiceId,
                    'subscription' => 'sub_invoice_event_1',
                    'amount_paid' => 500000,
                    'currency' => 'pkr',
                    'lines' => ['data' => [[
                        'period' => ['start' => $periodStart, 'end' => $periodEnd],
                    ]]],
                ]],
            ]);

            $this->call(
                'POST',
                '/api/payments/webhook/stripe',
                [],
                [],
                [],
                [
                    'CONTENT_TYPE' => 'application/json',
                    'HTTP_STRIPE_SIGNATURE' => $this->stripeSignature($payload, $secret),
                ],
                $payload
            )->assertOk()->assertJsonPath('success', true);

            $this->assertDatabaseHas('agency_subscriptions', [
                'agency_id' => $agency->id,
                'stripe_subscription_id' => 'sub_invoice_event_1',
                'status' => $expectedStatus,
            ]);
        }

        $this->assertDatabaseHas('subscription_invoices', [
            'agency_id' => $agency->id,
            'stripe_invoice_id' => 'in_invoice_paid_1',
            'status' => 'paid',
            'total_pkr' => 5000,
        ]);
        $this->assertSame(
            now()->setTimestamp($paidPeriodEnd)->toDateString(),
            AgencySubscription::where('stripe_subscription_id', 'sub_invoice_event_1')->firstOrFail()->ends_at->toDateString()
        );
    }


}
