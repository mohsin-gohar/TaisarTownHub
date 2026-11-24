<?php

namespace Tests\Feature;

use App\Models\BusinessRecord;

/**
 * Public newsletter subscriptions are platform-owned records. They must not
 * be readable by agency tenants, must be idempotent, and must stay behind the
 * route's abuse throttle.
 */
class PublicSubscriptionTest extends EnterpriseTestCase
{
    public function test_public_subscription_is_platform_owned_and_hidden_from_agencies(): void
    {
        $this->postJson('/api/subscribers', ['email' => 'Buyer@Example.TEST'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $row = BusinessRecord::where('collection', 'subscribers')->firstOrFail();
        $this->assertNull($row->agency_id, 'public subscriptions are platform-owned');
        $this->assertSame('buyer@example.test', $row->payload['email']);

        [$user] = $this->agencyUser([
            'properties.view', 'view_properties', 'crm.view', 'view_inquiries',
        ]);

        $agencyBody = $this->getJson('/api/workspace', $this->authHeaders($this->tokenFor($user)))
            ->assertOk()
            ->json('subscribers');
        $this->assertSame([], $agencyBody, 'an agency must not see platform subscriber emails');

        $this->seedPlatformRole('super_admin');
        $admin = $this->makeUser('super_admin');
        $platformBody = $this->getJson('/api/workspace', $this->authHeaders($this->tokenFor($admin)))
            ->assertOk()
            ->json('subscribers');
        $this->assertContains($row->record_id, array_column($platformBody, 'id'));
    }

    public function test_public_subscription_normalizes_email_and_does_not_duplicate(): void
    {
        $this->postJson('/api/subscribers', ['email' => 'Buyer@Example.TEST'])->assertOk();
        $this->postJson('/api/subscribers', ['email' => '  buyer@example.test  '])->assertOk();

        $rows = BusinessRecord::where('collection', 'subscribers')->get();
        $this->assertCount(1, $rows);
        $this->assertSame('buyer@example.test', $rows->first()->payload['email']);
        $this->assertSame('sub-'.substr(sha1('buyer@example.test'), 0, 16), $rows->first()->record_id);
    }

    public function test_public_subscription_rejects_invalid_email(): void
    {
        $this->postJson('/api/subscribers', ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertSame(0, BusinessRecord::where('collection', 'subscribers')->count());
    }

    public function test_public_subscription_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/subscribers', ['email' => "subscriber{$i}@example.test"])
                ->assertOk();
        }

        $this->postJson('/api/subscribers', ['email' => 'blocked@example.test'])
            ->assertStatus(429);
    }
}
