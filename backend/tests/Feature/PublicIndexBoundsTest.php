<?php

namespace Tests\Feature;

use App\Models\AgencyVideo;
use App\Models\Dealer;
use App\Models\Project;
use Illuminate\Support\Str;

/**
 * Public list endpoints must stay bounded: the frontend consumes a plain
 * JSON array, so the endpoints keep that shape while capping rows (newest
 * first) instead of streaming entire tables to unauthenticated visitors.
 * ?limit narrows further and is clamped server-side.
 */
class PublicIndexBoundsTest extends EnterpriseTestCase
{
    public function test_dealers_public_index_is_bounded_and_keeps_array_shape(): void
    {
        foreach (['Bound Dealer A', 'Bound Dealer B'] as $name) {
            Dealer::create([
                'id' => 'deal-' . Str::random(8),
                'fullName' => $name,
                'phone' => '03001234567',
            ]);
        }

        $all = $this->getJson('/api/dealers')->assertStatus(200);
        $this->assertIsArray($all->json());
        $this->assertCount(2, $all->json());

        $one = $this->getJson('/api/dealers?limit=1')->assertStatus(200);
        $this->assertCount(1, $one->json());
    }

    public function test_videos_public_index_is_bounded(): void
    {
        foreach (['Bound Video A', 'Bound Video B'] as $title) {
            AgencyVideo::create([
                'id' => 'vid-' . Str::random(8),
                'title' => $title,
                'videoUrl' => 'https://example.test/tour.mp4',
                'agencyName' => 'Bound Agency',
            ]);
        }

        $all = $this->getJson('/api/videos')->assertStatus(200);
        $this->assertIsArray($all->json());
        $this->assertCount(2, $all->json());

        $one = $this->getJson('/api/videos?limit=1')->assertStatus(200);
        $this->assertCount(1, $one->json());
    }

    public function test_projects_public_index_is_bounded(): void
    {
        foreach (['Bound Project A', 'Bound Project B'] as $title) {
            Project::create([
                'id' => 'proj-' . Str::random(8),
                'title' => $title,
            ]);
        }

        $all = $this->getJson('/api/projects')->assertStatus(200);
        $this->assertIsArray($all->json());
        $this->assertCount(2, $all->json());

        $one = $this->getJson('/api/projects?limit=1')->assertStatus(200);
        $this->assertCount(1, $one->json());
    }
}
