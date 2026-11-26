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
            'success_url' => [
                'required',
                'url',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (!$this->isTrustedCheckoutReturnUrl((string) $value)) {
                        $fail('The checkout return URL must use a configured application origin.');
                    }
                },
            ],
            'cancel_url' => [
                'required',
                'url',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (!$this->isTrustedCheckoutReturnUrl((string) $value)) {
                        $fail('The checkout return URL must use a configured application origin.');
                    }
                },
            ],
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

        $amount = (int) round((float) $plan->price);

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
                        'unit_amount' => $amount * 100,
                        'recurring' => [
                            'interval' => $plan->interval ?? 'month',
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'success_url' => $this->appendCheckoutSessionId((string) $request->success_url),
                'cancel_url' => $request->cancel_url,
                'client_reference_id' => $agencyId,
                'metadata' => [
                    'agency_id' => $agencyId,
                    'plan_id' => $plan->id,
                ],
            ]);

            // Create a pending invoice using the canonical schema columns.
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
                $currency = strtoupper((string) ($payload['currency'] ?? ''));
                if ($amount !== null && (int) round((float) $amount) !== (int) $invoice->total_pkr) {
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

            $remoteAmount = (int) round((float) ($remote['amount'] ?? 0));
            $remoteCurrency = strtoupper((string) ($remote['currency'] ?? ''));

            if ($remoteCurrency !== strtoupper((string) $invoice->currency)
                || $remoteAmount !== (int) $invoice->total_pkr) {
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

            // The gateway call happens before the DB transaction. Re-check
            // the invoice under a row lock so concurrent verify requests can
            // activate a pending invoice only once.
            $activated = DB::transaction(function () use ($invoice, $remote, $txRef): bool {
                $lockedInvoice = SubscriptionInvoice::where('id', $invoice->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedInvoice->status !== 'pending') {
                    return false;
                }

                $lockedInvoice->update([
                    'status' => 'paid',
                    'payment_method' => 'flutterwave',
                    'payment_reference' => $txRef,
                    'flutterwave_tx_id' => isset($remote['id']) ? (string) $remote['id'] : null,
                    'paid_at' => now(),
                ]);
                $this->activateSubscriptionFromInvoice($lockedInvoice);

                return true;
            }, 3);

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

    /**
     * Checkout return URLs must point to a first-party origin. Accept the
     * configured SPA origins (CORS allow-list) and APP_URL, never arbitrary
     * user-supplied hosts that could turn Stripe Checkout into a phishing
     * redirect. Compare origins, not URL prefixes.
     */
    private function appendCheckoutSessionId(string $url): string
    {
        // Preserve existing query parameters and keep the fragment last.
        $fragment = '';
        $fragmentPosition = strpos($url, '#');
        if ($fragmentPosition !== false) {
            $fragment = substr($url, $fragmentPosition);
            $url = substr($url, 0, $fragmentPosition);
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . 'session_id={CHECKOUT_SESSION_ID}' . $fragment;
    }

    private function isTrustedCheckoutReturnUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $origin = strtolower($parts['scheme'] . '://' . $parts['host']
            . (isset($parts['port']) ? ':' . $parts['port'] : ''));

        $configuredOrigins = array_merge(
            (array) config('cors.allowed_origins', []),
            [(string) config('app.url', '')]
        );

        foreach ($configuredOrigins as $configured) {
            $allowed = parse_url((string) $configured);
            if (!is_array($allowed) || empty($allowed['scheme']) || empty($allowed['host'])) {
                continue;
            }

            $allowedOrigin = strtolower($allowed['scheme'] . '://' . $allowed['host']
                . (isset($allowed['port']) ? ':' . $allowed['port'] : ''));

            if (hash_equals($allowedOrigin, $origin)) {
                return true;
            }
        }

        return false;
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

        $outcome = DB::transaction(function () use ($session, $agencyId, $planId, $stripeSubId): string {
            $invoice = SubscriptionInvoice::where('stripe_session_id', $session->id)
                ->lockForUpdate()
                ->first();

            if (!$invoice) {
                return 'invoice_missing';
            }

            if ($invoice->status === 'paid') {
                return 'already_paid';
            }

            if ($invoice->status !== 'pending'
                || (string) $invoice->agency_id !== (string) $agencyId
                || (string) $invoice->plan_id !== (string) $planId) {
                return 'invoice_mismatch';
            }

            $paymentStatus = (string) ($session->payment_status ?? '');
            if (!in_array($paymentStatus, ['paid', 'no_payment_required'], true)) {
                return 'not_settled';
            }

            $sessionCurrency = strtolower((string) ($session->currency ?? ''));
            $invoiceCurrency = strtolower((string) $invoice->currency);
            $amountTotal = $session->amount_total ?? null;
            $expectedAmount = (int) $invoice->total_pkr * 100;

            if ($sessionCurrency === '' || $sessionCurrency !== $invoiceCurrency
                || !is_numeric($amountTotal) || (int) $amountTotal !== $expectedAmount) {
                return 'amount_or_currency_mismatch';
            }

            $invoice->update([
                'status' => 'paid',
                'stripe_payment_intent_id' => $session->payment_intent ?? null,
                'stripe_subscription_id' => $stripeSubId,
                'paid_at' => now(),
            ]);

            $this->activateSubscriptionFromInvoice($invoice);

            return 'activated';
        }, 3);

        if ($outcome !== 'activated' && $outcome !== 'already_paid') {
            Log::warning('Stripe checkout completion was not applied', [
                'session_id' => $session->id,
                'outcome' => $outcome,
            ]);
        }
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
