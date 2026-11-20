<?php

namespace Tests\Feature;

/**
 * Guards the production payment integration contract.  The payment controller
 * uses Stripe's official SDK and these variables must be usable straight from
 * the supplied deployment template.
 */
class PaymentConfigurationTest extends EnterpriseTestCase
{
    public function test_stripe_sdk_is_installed(): void
    {
        $this->assertTrue(class_exists(\Stripe\StripeClient::class));
        $this->assertTrue(class_exists(\Stripe\Webhook::class));
    }

    public function test_payment_environment_template_matches_service_configuration(): void
    {
        $template = file_get_contents(base_path('.env.example'));

        foreach ([
            'STRIPE_KEY=',
            'STRIPE_SECRET=',
            'STRIPE_WEBHOOK_SECRET=',
            'FLUTTERWAVE_PUBLIC_KEY=',
            'FLUTTERWAVE_SECRET_KEY=',
            'FLUTTERWAVE_ENCRYPTION_KEY=',
            'FLUTTERWAVE_WEBHOOK_SECRET=',
        ] as $variable) {
            $this->assertStringContainsString($variable, $template);
        }

        $this->assertStringNotContainsString('PAYMENT_STRIPE_', $template);
        $this->assertStringNotContainsString('FLUTTERWAVE_API_KEY=', $template);
    }
}
