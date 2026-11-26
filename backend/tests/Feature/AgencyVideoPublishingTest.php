<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Dealer;
use Illuminate\Support\Str;

/**
 * Public video tours must not be attributable to an agency/dealer the
 * publisher does not control. The legacy payload stores a dealer id in
 * `agencyId`; attribution text used to be copied verbatim from the client,
 * letting any upload_media account label a tour with another agency's name.
 */
class AgencyVideoPublishingTest extends EnterpriseTestCase
{
    private function makeDealerFor(Agency $agency, string $name): Dealer
    {
        return Dealer::create([
            'id' => 'deal-' . Str::random(8),
            'fullName' => $name,
            'phone' => '03001234567',
            'agency_id' => $agency->id,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Video Tour',
            'description' => 'Walkthrough',
            'videoUrl' => 'https://example.test/tour.mp4',
            'agencyName' => 'Spoofed Agency',
            'dealerName' => 'Spoofed Dealer',
        ], $overrides);
    }

    public function test_agency_publisher_cannot_claim_a_foreign_dealer_or_agency(): void
    {
        [$user] = $this->agencyUser(['upload_media'], 'agency_manager');
        [, $foreign] = $this->agencyUser(['upload_media'], 'agency_manager');
        $foreignDealer = $this->makeDealerFor($foreign, 'Foreign Dealer');

        $headers = $this->authHeaders($this->tokenFor($user));

        $this->postJson('/api/videos', $this->payload(['agencyId' => $foreignDealer->id]), $headers)
            ->assertStatus(422);
        $this->postJson('/api/videos', $this->payload(['agencyId' => $foreign->id]), $headers)
            ->assertStatus(422);

        $this->assertDatabaseCount('agency_videos', 0);
    }

    public function test_agency_attribution_is_forced_to_the_real_records(): void
    {
        [$user, $agency] = $this->agencyUser(['upload_media'], 'agency_manager');
        $dealer = $this->makeDealerFor($agency, 'Real Dealer');

        $response = $this->postJson('/api/videos', $this->payload([
            'agencyId' => $dealer->id,
            'agencyName' => 'Fake Agency Name',
            'dealerName' => 'Fake Dealer Name',
        ]), $this->authHeaders($this->tokenFor($user)))->assertCreated();

        $this->assertSame($agency->name, $response->json('agencyName'));
        $this->assertSame($dealer->id, $response->json('agencyId'));
        $this->assertSame($dealer->fullName, $response->json('dealerName'));
    }

    public function test_platform_publisher_attribution_comes_from_the_dealer(): void
    {
        $admin = $this->makeUser('super_admin');
        [, $agency] = $this->agencyUser([], 'agency_manager');
        $dealer = $this->makeDealerFor($agency, 'Platform Dealer');

        $response = $this->postJson('/api/videos', $this->payload([
            'agencyId' => $dealer->id,
            'agencyName' => 'Fake Agency Name',
            'dealerName' => 'Fake Dealer Name',
        ]), $this->authHeaders($this->tokenFor($admin)))->assertCreated();

        $this->assertSame($agency->name, $response->json('agencyName'));
        $this->assertSame($dealer->id, $response->json('agencyId'));
        $this->assertSame($dealer->fullName, $response->json('dealerName'));
    }

    public function test_video_urls_must_be_https_and_directly_playable(): void
    {
        [$user] = $this->agencyUser(['upload_media'], 'agency_manager');
        $headers = $this->authHeaders($this->tokenFor($user));

        $this->postJson('/api/videos', $this->payload([
            'videoUrl' => 'http://cdn.example.com/tour.mp4',
        ]), $headers)->assertStatus(422);

        $this->postJson('/api/videos', $this->payload([
            'videoUrl' => 'https://www.youtube.com/watch?v=abc123',
        ]), $headers)->assertStatus(422);

        $this->postJson('/api/videos', $this->payload([
            'videoUrl' => 'https://cdn.example.com/tour.mp4',
            'thumbnailUrl' => 'http://cdn.example.com/poster.jpg',
        ]), $headers)->assertStatus(422);
    }

    public function test_publishing_requires_a_media_permission(): void
    {
        [$user] = $this->agencyUser(['properties.view'], 'agent');

        $this->postJson('/api/videos', $this->payload(), $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(403);
        $this->assertDatabaseCount('agency_videos', 0);
    }
}
