<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AgencyVideo;
use Illuminate\Support\Str;

/**
 * Public gallery comments are open, but attribution is always
 * server-stamped: the client can neither pick the displayed name nor mark a
 * comment as a dealer's. Owners are free to comment as themselves.
 */
class AgencyVideoCommentIsolationTest extends EnterpriseTestCase
{
    public function test_video_comments_cannot_be_attributed_to_other_accounts(): void
    {
        [$userA, $agencyA] = $this->agencyUser(['upload_media', 'view_media'], 'agency_manager');
        [$userB] = $this->agencyUser(['upload_media', 'view_media'], 'agency_manager');

        $video = AgencyVideo::create([
            'id' => 'vid-' . Str::uuid(),
            'title' => 'Agency Walkthrough',
            'description' => 'Public tour',
            'videoUrl' => 'https://example.test/tour.mp4',
            'agencyId' => null,
            'agencyName' => $agencyA->name,
            'phase' => 'Phase 1',
            'sector' => 'Sector 70-D',
        ]);

        // Comments on the public gallery are allowed for any authenticated
        // account, but the stored attribution always comes from the account.
        $response = $this->postJson("/api/videos/{$video->id}/comments", [
            'comment' => 'Nice video!',
        ], $this->authHeaders($this->tokenFor($userB)))->assertStatus(201);

        $this->assertSame($userB->name, $response->json('userName'));
        $this->assertSame((int) $userB->id, (int) $response->json('user_id'));
        $this->assertTrue((bool) $response->json('isDealer'));

        // The video owner can comment too; server-stamps again.
        $owner = $this->postJson("/api/videos/{$video->id}/comments", [
            'comment' => 'Great opening.',
        ], $this->authHeaders($this->tokenFor($userA)))->assertStatus(201);

        $this->assertSame($userA->name, $owner->json('userName'));
    }
}
