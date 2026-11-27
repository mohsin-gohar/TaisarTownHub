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

    public function test_public_agency_directory_uses_aggregate_counts_without_per_agency_queries(): void
    {
        foreach (['Agency Perf A', 'Agency Perf B', 'Agency Perf C'] as $name) {
            $agency = $this->makeAgency($name);
            $this->makePublicProperty($agency);
        }

        $countQueries = [];
        DB::listen(function (QueryExecuted $query) use (&$countQueries): void {
            $sql = strtolower(ltrim($query->sql));
            if (preg_match('/^select count\\(\\*\\) as aggregate from ["`]?(agency_members|properties)["`]?( where|$)/', $sql)) {
                $countQueries[] = $sql;
            }
        });

        $response = $this->getJson('/api/agencies')->assertOk();
        $this->assertCount(3, $response->json('agencies.data'));
        $this->assertSame(0, count($countQueries), 'directory card counts must be selected with the parent query, not counted once per agency');
        $this->assertSame(1, $response->json('agencies.data.0.sale_listing_count'));
    }

    public function test_location_index_aggregates_listing_counts_instead_of_counting_per_sector_and_category(): void
    {
        foreach (['Sector Perf A', 'Sector Perf B', 'Sector Perf C'] as $index => $name) {
            Sector::create([
                'id' => 'sec_perf_' . $index,
                'name' => $name,
                'slug' => 'sector-perf-' . $index,
                'phase' => '1',
                'is_active' => true,
            ]);
            PropertyCategory::create([
                'key' => 'perf-category-' . $index,
                'name' => 'Perf Category ' . $index,
                'group' => 'residential',
                'is_active' => true,
            ]);
        }

        $countQueries = [];
        DB::listen(function (QueryExecuted $query) use (&$countQueries): void {
            $sql = strtolower(ltrim($query->sql));
            if (preg_match('/^select count\\(\\*\\) as aggregate from ["`]properties["`] where ["`]properties["`]\\.["`](sector_id|category_id)["`]/', $sql)) {
                $countQueries[] = $sql;
            }
        });

        $response = $this->getJson('/api/locations')->assertOk();
        $this->assertCount(3, $response->json('sectors'));
        $this->assertCount(3, $response->json('categories'));
        $this->assertSame(0, count($countQueries), 'location listing counts must be aggregate subqueries, not one query per item');
    }

}
