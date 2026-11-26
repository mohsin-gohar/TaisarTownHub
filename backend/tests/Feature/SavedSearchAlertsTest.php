<?php

namespace Tests\Feature;

use App\Mail\SavedSearchMatchesMail;
use App\Models\Property;
use App\Models\SavedSearch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\EnterpriseTestCase;

/**
 * Saved marketplace searches + scheduled email alerts.
 *
 * The alert command is verified end-to-end with Mail::fake: only published,
 * public, matching listings may trigger an email, and each run window is
 * idempotent so users are not spammed on every scheduler tick.
 */
class SavedSearchAlertsTest extends EnterpriseTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_model_generates_a_prefixed_id_when_created_without_one(): void
    {
        [$user] = $this->agencyUser(['properties.view']);

        $search = SavedSearch::create([
            'user_id' => $user->id,
            'name' => 'Generated identifier',
            'filters' => ['sector' => 'Sector 63'],
            'frequency' => 'daily',
            'alerts_enabled' => true,
        ]);

        $this->assertMatchesRegularExpression('/^ss_[a-z0-9]{24}$/', (string) $search->id);
        $this->assertDatabaseHas('saved_searches', ['id' => $search->id, 'user_id' => $user->id]);
    }

    public function test_saved_searches_require_authentication_and_are_owned_by_the_account(): void
    {
        $this->asGuest();
        $this->getJson('/api/saved-searches')->assertStatus(401);

        [$user] = $this->agencyUser(['properties.view']);
        $token = $this->tokenFor($user);

        $created = $this->postJson('/api/saved-searches', [
            'name' => 'Sector 63 plots under 1 crore',
            'filters' => [
                'sector' => 'Sector 63',
                'type' => 'Residential Plot',
                'maxPrice' => 10000000,
                'possessionOnly' => true,
                'unknown_key' => 'must be dropped',
            ],
            'frequency' => 'instant',
        ], $this->authHeaders($token))
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('saved_search.name', 'Sector 63 plots under 1 crore')
            ->assertJsonPath('saved_search.frequency', 'instant')
            ->assertJsonPath('saved_search.alerts_enabled', true);

        $id = $created->json('saved_search.id');
        $this->assertStringStartsWith('ss_', $id);

        $stored = SavedSearch::findOrFail($id);
        $this->assertArrayNotHasKey('unknown_key', $stored->filters);
        $this->assertTrue($stored->filters['possessionOnly']);

        $this->getJson('/api/saved-searches', $this->authHeaders($token))
            ->assertOk()
            ->assertJsonCount(1, 'saved_searches')
            ->assertJsonPath('saved_searches.0.id', $id);

        $this->patchJson("/api/saved-searches/{$id}", [
            'name' => 'Renamed search',
            'alerts_enabled' => false,
        ], $this->authHeaders($token))
            ->assertOk()
            ->assertJsonPath('saved_search.name', 'Renamed search')
            ->assertJsonPath('saved_search.alerts_enabled', false);

        $this->deleteJson("/api/saved-searches/{$id}", [], $this->authHeaders($token))
            ->assertOk();

        $this->assertDatabaseMissing('saved_searches', ['id' => $id]);
    }

    public function test_sale_only_marketplace_rejects_rental_saved_searches_and_alerts(): void
    {
        Carbon::setTestNow('2026-11-16 11:00:00');
        [$user] = $this->agencyUser(['properties.view']);
        $token = $this->authHeaders($this->tokenFor($user));

        $this->postJson('/api/saved-searches', [
            'name' => 'Rental search',
            'filters' => ['listingPurpose' => 'rent'],
        ], $token)->assertStatus(422)->assertJsonValidationErrors(['filters.listingPurpose']);

        [$owner, $agency] = $this->agencyUser(['properties.view'], 'manager');
        // Simulate a legacy stored filter from before the sale-only rule.
        SavedSearch::create([
            'id' => 'ss_rent_' . str_pad('1', 16, 'x', STR_PAD_LEFT),
            'user_id' => $owner->id,
            'name' => 'Legacy rent filter',
            'filters' => ['sector' => 'Sector 45', 'listingPurpose' => 'rent'],
            'frequency' => 'instant',
            'alerts_enabled' => true,
        ]);

        // Alert windows only include listings created after the search.
        Carbon::setTestNow('2026-11-16 11:05:00');
        $sale = $this->makePublicProperty($agency, [
            'title' => 'Sale listing alert',
            'sector' => 'Sector 45',
            'listing_purpose' => 'sale',
        ]);
        $this->makePublicProperty($agency, [
            'title' => 'Legacy rental listing alert',
            'sector' => 'Sector 45',
            'listing_purpose' => 'rent',
        ]);

        Mail::fake();
        $this->artisan('saved-searches:send-alerts')->assertSuccessful();

        Mail::assertSent(SavedSearchMatchesMail::class, 1);
        Mail::assertSent(SavedSearchMatchesMail::class, fn (SavedSearchMatchesMail $mail) =>
            $mail->matches->pluck('id')->all() === [$sale->id]
        );
    }

    public function test_saved_searches_are_isolated_between_accounts(): void
    {
        [$owner] = $this->agencyUser(['properties.view']);
        [$intruder] = $this->agencyUser(['properties.view']);

        $id = $this->postJson('/api/saved-searches', [
            'name' => 'Private saved search',
            'filters' => ['sector' => 'Sector 71'],
        ], $this->authHeaders($this->tokenFor($owner)))
            ->assertStatus(201)
            ->json('saved_search.id');

        $this->getJson('/api/saved-searches', $this->authHeaders($this->tokenFor($intruder)))
            ->assertOk()
            ->assertJsonCount(0, 'saved_searches');

        // Enumeration-safe: another account's id is a 404, not a 403.
        $this->patchJson("/api/saved-searches/{$id}", ['name' => 'Hijacked'], $this->authHeaders($this->tokenFor($intruder)))
            ->assertStatus(404);
        $this->deleteJson("/api/saved-searches/{$id}", [], $this->authHeaders($this->tokenFor($intruder)))
            ->assertStatus(404);

        $this->assertDatabaseHas('saved_searches', ['id' => $id, 'name' => 'Private saved search']);
    }

    public function test_saved_search_validation_and_limit(): void
    {
        [$user] = $this->agencyUser(['properties.view']);
        $token = $this->tokenFor($user);

        $this->postJson('/api/saved-searches', [
            'name' => 'Bad range',
            'filters' => ['minPrice' => 9000000, 'maxPrice' => 1000000],
        ], $this->authHeaders($token))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['filters.minPrice']);

        $this->postJson('/api/saved-searches', [
            'name' => '',
            'filters' => [],
        ], $this->authHeaders($token))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        // Fill the per-account quota directly; the API must refuse #21.
        for ($i = 0; $i < 20; $i++) {
            SavedSearch::create([
                'id' => 'ss_' . str_pad((string) $i, 24, 'x', STR_PAD_LEFT),
                'user_id' => $user->id,
                'name' => 'Saved ' . $i,
                'filters' => ['sector' => 'Sector 63'],
                'frequency' => 'daily',
                'alerts_enabled' => true,
            ]);
        }

        $this->postJson('/api/saved-searches', [
            'name' => 'One too many',
            'filters' => ['sector' => 'Sector 63'],
        ], $this->authHeaders($token))
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_alert_command_emails_only_new_public_matches_and_never_duplicates(): void
    {
        Carbon::setTestNow('2026-11-16 10:00:00');
        [$user, $agency] = $this->agencyUser(['properties.view']);

        $id = $this->postJson('/api/saved-searches', [
            'name' => 'Sector 63 plots',
            'filters' => ['sector' => 'Sector 63', 'maxPrice' => 10000000],
            'frequency' => 'instant',
        ], $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(201)
            ->json('saved_search.id');

        $search = SavedSearch::findOrFail($id);
        $this->assertNotNull($search->last_run_at, 'saving a search starts its alert window at save time');

        Carbon::setTestNow('2026-11-16 10:05:00');

        $match = $this->makePublicProperty($agency, [
            'title' => 'Corner plot in Sector 63',
            'sector' => 'Sector 63',
            'pricePKR' => 9000000,
        ]);
        // A draft, an over-budget listing and a different sector must never be
        // emailed even though they are created inside the same window.
        $this->makePublicProperty($agency, [
            'title' => 'Draft in Sector 63',
            'sector' => 'Sector 63',
            'approval_status' => 'draft',
            'visibility' => 'private',
        ]);
        $this->makePublicProperty($agency, [
            'title' => 'Penthouse over budget',
            'sector' => 'Sector 63',
            'pricePKR' => 90000000,
        ]);
        $this->makePublicProperty($agency, [
            'title' => 'Plot in another sector',
            'sector' => 'Sector 71',
            'pricePKR' => 8000000,
        ]);

        Mail::fake();

        $this->artisan('saved-searches:send-alerts')
            ->expectsOutputToContain('Sent 1 saved-search alert(s)')
            ->assertSuccessful();

        Mail::assertSent(SavedSearchMatchesMail::class, 1);
        Mail::assertSent(SavedSearchMatchesMail::class, function (SavedSearchMatchesMail $mail) use ($user, $id, $match) {
            return $mail->hasTo($user->email)
                && $mail->search->id === $id
                && $mail->matches->pluck('id')->all() === [$match->id];
        });
        Mail::sent(SavedSearchMatchesMail::class)->first()->assertSeeInHtml($match->title);

        $search->refresh();
        $this->assertNotNull($search->last_notified_at);

        // Running the sweep again immediately must not resend the same listing.
        $this->artisan('saved-searches:send-alerts')->assertSuccessful();
        Mail::assertSent(SavedSearchMatchesMail::class, 1);
    }

    public function test_daily_alerts_respect_their_window_and_disabled_alerts_never_send(): void
    {
        Carbon::setTestNow('2026-11-16 10:00:00');
        [$user, $agency] = $this->agencyUser(['properties.view']);
        $token = $this->tokenFor($user);

        $dailyId = $this->postJson('/api/saved-searches', [
            'name' => 'Daily Sector 70-D',
            'filters' => ['sector' => 'Sector 70-D'],
            'frequency' => 'daily',
        ], $this->authHeaders($token))->json('saved_search.id');

        $disabledId = $this->postJson('/api/saved-searches', [
            'name' => 'Disabled alerts',
            'filters' => ['sector' => 'Sector 70-D'],
            'frequency' => 'instant',
            'alerts_enabled' => false,
        ], $this->authHeaders($token))->json('saved_search.id');

        Carbon::setTestNow('2026-11-16 10:30:00');
        $this->makePublicProperty($agency, ['title' => 'Daily match', 'sector' => 'Sector 70-D']);

        Mail::fake();
        $this->artisan('saved-searches:send-alerts')->assertSuccessful();
        Mail::assertNothingSent();

        Carbon::setTestNow('2026-11-17 10:31:00');
        $this->artisan('saved-searches:send-alerts')->assertSuccessful();

        Mail::assertSent(SavedSearchMatchesMail::class, 1);
        Mail::assertSent(SavedSearchMatchesMail::class, fn (SavedSearchMatchesMail $mail) => $mail->search->id === $dailyId && $mail->hasTo($user->email));

        $this->assertDatabaseHas('saved_searches', [
            'id' => $disabledId,
            'alerts_enabled' => false,
            'last_notified_at' => null,
        ]);
    }

    public function test_support_staff_can_load_assignable_staff_but_agency_members_cannot(): void
    {
        [$member] = $this->agencyUser(['properties.view']);

        $this->getJson('/api/admin/support-tickets/assignable-staff', $this->authHeaders($this->tokenFor($member)))
            ->assertStatus(403);

        $this->seedPlatformRole('support');
        $support = $this->makeUser('support');

        $this->getJson('/api/admin/support-tickets/assignable-staff', $this->authHeaders($this->tokenFor($support)))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['id' => $support->id, 'role' => 'support']);
    }
}
