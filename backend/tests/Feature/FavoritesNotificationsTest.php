<?php

namespace Tests\Feature;

use App\Models\PropertyFavorite;

/**
 * Per-account favorites (server-side, replaces per-browser localStorage) and
 * the in-app notification centre.
 */
class FavoritesNotificationsTest extends EnterpriseTestCase
{
    private function notify($user, array $data, $readAt = null)
    {
        return $user->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\\Notifications\\TestNotification',
            'data' => $data,
            'read_at' => $readAt,
        ]);
    }

    public function test_favorites_are_toggled_per_account_and_limited_to_public_listings(): void
    {
        [$user, $agency] = $this->agencyUser(['view_properties']);
        $public = $this->makePublicProperty($agency, ['title' => 'Favoritable Plot']);

        $this->postJson("/api/favorites/{$public->id}", [], $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(200)
            ->assertJsonPath('favorited', true)
            ->assertJsonPath('count', 1);

        $favorites = $this->getJson('/api/favorites', $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(200)
            ->json('favorites');
        $this->assertContains($public->id, array_column($favorites, 'id'));

        // Toggle again removes it.
        $this->postJson("/api/favorites/{$public->id}", [], $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(200)
            ->assertJsonPath('favorited', false);

        $this->assertDatabaseCount('property_favorites', 0);
    }

    public function test_non_public_listing_cannot_be_favorited(): void
    {
        [$user, $agency] = $this->agencyUser(['view_properties']);
        $private = $this->makePublicProperty($agency, [
            'title' => 'Private Draft',
            'approval_status' => 'draft',
            'visibility' => 'private',
        ]);

        $this->postJson("/api/favorites/{$private->id}", [], $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(404);

        $this->assertDatabaseCount('property_favorites', 0);
    }

    public function test_favorites_are_isolated_between_accounts(): void
    {
        [$userA, $agency] = $this->agencyUser(['view_properties']);
        [$userB] = $this->agencyUser(['view_properties'], 'agent', $agency);
        $property = $this->makePublicProperty($agency);

        $this->postJson("/api/favorites/{$property->id}", [], $this->authHeaders($this->tokenFor($userA)))
            ->assertStatus(200);

        $favoritesB = $this->getJson('/api/favorites', $this->authHeaders($this->tokenFor($userB)))
            ->assertStatus(200)
            ->json('favorites');
        $this->assertNotContains($property->id, array_column($favoritesB, 'id'));

        $this->assertDatabaseHas('property_favorites', [
            'user_id' => $userA->id,
            'property_id' => $property->id,
        ]);
    }

    public function test_notifications_are_listed_and_marked_read_per_account(): void
    {
        [$userA] = $this->agencyUser(['view_properties']);
        [$userB] = $this->agencyUser(['view_properties']);

        $first = $this->notify($userA, ['title' => 'First alert']);
        $this->notify($userA, ['title' => 'Second alert']);
        $foreign = $this->notify($userB, ['title' => 'Not yours']);

        $this->getJson('/api/notifications/unread-count', $this->authHeaders($this->tokenFor($userA)))
            ->assertStatus(200)
            ->assertJsonPath('unread_count', 2);

        $list = $this->getJson('/api/notifications', $this->authHeaders($this->tokenFor($userA)))
            ->assertStatus(200)
            ->assertJsonPath('unread_count', 2)
            ->json('notifications');
        $this->assertCount(2, $list);

        $this->postJson("/api/notifications/{$first->id}/read", [], $this->authHeaders($this->tokenFor($userA)))
            ->assertStatus(200)
            ->assertJsonPath('unread_count', 1);

        // Another account cannot mark someone else's notification read.
        $this->postJson("/api/notifications/{$foreign->id}/read", [], $this->authHeaders($this->tokenFor($userA)))
            ->assertStatus(404);

        $this->postJson('/api/notifications/read-all', [], $this->authHeaders($this->tokenFor($userA)))
            ->assertStatus(200)
            ->assertJsonPath('unread_count', 0);

        $this->assertEquals(0, $userA->unreadNotifications()->count());
        $this->assertEquals(1, $userB->unreadNotifications()->count());
    }

    public function test_unread_only_filter(): void
    {
        [$user] = $this->agencyUser(['view_properties']);

        $read = $this->notify($user, ['title' => 'Already read'], now());
        $this->notify($user, ['title' => 'Still unread']);

        $notifications = $this->getJson('/api/notifications?unreadOnly=1', $this->authHeaders($this->tokenFor($user)))
            ->assertStatus(200)
            ->json('notifications');

        $ids = array_column($notifications, 'id');
        $this->assertNotContains($read->id, $ids);
        $this->assertCount(1, $notifications);
    }

    public function test_notification_pagination_is_clamped_to_safe_bounds(): void
    {
        [$user] = $this->agencyUser(['view_properties']);
        $token = $this->authHeaders($this->tokenFor($user));

        $this->notify($user, ['title' => 'First']);
        $this->notify($user, ['title' => 'Second']);

        $this->getJson('/api/notifications?per_page=0', $token)
            ->assertOk()
            ->assertJsonPath('pagination.per_page', 1)
            ->assertJsonCount(1, 'notifications');

        $this->getJson('/api/notifications?per_page=500', $token)
            ->assertOk()
            ->assertJsonPath('pagination.per_page', 100)
            ->assertJsonCount(2, 'notifications');
    }

}
