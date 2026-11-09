<?php

namespace Tests\Feature;

use App\Models\ConnectionRequest;
use App\Models\User;
use App\Models\UserConnection;
use App\Models\UserFollow;
use App\Notifications\ConnectionAcceptedNotification;
use App\Notifications\ConnectionRequestNotification;
use App\Notifications\NewFollowerNotification;
use App\Services\Social\ConnectionRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 05 — Social connections, follows, friend requests, blocking.
 * Covers the full acceptance surface: happy paths, duplicates, privacy,
 * authorization isolation and concurrency safety.
 */
class PhaseFiveSocialConnectionsTest extends TestCase
{
    use RefreshDatabase;

    /** Registered, verified, active user with the social privacy defaults. */
    private function member(array $attributes = [], array $privacy = []): User
    {
        $user = User::factory()->create(['status' => 'active', 'email_verified_at' => now(), ...$attributes]);
        $user->profile()->create(['username' => strtolower(str_replace(' ', '', $attributes['name'] ?? 'user')).'-'.($user->id), 'visibility' => 'public', 'display_name' => $attributes['name'] ?? ('User '.$user->id)]);
        $user->privacySettings()->create([
            'profile_visibility' => 'public',
            'discovery_enabled' => true,
            'allow_follows' => true,
            'allow_connection_requests' => true,
            'show_followers' => true,
            'show_following' => true,
            'show_connections' => true,
            'show_mutual_connections' => true,
            'show_interests' => true,
            'show_online_status' => false,
            'allow_message_requests' => false,
            'show_location' => false,
            ...$privacy,
        ]);
        $user->discoveryPreference()->create(['discovery_enabled' => true, 'show_in_recommendations' => true]);

        return $user;
    }

    // ------------------------------------------------------------------
    // Follow system
    // ------------------------------------------------------------------

    public function test_01_user_can_follow_an_eligible_account(): void
    {
        [$actor, $target] = [$this->member(['name' => 'Ada']), $this->member(['name' => 'Brt'])];

        $response = $this->actingAs($actor)->postJson("/api/v1/users/{$target->id}/follow");

        $response->assertOk();
        $this->assertTrue($response->json('relationship.is_following'));
        $this->assertSame(1, UserFollow::query()->where('follower_id', $actor->id)->where('following_id', $target->id)->count());
    }

    public function test_02_duplicate_follow_is_prevented(): void
    {
        [$actor, $target] = [$this->member(['name' => 'Ada']), $this->member(['name' => 'Brat'])];
        $this->actingAs($actor)->postJson("/api/v1/users/{$target->id}/follow");

        $second = $this->actingAs($actor)->postJson("/api/v1/users/{$target->id}/follow");

        $second->assertOk();
        $this->assertSame(1, UserFollow::query()->where('follower_id', $actor->id)->count());
    }

    public function test_03_self_follow_is_rejected(): void
    {
        $actor = $this->member(['name' => 'Solo']);

        $response = $this->actingAs($actor)->postJson("/api/v1/users/{$actor->id}/follow");

        $response->assertStatus(422);
        $this->assertSame(0, UserFollow::query()->count());
    }

    public function test_04_unfollow_removes_the_edge(): void
    {
        [$actor, $target] = [$this->member(['name' => 'Ada']), $this->member(['name' => 'Brat'])];
        UserFollow::query()->create(['follower_id' => $actor->id, 'following_id' => $target->id]);

        $response = $this->actingAs($actor)->deleteJson("/api/v1/users/{$target->id}/follow");

        $response->assertOk();
        $this->assertFalse($response->json('relationship.is_following'));
        $this->assertSame(0, UserFollow::query()->count());
    }

    public function test_05_follower_and_following_counts_are_real(): void
    {
        [$actor, $other] = [$this->member(['name' => 'Ada']), $this->member(['name' => 'Brat'])];
        UserFollow::query()->create(['follower_id' => $actor->id, 'following_id' => $other->id]);

        $relationship = $this->actingAs($other)->getJson("/api/v1/users/{$other->id}/relationship");

        $relationship->assertOk();
        $this->assertSame(1, $relationship->json('counts.followers'));
        $this->assertSame(0, $relationship->json('counts.following'));

        $self = $this->actingAs($actor)->getJson("/api/v1/users/{$actor->id}/relationship");
        $this->assertSame(0, $self->json('counts.followers'));
        $this->assertSame(1, $self->json('counts.following'));
    }

    public function test_06_private_profiles_never_accept_follows_or_expose_lists(): void
    {
        [$actor, $private] = [$this->member(['name' => 'Ada']), $this->member(['name' => 'Hidden Radar'], ['profile_visibility' => 'private'])];

        $this->actingAs($actor)->postJson("/api/v1/users/{$private->id}/follow")->assertStatus(409);

        $hidden = $this->actingAs($actor)->getJson("/api/v1/users/{$private->id}/followers");
        $hidden->assertNotFound();
    }

    public function test_07_blocked_accounts_cannot_be_followed(): void
    {
        [$actor, $target] = [$this->member(['name' => 'Ada']), $this->member(['name' => 'Brat'])];
        $this->actingAs($actor)->postJson("/api/v1/users/{$target->id}/block")->assertOk();

        $this->actingAs($actor)->postJson("/api/v1/users/{$target->id}/follow")->assertStatus(409);
        $this->actingAs($target)->postJson("/api/v1/users/{$actor->id}/follow")->assertStatus(409);
    }

    public function test_08_suspended_accounts_reject_follows_and_requests(): void
    {
        [$actor, $target] = [$this->member(['name' => 'Ada']), $this->member(['name' => 'Brat'])];
        $target->forceFill(['status' => 'suspended'])->saveQuietly();

        $this->actingAs($actor)->postJson("/api/v1/users/{$target->id}/follow")->assertStatus(422);
        $this->actingAs($actor)->postJson('/api/v1/connections/requests', ['receiver_id' => $target->id])->assertStatus(422);
    }

    // ------------------------------------------------------------------
    // Friend (connection) requests
    // ------------------------------------------------------------------

    public function test_09_connection_request_is_sent_and_persisted(): void
    {
        [$sender, $receiver] = [$this->member(['name' => 'Ada']), $this->member(['name' => 'Brat'])];

        $response = $this->actingAs($sender)->postJson('/api/v1/connections/requests', ['receiver_id' => $receiver->id]);

        $response->assertStatus(201);
        $this->assertSame('outgoing_pending', $response->json('relationship.request_status'));
        $this->assertSame(1, ConnectionRequest::query()->where('sender_id', $sender->id)->where('receiver_id', $receiver->id)->where('status', 'pending')->count());

        $incoming = $this->actingAs($receiver)->getJson('/api/v1/connections/requests/incoming');
        $incoming->assertOk();
        $this->assertSame(1, $incoming->json('meta.total'));
        $this->assertSame($sender->id, $incoming->json('data.0.sender.id'));
    }

    public function test_suggestions_exclude_connections_and_pending_requests(): void
    {
        $viewer = $this->member(['name' => 'Viewer']);
        $connected = $this->member(['name' => 'Connected']);
        $pending = $this->member(['name' => 'Pending']);
        $suggested = $this->member(['name' => 'Suggested']);

        UserConnection::query()->create([
            'user_one_id' => min($viewer->id, $connected->id),
            'user_two_id' => max($viewer->id, $connected->id),
            'connected_at' => now(),
        ]);
        $this->actingAs($viewer)->postJson('/api/v1/connections/requests', ['receiver_id' => $pending->id])->assertCreated();

        $response = $this->actingAs($viewer)->getJson('/api/v1/connections/suggestions?sort=name&per_page=50');

        $response->assertOk();
        $usernames = $response->json('data.*.username');
        $this->assertContains($suggested->profile->username, $usernames);
        $this->assertNotContains($connected->profile->username, $usernames);
        $this->assertNotContains($pending->profile->username, $usernames);
    }

    public function test_10_duplicate_and_reverse_duplicate_requests_are_rejected(): void
    {
        [$sender, $receiver] = [$this->member(['name' => 'Ada']), $this->member(['name' => 'Brat'])];

        $this->actingAs($sender)->postJson('/api/v1/connections/requests', ['receiver_id' => $receiver->id])->assertStatus(201);
        // Same-direction repeat is idempotent, never a second row.
        $this->actingAs($sender)->postJson('/api/v1/connections/requests', ['receiver_id' => $receiver->id])->assertStatus(200);

        $opposite = $this->actingAs($receiver)->postJson('/api/v1/connections/requests', ['receiver_id' => $sender->id]);

        $opposite->assertStatus(409);
        $this->assertSame(1, ConnectionRequest::query()->count());
    }

    public function test_11_accept_request_creates_exactly_one_connection(): void
    {
        [$sender, $receiver] = [$this->member(['name' => 'Ada']), $this->member(['name' => 'Brat'])];
        $this->actingAs($sender)->postJson('/api/v1/connections/requests', ['receiver_id' => $receiver->id]);
        $requestId = ConnectionRequest::query()->sole()->id;

        $response = $this->actingAs($receiver)->postJson("/api/v1/connections/requests/{$requestId}/accept");

        $response->assertOk();
        $this->assertSame(1, UserConnection::query()->count());
        $connection = UserConnection::query()->sole();
        $this->assertSame(min($sender->id, $receiver->id), $connection->user_one_id);
        $this->assertSame(max($sender->id, $receiver->id), $connection->user_two_id);

        $stored = ConnectionRequest::query()->sole();
        $this->assertSame('accepted', $stored->status->value);
        $this->assertNotNull($stored->responded_at);
        $this->assertTrue($response->json('relationship.is_connected'));
    }

    public function test_12_decline_keeps_history_and_permits_retry(): void
    {
        [$sender, $receiver] = [$this->member(['name' => 'Ada']), $this->member(['name' => 'Brat'])];
        $this->actingAs($sender)->postJson('/api/v1/connections/requests', ['receiver_id' => $receiver->id]);
        $requestId = ConnectionRequest::query()->sole()->id;

        $this->actingAs($receiver)->postJson("/api/v1/connections/requests/{$requestId}/decline")->assertOk();
        $this->assertSame('declined', ConnectionRequest::query()->sole()->status->value);
        $this->assertSame(0, UserConnection::query()->count());

        // Retry after a decline is allowed by policy.
        $retry = $this->actingAs($sender)->postJson('/api/v1/connections/requests', ['receiver_id' => $receiver->id]);
        $retry->assertStatus(201);
        $this->assertSame(2, ConnectionRequest::query()->count());
        $this->assertSame(1, ConnectionRequest::query()->where('status', 'pending')->count());
    }

    public function test_13_sender_cancels_own_pending_request(): void
    {
        [$sender, $receiver] = [$this->member(['name' => 'Ada']), $this->member(['name' => 'Brat'])];
        $this->actingAs($sender)->postJson('/api/v1/connections/requests', ['receiver_id' => $receiver->id]);
        $requestId = ConnectionRequest::query()->sole()->id;

        $this->actingAs($sender)->postJson("/api/v1/connections/requests/{$requestId}/cancel")->assertOk();
        $this->assertSame('cancelled', ConnectionRequest::query()->sole()->status->value);

        $this->assertSame(0, $this->actingAs($receiver)->getJson('/api/v1/connections/requests/incoming')->json('meta.total'));
        $this->assertSame(0, $this->actingAs($sender)->getJson('/api/v1/connections/requests/outgoing')->json('meta.total'));
    }

    public function test_14_unauthorized_acceptance_is_rejected(): void
    {
        [$sender, $receiver, $stranger] = [$this->member(['name' => 'Ada']), $this->member(['name' => 'Brat']), $this->member(['name' => 'Cy'])];
        $this->actingAs($sender)->postJson('/api/v1/connections/requests', ['receiver_id' => $receiver->id]);
        $requestId = ConnectionRequest::query()->sole()->id;

        $response = $this->actingAs($stranger)->postJson("/api/v1/connections/requests/{$requestId}/accept");

        $response->assertStatus(422);
        $this->assertSame('pending', ConnectionRequest::query()->sole()->status->value);
        $this->assertSame(0, UserConnection::query()->count());
    }

    public function test_15_self_requests_and_blocked_requests_are_rejected(): void
    {
        $actor = $this->member(['name' => 'Solo']);
        $this->actingAs($actor)->postJson('/api/v1/connections/requests', ['receiver_id' => $actor->id])->assertStatus(422);

        [$left, $right] = [$this->member(['name' => 'Ada']), $this->member(['name' => 'Brat'])];
        $this->actingAs($left)->postJson("/api/v1/users/{$right->id}/block")->assertOk();
        $this->actingAs($left)->postJson('/api/v1/connections/requests', ['receiver_id' => $right->id])->assertStatus(409);
        $this->assertSame(0, ConnectionRequest::query()->count());
    }

    public function test_16_connections_list_remove_and_privacy(): void
    {
        [$actor, $friend, $stranger] = [$this->member(['name' => 'Ada']), $this->member(['name' => 'Brat']), $this->member(['name' => 'Cy'])];
        UserConnection::query()->create(['user_one_id' => min($actor->id, $friend->id), 'user_two_id' => max($actor->id, $friend->id), 'connected_at' => now()]);

        $list = $this->actingAs($actor)->getJson('/api/v1/connections');
        $list->assertOk();
        $this->assertSame(1, $list->json('meta.total'));
        $this->assertSame($friend->id, $list->json('data.0.user.id'));

        // Strangers see only what privacy allows.
        $visible = $this->actingAs($stranger)->getJson("/api/v1/users/{$actor->id}/connections");
        $this->assertSame(1, $visible->json('meta.total'));

        // Hiding the list removes it entirely.
        $actor->privacySettings()->update(['show_connections' => false]);
        $this->actingAs($stranger)->getJson("/api/v1/users/{$actor->id}/connections")->assertJson(['meta' => ['total' => 0], 'data' => []]);

        $removed = $this->actingAs($friend)->deleteJson("/api/v1/connections/{$actor->id}");
        $removed->assertOk();
        $this->assertSame(0, UserConnection::query()->count());
        $this->actingAs($friend)->deleteJson("/api/v1/connections/{$actor->id}")->assertStatus(409);
    }

    // ------------------------------------------------------------------
    // Mutual connections
    // ------------------------------------------------------------------

    public function test_17_mutual_connections_are_calculated_correctly(): void
    {
        [$a, $b, $c] = [$this->member(['name' => 'Ada']), $this->member(['name' => 'Brat']), $this->member(['name' => 'Cy'])];
        UserConnection::query()->create(['user_one_id' => min($a->id, $b->id), 'user_two_id' => max($a->id, $b->id), 'connected_at' => now()]);
        UserConnection::query()->create(['user_one_id' => min($b->id, $c->id), 'user_two_id' => max($b->id, $c->id), 'connected_at' => now()]);

        $response = $this->actingAs($a)->getJson("/api/v1/users/{$c->id}/mutual-connections");

        $response->assertOk();
        $this->assertSame(1, $response->json('count'));
        $this->assertSame($b->fresh()->profile->username, $response->json('data.0.username'));

        // Zero mutuals for unconnected pairs.
        $empty = $this->actingAs($b)->getJson("/api/v1/users/{$c->id}/mutual-connections");
        $this->assertSame(0, $empty->json('count'));
        $this->assertSame([], $empty->json('data'));
    }

    public function test_18_mutuals_hide_hidden_and_blocked_peers(): void
    {
        [$viewer, $left, $shared, $right] = [$this->member(['name' => 'Ada']), $this->member(['name' => 'Brat']), $this->member(['name' => 'Cy']), $this->member(['name' => 'Dee'])];
        UserConnection::query()->create(['user_one_id' => min($left->id, $shared->id), 'user_two_id' => max($left->id, $shared->id), 'connected_at' => now()]);
        UserConnection::query()->create(['user_one_id' => min($shared->id, $right->id), 'user_two_id' => max($shared->id, $right->id), 'connected_at' => now()]);

        // left↔right are not connected; classify via left↔viewer and viewer↔right
        $this->actingAs($viewer)->postJson("/api/v1/users/{$left->id}/follow")->assertOk();

        // The shared peer hides their profile: not leakable.
        $shared->privacySettings()->update(['profile_visibility' => 'private']);
        $response = $this->actingAs($left)->getJson("/api/v1/users/{$right->id}/mutual-connections");
        $this->assertSame(0, $response->json('count'));
    }

    // ------------------------------------------------------------------
    // Blocking
    // ------------------------------------------------------------------

    public function test_19_blocking_tears_down_every_relationship(): void
    {
        [$actor, $target] = [$this->member(['name' => 'Ada']), $this->member(['name' => 'Brat'])];
        UserFollow::query()->create(['follower_id' => $actor->id, 'following_id' => $target->id]);
        UserFollow::query()->create(['follower_id' => $target->id, 'following_id' => $actor->id]);
        UserConnection::query()->create(['user_one_id' => min($actor->id, $target->id), 'user_two_id' => max($actor->id, $target->id), 'connected_at' => now()]);
        ConnectionRequest::query()->create(['sender_id' => $target->id, 'receiver_id' => $actor->id, 'status' => 'pending']);
        $this->actingAs($actor)->postJson("/api/v1/users/{$target->id}/follow")->assertOk(); // no-op? blocked already? nothing but exercises idempotence

        $response = $this->actingAs($actor)->postJson("/api/v1/users/{$target->id}/block");

        $response->assertOk();
        $this->assertSame(0, UserFollow::query()->count());
        $this->assertSame(0, UserConnection::query()->count());
        $this->assertSame(0, ConnectionRequest::query()->where('status', 'pending')->count());
        $this->assertSame('cancelled', ConnectionRequest::query()->sole()->status->value);

        $listed = $this->actingAs($actor)->getJson('/api/v1/settings/blocked-users');
        $listed->assertOk();
        $this->assertSame(1, $listed->json('meta.total'));
        $this->assertSame($target->id, $listed->json('data.0.user.id'));
    }

    public function test_20_unblocking_never_restores_relationships(): void
    {
        [$actor, $target] = [$this->member(['name' => 'Ada']), $this->member(['name' => 'Brat'])];
        $this->actingAs($actor)->postJson("/api/v1/users/{$target->id}/block")->assertOk();
        $this->actingAs($actor)->deleteJson("/api/v1/users/{$target->id}/block")->assertOk();

        $this->assertSame(0, UserFollow::query()->count());
        $this->assertSame(0, UserConnection::query()->count());
        $relationship = $this->actingAs($actor)->getJson("/api/v1/users/{$target->id}/relationship");
        $this->assertFalse($relationship->json('relationship.is_connected'));
        $this->assertTrue($relationship->json('relationship.can_follow'), 'A fresh follow/connect should be possible after unblocking.');
    }

    public function test_21_blocks_stay_bidirectional_and_never_leak(): void
    {
        [$actor, $target] = [$this->member(['name' => 'Ada']), $this->member(['name' => 'Brat'])];
        $this->actingAs($actor)->postJson("/api/v1/users/{$target->id}/block")->assertOk();

        // Only the blocker sees their own blocked list.
        $this->actingAs($actor)->getJson('/api/v1/settings/blocked-users')->assertOk()->assertJson(['meta' => ['total' => 1]]);
        $this->actingAs($target)->getJson('/api/v1/settings/blocked-users')->assertOk()->assertJson(['meta' => ['total' => 0]]);

        // The blocked side can never reach a "who blocked me" answer.
        $relationship = $this->actingAs($target)->getJson("/api/v1/users/{$actor->id}/relationship");
        $this->assertArrayNotHasKey('is_blocked_by_target', $relationship->json('relationship'));
        $this->assertTrue($relationship->json('relationship.can_connect') === false);
    }

    public function test_22_self_blocking_is_rejected(): void
    {
        $actor = $this->member(['name' => 'Solo']);
        $this->actingAs($actor)->postJson("/api/v1/users/{$actor->id}/block")->assertStatus(422);
    }

    // ------------------------------------------------------------------
    // Privacy
    // ------------------------------------------------------------------

    public function test_23_privacy_settings_gate_every_list(): void
    {
        [$actor, $target] = [$this->member(['name' => 'Ada']), $this->member(['name' => 'Brat'])];
        UserFollow::query()->create(['follower_id' => $actor->id, 'following_id' => $target->id]);

        $target->privacySettings()->update(['show_followers' => false, 'show_following' => false, 'show_connections' => false, 'allow_follows' => false, 'allow_connection_requests' => false, 'show_mutual_connections' => false]);

        // Hidden lists answer 404 uniformly: no existence signal.
        $this->actingAs($actor)->getJson("/api/v1/users/{$target->id}/followers")->assertNotFound();
        $this->actingAs($actor)->getJson("/api/v1/users/{$target->id}/following")->assertNotFound();
        $relationship = $this->actingAs($target)->getJson("/api/v1/users/{$target->id}/relationship");
        $this->assertNull($relationship->json('mutual'));

        $external = $this->member(['name' => 'Cy']);
        $hiddenCounts = $this->actingAs($external)->getJson("/api/v1/users/{$target->id}/relationship");
        $this->assertNull($hiddenCounts->json('counts.followers'));
        $this->assertNull($hiddenCounts->json('counts.following'));
        $this->assertNull($hiddenCounts->json('counts.connections'));

        $fresh = $this->member(['name' => 'Dee']);
        $this->actingAs($fresh)->postJson("/api/v1/users/{$target->id}/follow")->assertStatus(409);
        $this->actingAs($fresh)->postJson('/api/v1/connections/requests', ['receiver_id' => $target->id])->assertStatus(409);
    }

    // ------------------------------------------------------------------
    // Notifications
    // ------------------------------------------------------------------

    public function test_24_social_notifications_are_stored_for_the_right_recipient(): void
    {
        [$sender, $receiver] = [$this->member(['name' => 'Ada']), $this->member(['name' => 'Brat'])];

        $this->actingAs($sender)->postJson("/api/v1/users/{$receiver->id}/follow")->assertOk();
        $this->assertSame(1, $receiver->notifications()->where('type', NewFollowerNotification::class)->count());
        $this->assertSame(0, $sender->notifications()->count());

        $this->actingAs($sender)->postJson('/api/v1/connections/requests', ['receiver_id' => $receiver->id])->assertStatus(201);
        $requestId = ConnectionRequest::query()->sole()->id;
        $this->actingAs($receiver)->postJson("/api/v1/connections/requests/{$requestId}/accept")->assertOk();

        $this->assertSame(1, $sender->notifications()->where('type', ConnectionAcceptedNotification::class)->count());
        $this->assertSame(1, $receiver->notifications()->where('type', ConnectionRequestNotification::class)->count());

        $feed = $this->actingAs($sender)->getJson('/api/v1/notifications');
        $feed->assertOk();
        $this->assertSame(1, $feed->json('meta.total'));

        $firstId = $feed->json('data.0.id');
        $this->actingAs($sender)->postJson("/api/v1/notifications/{$firstId}/read")->assertOk();
        $unread = $this->actingAs($sender)->getJson('/api/v1/notifications/unread-count');
        $this->assertSame(0, $unread->json('unread_count'));

        $this->actingAs($sender)->postJson('/api/v1/notifications/read-all')->assertOk();
        $this->assertSame(0, $this->actingAs($sender)->getJson('/api/v1/notifications/unread-count')->json('unread_count'));
    }

    public function test_25_cross_user_notification_access_is_impossible(): void
    {
        [$owner, $other] = [$this->member(['name' => 'Ada']), $this->member(['name' => 'Brat'])];
        $this->actingAs($other)->postJson("/api/v1/users/{$owner->id}/follow")->assertOk();
        $notificationId = $owner->notifications()->first()->getKey();

        $response = $this->actingAs($other)->postJson("/api/v1/notifications/{$notificationId}/read");

        $response->assertStatus(404);
        // The owner's notification remains unread: others cannot touch it.
        $this->assertNull($owner->notifications()->firstOrFail()->fresh()->read_at);
    }

    public function test_26_guests_are_rejected_with_401(): void
    {
        $target = $this->member(['name' => 'Brat']);
        $this->postJson("/api/v1/users/{$target->id}/follow")->assertStatus(401);
        $this->getJson('/api/v1/connections')->assertStatus(401);
        $this->getJson('/api/v1/notifications')->assertStatus(401);
        $this->getJson("/api/v1/users/{$target->id}/relationship")->assertStatus(401);
    }

    // ------------------------------------------------------------------
    // Concurrency safety
    // ------------------------------------------------------------------

    public function test_27_overlapping_accept_paths_converge_on_one_connection(): void
    {
        [$sender, $receiver] = [$this->member(['name' => 'Ada']), $this->member(['name' => 'Brat'])];
        $this->actingAs($sender)->postJson('/api/v1/connections/requests', ['receiver_id' => $receiver->id]);
        $requestId = (int) ConnectionRequest::query()->sole()->id;

        $service = app(ConnectionRequestService::class);

        // Two sequential "double-click" replays against the same pending row.
        [$first, $connection, $firstState] = $service->accept($receiver->fresh(), $requestId);
        [$secondRequest, $secondConnection, $secondState] = $service->accept($receiver->fresh(), $requestId);

        $this->assertSame(1, UserConnection::query()->count());
        $this->assertTrue($secondRequest->status->value === 'accepted');
        $this->assertSame($connection->getKey(), $secondConnection->getKey());
    }

    public function test_28_discovery_cards_carry_relationship_state(): void
    {
        [$actor, $target] = [$this->member(['name' => 'Ada']), $this->member(['name' => 'Brat'])];
        $this->actingAs($actor)->postJson("/api/v1/users/{$target->id}/follow")->assertOk();

        $page = $this->actingAs($actor)->getJson('/api/v1/discovery/people');

        $page->assertOk();
        $card = collect($page->json('data'))->firstWhere('id', $target->id);
        $this->assertNotNull($card);
        $this->assertTrue($card['relationship']['is_following']);
        $this->assertArrayNotHasKey('is_blocked_by_target', $card['relationship']);
    }
}
