<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgencySubscription;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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

        // Invoice amounts are integer PKR. Reject legacy/inconsistent plan rows
        // rather than silently rounding a fractional or foreign-currency price.
        $planPrice = (string) $plan->getRawOriginal('price');
        if (strtoupper((string) $plan->currency) !== 'PKR'
            || ! preg_match('/^\\d+(?:\\.00)?$/', $planPrice)) {
            return response()->json([
                'success' => false,
                'message' => 'This plan has an unsupported price or currency. Contact the platform administrator.',
            ], 422);
        }
        $amountPkr = (int) $planPrice;

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
                        'unit_amount' => $amountPkr * 100,
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
            $amount = $amountPkr;
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

        // A missing server-side secret must never accept an unsigned request:
        // null === null would otherwise be treated as a valid match.
        if (!is_string($expectedHash) || $expectedHash === ''
            || !is_string($secretHash) || !hash_equals($expectedHash, $secretHash)) {
            Log::warning('Flutterwave webhook signature mismatch');

            return response()->json(['success' => false, 'message' => 'Invalid signature.'], 400);
        }

        $status = (string) ($payload['status'] ?? '');
        $txRef = (string) ($payload['tx_ref'] ?? '');
        $transactionId = $payload['id'] ?? null;

        if ($txRef === '') {
            return response()->json(['success' => false, 'message' => 'Missing transaction reference.'], 422);
        }

        Log::info('Flutterwave webhook received', ['status' => $status, 'tx_ref' => $txRef]);

        $outcome = DB::transaction(function () use ($payload, $txRef, $transactionId, $status): string {
            $invoice = SubscriptionInvoice::where('flutterwave_tx_ref', $txRef)
                ->lockForUpdate()
                ->first();

            if (!$invoice) {
                Log::warning('Flutterwave webhook: invoice not found', ['tx_ref' => $txRef]);

                return 'not_found';
            }

            if ($status === 'successful') {
                if ($invoice->status === 'paid') {
                    return 'already_paid';
                }
                if ($invoice->status !== 'pending') {
                    Log::warning('Flutterwave webhook ignored for non-pending invoice', [
                        'tx_ref' => $txRef,
                        'status' => $invoice->status,
                    ]);

                    return 'not_pending';
                }

                $amount = $payload['amount'] ?? null;
                $currency = strtoupper(trim((string) ($payload['currency'] ?? '')));
                // A valid shared-secret header authenticates the sender, but
                // a payment event without the invoice amount/currency cannot
                // prove that the expected amount was settled. Never treat
                // missing financial fields as an implicit match.
                if ($amount === null || !is_numeric($amount)) {
                    Log::error('Flutterwave webhook amount missing or invalid', ['tx_ref' => $txRef]);
                    return 'amount_mismatch';
                }
                if ($currency === '') {
                    Log::error('Flutterwave webhook currency missing', ['tx_ref' => $txRef]);
                    return 'currency_mismatch';
                }
                if (abs((float) $amount - (float) $invoice->total_pkr) > 0.00001) {
                    Log::error('Flutterwave webhook amount mismatch', [
                        'tx_ref' => $txRef,
                        'expected' => (int) $invoice->total_pkr,
                        'received' => $amount,
                    ]);

                    return 'amount_mismatch';
                }
                if ($currency !== '' && strtoupper((string) $invoice->currency) !== $currency) {
                    Log::error('Flutterwave webhook currency mismatch', [
                        'tx_ref' => $txRef,
                        'expected' => (string) $invoice->currency,
                        'received' => $currency,
                    ]);

                    return 'currency_mismatch';
                }

                $invoice->update([
                    'status' => 'paid',
                    'payment_method' => 'flutterwave',
                    'payment_reference' => $txRef,
                    'flutterwave_tx_id' => $transactionId !== null ? (string) $transactionId : null,
                    'paid_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->activateSubscriptionFromInvoice($invoice);

                return 'activated';
            }

            if (in_array($status, ['failed', 'cancelled'], true) && $invoice->status === 'pending') {
                $invoice->update([
                    'status' => 'failed',
                    'updated_at' => now(),
                ]);

                return 'failed';
            }

            return 'ignored';
        });

        if (in_array($outcome, ['amount_mismatch', 'currency_mismatch'], true)) {
            return response()->json(['success' => false, 'message' => 'Payment does not match the invoice.'], 422);
        }

        return response()->json(['success' => true, 'message' => 'Webhook processed.']);
    }

    /* ==================== Flutterwave Payment Verification ==================== */

    /** GET /api/payments/flutterwave/verify/{tx_ref} — verify a Flutterwave transaction. */
    public function flutterwaveVerify(Request $request, string $txRef)
    {
        // Only an agency member with billing rights may verify, and only a
        // reference that belongs to the acting agency — otherwise any signed-in
        // user could read/activate another agency's payment.
        $agencyId = $this->requireAgency($request);
        $this->requirePermission($request, 'agency.subscribe');

        $invoice = SubscriptionInvoice::where('agency_id', $agencyId)
            ->where('flutterwave_tx_ref', $txRef)
            ->first();

        // Cross-agency references are indistinguishable from missing ones.
        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Payment reference not found.',
            ], 404);
        }

        $secretKey = config('services.flutterwave.secret_key');

        if (!is_string($secretKey) || $secretKey === '') {
            return response()->json([
                'success' => false,
                'message' => 'Flutterwave not configured.',
            ], 500);
        }

        try {
            // Flutterwave's verify-by-reference endpoint (tx_ref is a query
            // parameter, not the numeric transaction id used by /transactions/{id}).
            $response = Http::withToken($secretKey)
                ->acceptJson()
                ->timeout(15)
                ->get('https://api.flutterwave.com/v3/transactions/verify_by_reference', [
                    'tx_ref' => $txRef,
                ]);

            $data = $response->json();
            $remote = is_array($data) ? ($data['data'] ?? null) : null;
            $status = is_array($remote) ? strtolower((string) ($remote['status'] ?? '')) : '';

            if (!$response->successful() || !is_array($remote) || $status !== 'successful') {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not successful.',
                ], 422);
            }

            $remoteAmountValue = $remote['amount'] ?? null;
            $remoteAmount = is_numeric($remoteAmountValue) ? (float) $remoteAmountValue : null;
            $remoteCurrency = strtoupper(trim((string) ($remote['currency'] ?? '')));

            if ($remoteAmount === null || $remoteCurrency === ''
                || $remoteCurrency !== strtoupper((string) $invoice->currency)
                || abs($remoteAmount - (float) $invoice->total_pkr) > 0.00001) {
                Log::warning('Flutterwave verification amount/currency mismatch', [
                    'agency_id' => $agencyId,
                    'tx_ref' => $txRef,
                    'expected_amount' => (int) $invoice->total_pkr,
                    'received_amount' => $remoteAmount,
                    'expected_currency' => (string) $invoice->currency,
                    'received_currency' => $remoteCurrency,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Payment does not match the invoice.',
                ], 422);
            }

            // The provider call happens outside the DB transaction, so the
            // invoice may have been voided/failed while verification was in
            // flight. Re-lock and re-check the current state before settlement.
            $outcome = DB::transaction(function () use ($invoice, $remote, $txRef): string {
                $lockedInvoice = SubscriptionInvoice::whereKey($invoice->getKey())
                    ->lockForUpdate()
                    ->first();

                if (! $lockedInvoice || (string) $lockedInvoice->agency_id !== (string) $invoice->agency_id) {
                    return 'not_found';
                }
                if ($lockedInvoice->status === 'paid') {
                    return 'already_paid';
                }
                if ($lockedInvoice->status !== 'pending') {
                    return 'not_pending';
                }

                $lockedInvoice->update([
                    'status' => 'paid',
                    'payment_method' => 'flutterwave',
                    'payment_reference' => $txRef,
                    'flutterwave_tx_id' => isset($remote['id']) ? (string) $remote['id'] : null,
                    'paid_at' => now(),
                ]);
                $this->activateSubscriptionFromInvoice($lockedInvoice);

                return 'activated';
            });

            if ($outcome === 'not_found') {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment reference not found.',
                ], 404);
            }
            if ($outcome === 'not_pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'This invoice is no longer payable.',
                ], 422);
            }
            $activated = $outcome === 'activated';

            if ($activated) {
                $this->writeAudit(
                    $request,
                    'billing.invoice.verify',
                    'invoice:' . $invoice->id,
                    'Flutterwave payment verified',
                    [
                        'agency_id' => $agencyId,
                        'entity_type' => 'subscription_invoice',
                        'entity_id' => $invoice->id,
                        'metadata' => ['tx_ref' => $txRef],
                    ]
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment verified.',
                // Only fields the agency already owns — never the gateway's
                // full customer/card payload.
                'data' => [
                    'tx_ref' => $txRef,
                    'status' => 'successful',
                    'amount' => (float) ($remote['amount'] ?? 0),
                    'currency' => (string) ($remote['currency'] ?? ''),
                    'transaction_id' => isset($remote['id']) ? (string) $remote['id'] : null,
                ],
            ]);
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

        // Signed Checkout events must still match the invoice created by this
        // application. Never accept missing payment fields or infer a settled
        // payment from checkout.session.completed alone (async methods can
        // complete the checkout flow before payment settles).
        if ((string) $agencyId !== (string) $invoice->agency_id
            || (string) $planId !== (string) $invoice->plan_id) {
            Log::warning('Stripe checkout: session metadata does not match invoice', [
                'session_id' => $session->id,
                'invoice_id' => $invoice->id,
            ]);
            return;
        }

        $paymentStatus = $session->payment_status ?? null;
        if (!in_array($paymentStatus, ['paid', 'no_payment_required'], true)) {
            Log::warning('Stripe checkout: session not settled', [
                'session_id' => $session->id, 'payment_status' => $paymentStatus,
            ]);
            return;
        }

        $amountTotal = $session->amount_total ?? null;
        $currency = strtolower(trim((string) ($session->currency ?? '')));
        $expectedCurrency = strtolower(trim((string) $invoice->currency));
        $expectedAmountMinor = (float) $invoice->total_pkr * 100;
        if (!is_numeric($amountTotal) || $currency === '' || $currency !== $expectedCurrency
            || abs((float) $amountTotal - $expectedAmountMinor) > 0.00001) {
            Log::warning('Stripe checkout: amount or currency does not match invoice', [
                'session_id' => $session->id,
                'amount_total' => $amountTotal,
                'currency' => $currency,
                'expected_amount_minor' => $expectedAmountMinor,
                'expected_currency' => $expectedCurrency,
            ]);
            return;
        }

        // Invoice settlement and subscription activation are one atomic
        // state transition. Stripe may retry/deliver the same event in
        // parallel; lock and re-check the invoice inside the transaction so
        // a paid invoice can never be committed without its subscription.
        $outcome = DB::transaction(function () use ($invoice, $session, $stripeSubId): string {
            $lockedInvoice = SubscriptionInvoice::whereKey($invoice->getKey())
                ->lockForUpdate()
                ->first();

            if (!$lockedInvoice) {
                return 'not_found';
            }
            if ($lockedInvoice->status === 'paid') {
                return 'already_paid';
            }
            if ($lockedInvoice->status !== 'pending') {
                return 'not_pending';
            }

            $lockedInvoice->update([
                'status' => 'paid',
                'stripe_payment_intent_id' => $session->payment_intent ?? null,
                'stripe_subscription_id' => $stripeSubId,
                'paid_at' => now(),
            ]);

            $this->activateSubscriptionFromInvoice($lockedInvoice);
            return 'activated';
        });

        if ($outcome === 'not_pending') {
            Log::warning('Stripe checkout: invoice is no longer payable', [
                'session_id' => $session->id,
                'invoice_id' => $invoice->id,
                'status' => $invoice->fresh()?->status,
            ]);
        }
    }

    private function handleStripeInvoicePaid($invoice): void
    {
        // Stripe Invoice uses the subscription field (or parent.subscription_details
        // on newer API versions), not stripe_subscription_id.
        $stripeSubId = $invoice->subscription
            ?? $invoice->parent?->subscription_details?->subscription
            ?? $invoice->stripe_subscription_id
            ?? null;
        if (!$stripeSubId) {
            return;
        }

        $invoiceLines = $invoice->lines->data ?? [];
        $firstLine = is_array($invoiceLines) ? ($invoiceLines[0] ?? null) : null;
        $periodEnd = $firstLine->period->end ?? null;
        $periodStart = $firstLine->period->start ?? null;

        DB::transaction(function () use ($stripeSubId, $periodEnd, $periodStart): void {
            $subscription = AgencySubscription::where('stripe_subscription_id', $stripeSubId)
                ->lockForUpdate()
                ->first();
            if (!$subscription) {
                return;
            }

            $updates = ['status' => 'active', 'cancelled_at' => null];
            if (is_numeric($periodEnd)) {
                $newEnd = \\Illuminate\\Support\\Carbon::createFromTimestamp((int) $periodEnd);
                if ($subscription->ends_at === null || $newEnd->greaterThan($subscription->ends_at)) {
                    $updates['ends_at'] = $newEnd;
                }
            }
            if (is_numeric($periodStart) && $subscription->starts_at === null) {
                $updates['starts_at'] = \\Illuminate\\Support\\Carbon::createFromTimestamp((int) $periodStart);
            }
            $subscription->update($updates);
        });
    }

    private function handleStripeInvoiceFailed($invoice): void
    {
        $stripeSubId = $invoice->subscription
            ?? $invoice->parent?->subscription_details?->subscription
            ?? $invoice->stripe_subscription_id
            ?? null;
        if (!$stripeSubId) {
            return;
        }

        DB::transaction(function () use ($stripeSubId): void {
            $subscription = AgencySubscription::where('stripe_subscription_id', $stripeSubId)
                ->lockForUpdate()
                ->first();
            $subscription?->update(['status' => 'past_due']);
        });
    }

    private function handleStripeSubscriptionChanged($subscription): void
    {
        $stripeSubId = $subscription->id ?? null;
        if (!$stripeSubId) {
            return;
        }

        DB::transaction(function () use ($stripeSubId, $subscription): void {
            $agencySubscription = AgencySubscription::where('stripe_subscription_id', $stripeSubId)
                ->lockForUpdate()
                ->first();
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
            $updates = ['status' => $status];
            $subscriptionItems = $subscription->items->data ?? [];
            $firstItem = is_array($subscriptionItems) ? ($subscriptionItems[0] ?? null) : null;
            $periodEnd = $subscription->current_period_end ?? $firstItem->current_period_end ?? null;
            $trialEnd = $subscription->trial_end ?? null;
            if (is_numeric($periodEnd)) {
                $updates['ends_at'] = \\Illuminate\\Support\\Carbon::createFromTimestamp((int) $periodEnd);
            }
            if (is_numeric($trialEnd)) {
                $updates['trial_ends_at'] = \\Illuminate\\Support\\Carbon::createFromTimestamp((int) $trialEnd);
            }
            if (($subscription->cancel_at_period_end ?? false) === true) {
                $updates['auto_renew'] = false;
            } elseif ($status === 'active') {
                $updates['auto_renew'] = true;
                $updates['cancelled_at'] = null;
            }
            if ($status === 'cancelled') {
                $updates['cancelled_at'] = now();
                $updates['auto_renew'] = false;
            }

            $agencySubscription->update($updates);
        });
    }

    private function activateSubscriptionFromInvoice(SubscriptionInvoice $invoice): void
    {
        $agencyId = $invoice->agency_id;
        $planId = $invoice->plan_id;

        // Serialize all plan activations for one tenant, including different
        // invoices/providers, so the final subscription row cannot be raced.
        Agency::whereKey($agencyId)->lockForUpdate()->firstOrFail();
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
