<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgencySubscription;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Payment gateway integration — Stripe + Flutterwave.
 *
 * Handles:
 *   - Creating payment intents for subscriptions & one-off payments
 *   - Stripe webhook processing (checkout.session.completed, invoice.payment_succeeded, etc.)
 *   - Flutterwave payment verification & webhook
 *   - Invoice lifecycle for subscription billing
 *
 * All routes are under auth:sanctum middleware except the public webhook endpoints.
 */
class PaymentController extends Controller
{
    use AuthorizesApi;

    /* ==================== Subscription Checkout ==================== */

    /** POST /api/payments/subscription/checkout — create a Stripe Checkout session for a plan. */
    public function subscriptionCheckout(Request $request)
    {
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'agency.subscribe');

        $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'success_url' => 'required|url',
            'cancel_url' => 'required|url',
        ]);

        $plan = SubscriptionPlan::findOrFail($request->plan_id);

        // Every agency already has a starter subscription (5 GB workspace), so
        // an existing row must never block an upgrade. Payment is optional
        // during the free launch; platform admins can also assign plans
        // directly from the dashboard.
        $existingPending = SubscriptionInvoice::where('agency_id', $agencyId)
            ->where('plan_id', $plan->id)
            ->where('status', 'pending')
            ->whereNotNull('stripe_session_id')
            ->where('created_at', '>=', now()->subHours(24))
            ->latest()
            ->first();

        if ($existingPending) {
            return response()->json([
                'success' => true,
                'message' => 'A recent checkout session already exists for this plan. Complete it or wait for it to expire before retrying.',
                'session_id' => $existingPending->stripe_session_id,
            ], 409);
        }

        try {
            $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

            $session = $stripe->checkout->sessions->create([
                'mode' => 'subscription',
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => strtolower($plan->currency ?? 'pkr'),
                        'product_data' => [
                            'name' => $plan->name,
                            'description' => $plan->description ?? 'TaisarTownHub agency subscription',
                        ],
                        'unit_amount' => (int) ($plan->price * 100),
                        'recurring' => [
                            'interval' => $plan->interval ?? 'month',
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'success_url' => $request->success_url . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $request->cancel_url,
                'client_reference_id' => $agencyId,
                'metadata' => [
                    'agency_id' => $agencyId,
                    'plan_id' => $plan->id,
                ],
            ]);

            // Create a pending invoice using the canonical schema columns.
            $amount = (int) round((float) $plan->price);
            SubscriptionInvoice::create([
                'id' => (string) Str::uuid(),
                'invoice_number' => SubscriptionInvoice::nextNumber(),
                'agency_id' => $agencyId,
                'plan_id' => $plan->id,
                'amount_pkr' => $amount,
                'tax_pkr' => 0,
                'total_pkr' => $amount,
                'currency' => $plan->currency ?? 'PKR',
                'period_start' => now()->toDateString(),
                'period_end' => $plan->interval === 'year'
                    ? now()->addYear()->toDateString()
                    : now()->addMonth()->toDateString(),
                'status' => 'pending',
                'stripe_session_id' => $session->id,
                'due_at' => now()->addDays(7),
            ]);

            return response()->json([
                'success' => true,
                'checkout_url' => $session->url,
                'session_id' => $session->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Stripe checkout failed', [
                'agency_id' => $agencyId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment session creation failed.',
            ], 500);
        }
    }

    /* ==================== Stripe Webhook ==================== */

    /** POST /api/payments/webhook/stripe — Stripe webhook handler. */
    public function stripeWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook');

        if (!$webhookSecret) {
            Log::warning('Stripe webhook secret not configured');
            return response()->json(['success' => false, 'message' => 'Webhook not configured.'], 500);
        }

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (\Throwable $e) {
            Log::warning('Stripe webhook signature verification failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Invalid signature.'], 400);
        }

        $eventType = $event->type;
        $data = $event->data->object;

        Log::info('Stripe webhook received', ['event' => $eventType, 'id' => $event->id]);

        try {
            switch ($eventType) {
                case 'checkout.session.completed':
                    $this->handleStripeCheckoutCompleted($data);
                    break;

                case 'invoice.payment_succeeded':
                case 'invoice.paid':
                    $this->handleStripeInvoicePaid($data);
                    break;

                case 'invoice.payment_failed':
                    $this->handleStripeInvoiceFailed($data);
                    break;

                case 'customer.subscription.deleted':
                case 'customer.subscription.updated':
                    $this->handleStripeSubscriptionChanged($data);
                    break;

                default:
                    Log::info('Unhandled Stripe event', ['event' => $eventType]);
            }

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            Log::error('Stripe webhook handling failed', [
                'event' => $eventType,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Webhook handling failed.'], 500);
        }
    }

    /* ==================== Flutterwave Webhook ==================== */

    /** POST /api/payments/webhook/flutterwave — Flutterwave webhook handler. */
    public function flutterwaveWebhook(Request $request)
    {
        $payload = $request->all();
        $secretHash = $request->header('verif-hash');
        $expectedHash = config('services.flutterwave.webhook_secret');

        if ($secretHash !== $expectedHash) {
            Log::warning('Flutterwave webhook signature mismatch');
            return response()->json(['success' => false, 'message' => 'Invalid signature.'], 400);
        }

        $status = $payload['status'] ?? '';
        $txRef = $payload['tx_ref'] ?? '';
        $transactionId = $payload['id'] ?? null;

        Log::info('Flutterwave webhook received', ['status' => $status, 'tx_ref' => $txRef]);

        DB::transaction(function () use ($payload, $txRef, $transactionId, $status) {
            // Find invoice by reference
            $invoice = SubscriptionInvoice::where('flutterwave_tx_ref', $txRef)->first();

            if (!$invoice) {
                Log::warning('Flutterwave webhook: invoice not found', ['tx_ref' => $txRef]);
                return;
            }

            if ($status === 'successful' && $invoice->status === 'pending') {
                $invoice->update([
                    'status' => 'paid',
                    'flutterwave_tx_id' => $transactionId,
                    'paid_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->activateSubscriptionFromInvoice($invoice);
            } elseif (in_array($status, ['failed', 'cancelled'])) {
                $invoice->update([
                    'status' => 'failed',
                    'updated_at' => now(),
                ]);
            }
        });

        return response()->json(['success' => true, 'message' => 'Webhook processed.']);
    }

    /* ==================== Flutterwave Payment Verification ==================== */

    /** GET /api/payments/flutterwave/verify/{tx_ref} — verify a Flutterwave transaction. */
    public function flutterwaveVerify(Request $request, string $txRef)
    {
        $secretKey = config('services.flutterwave.secret_key');

        if (!$secretKey) {
            return response()->json([
                'success' => false,
                'message' => 'Flutterwave not configured.',
            ], 500);
        }

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => "Bearer {$secretKey}",
            ])->get("https://api.flutterwave.com/v3/transactions/{$txRef}/verify");

            $data = $response->json();

            if ($response->successful() && ($data['status'] === 'successful')) {
                // Mark invoice paid
                $invoice = SubscriptionInvoice::where('flutterwave_tx_ref', $txRef)->first();
                if ($invoice && $invoice->status === 'pending') {
                    $invoice->update([
                        'status' => 'paid',
                        'flutterwave_tx_id' => $data['data']['id'] ?? null,
                        'paid_at' => now(),
                    ]);
                    $this->activateSubscriptionFromInvoice($invoice);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Payment verified.',
                    'data' => $data['data'] ?? [],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Payment not successful.',
            ], 400);
        } catch (\Throwable $e) {
            Log::error('Flutterwave verification failed', ['tx_ref' => $txRef, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Verification failed.',
            ], 500);
        }
    }

    /* ==================== Helpers ==================== */

    private function handleStripeCheckoutCompleted($session): void
    {
        $agencyId = $session->client_reference_id ?? $session->metadata['agency_id'] ?? null;
        $planId = $session->metadata['plan_id'] ?? null;
        $stripeSubId = $session->subscription ?? null;

        if (!$agencyId || !$planId) {
            Log::warning('Stripe checkout: missing agency or plan reference', [
                'session_id' => $session->id,
            ]);
            return;
        }

        $invoice = SubscriptionInvoice::where('stripe_session_id', $session->id)->first();
        if (!$invoice) {
            Log::warning('Stripe checkout: invoice not found', ['session_id' => $session->id]);
            return;
        }

        if ($invoice->status === 'paid') {
            return; // idempotent
        }

        $invoice->update([
            'status' => 'paid',
            'stripe_payment_intent_id' => $session->payment_intent ?? null,
            'stripe_subscription_id' => $stripeSubId,
            'paid_at' => now(),
        ]);

        $this->activateSubscriptionFromInvoice($invoice);
    }

    private function handleStripeInvoicePaid($invoice): void
    {
        $stripeSubId = $invoice->stripe_subscription_id ?? null;
        if (!$stripeSubId) {
            return;
        }

        $agencySubscription = AgencySubscription::where('stripe_subscription_id', $stripeSubId)->first();
        if ($agencySubscription) {
            $agencySubscription->update(['status' => 'active']);
        }
    }

    private function handleStripeInvoiceFailed($invoice): void
    {
        $stripeSubId = $invoice->stripe_subscription_id ?? null;
        if (!$stripeSubId) {
            return;
        }

        $agencySubscription = AgencySubscription::where('stripe_subscription_id', $stripeSubId)->first();
        if ($agencySubscription) {
            $agencySubscription->update(['status' => 'past_due']);
        }
    }

    private function handleStripeSubscriptionChanged($subscription): void
    {
        $stripeSubId = $subscription->id ?? null;
        if (!$stripeSubId) {
            return;
        }

        $agencySubscription = AgencySubscription::where('stripe_subscription_id', $stripeSubId)->first();
        if (!$agencySubscription) {
            return;
        }

        $status = match ($subscription->status ?? '') {
            'active' => 'active',
            'past_due' => 'past_due',
            'canceled' => 'cancelled',
            'unpaid' => 'past_due',
            default => $agencySubscription->status,
        };

        $agencySubscription->update(['status' => $status]);
    }

    private function activateSubscriptionFromInvoice(SubscriptionInvoice $invoice): void
    {
        $agencyId = $invoice->agency_id;
        $planId = $invoice->plan_id;

        $plan = SubscriptionPlan::findOrFail($planId);

        // Determine interval ends_at
        $endsAt = match ($plan->interval) {
            'year' => now()->addYear(),
            default => now()->addMonth(),
        };

        AgencySubscription::updateOrCreate(
            ['agency_id' => $agencyId],
            [
                'plan_id' => $planId,
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => $endsAt,
                'auto_renew' => true,
                'stripe_subscription_id' => $invoice->stripe_subscription_id,
                'provider' => $invoice->stripe_subscription_id ? 'stripe' : 'manual',
                'cancelled_at' => null,
                'updated_at' => now(),
            ]
        );

        Log::info('Subscription activated', [
            'agency_id' => $agencyId,
            'plan_id' => $planId,
            'invoice_id' => $invoice->id,
        ]);
    }
}
