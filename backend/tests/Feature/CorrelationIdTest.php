<?php

namespace Tests\Feature;

use Tests\Feature\EnterpriseTestCase;

/**
 * G-16 — every response carries an X-Request-Id (generated, or echoed from
 * the caller when sane), so support tickets and logs can be correlated.
 */
class CorrelationIdTest extends EnterpriseTestCase
{
    public function test_every_response_has_a_request_id(): void
    {
        $response = $this->getJson('/api/health')->assertOk();

        $id = $response->headers->get('X-Request-Id');
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            (string) $id,
            'a UUID request id is generated when the caller sends none'
        );
    }

    public function test_sane_client_request_ids_are_echoed(): void
    {
        $response = $this->getJson('/api/health', ['X-Request-Id' => 'req_abc123456789'])
            ->assertOk();

        $this->assertSame('req_abc123456789', $response->headers->get('X-Request-Id'));
    }

    public function test_hostile_request_ids_are_replaced(): void
    {
        // Too long + control chars — must never be echoed back.
        $response = $this->getJson('/api/health', [
            'X-Request-Id' => "evil\r\nSet-Cookie: x",
        ])->assertOk();

        $id = (string) $response->headers->get('X-Request-Id');
        $this->assertStringNotContainsString('Set-Cookie', $id);
        $this->assertStringNotContainsString("\n", $id);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $id
        );
    }

    public function test_error_responses_still_carry_the_request_id(): void
    {
        $response = $this->getJson('/api/properties/does-not-exist')->assertStatus(404);

        $this->assertNotNull($response->headers->get('X-Request-Id'));
    }
}
