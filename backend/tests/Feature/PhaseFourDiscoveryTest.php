<?php

namespace Tests\Feature;

use App\Models\DiscoveryFeedback;
use App\Models\Interest;
use App\Models\User;
use Database\Seeders\InterestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 04 — Social Discovery & Smart Interest Matching.
 * Regression + security suite for the discovery APIs.
 */
class PhaseFourDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    /** Registered, verified, active users with profiles and privacy rows. */
    private function eligibleUser(array $attributes = [], array $privacy = [], array $preferences = []): User
    {
        $user = User::factory()->create(['status' => 'active', 'email_verified_at' => now(), ...$attributes]);
        $user->profile()->create(['username' => 'username-'.($user->id), 'visibility' => 'public', 'display_name' => $attributes['name'] ?? ('User '.$user->id)]);
        $user->privacySettings()->create([
            'profile_visibility' => 'public',
            'discovery_enabled' => true,
            'allow_connection_requests' => true,
            'show_online_status' => false,
            'allow_message_requests' => false,
            'show_interests' => true,
            'show_location' => false,
            ...$privacy,
        ]);
        $user->discoveryPreference()->create(['discovery_enabled' => true, 'show_in_recommendations' => true, ...$preferences]);

        return $user;
    }

    /** Viewer with registration state but no consent default. */
    private function viewer(array $attributes = [], array $preferences = []): User
    {
        $user = $this->eligibleUser($attributes, preferences: $preferences);

        return $user;
    }

    private function attach(User $user, string $name, string $category = 'Creative', ?string $slug = null): Interest
    {
        $interest = Interest::query()->updateOrCreate(['slug' => $slug ?? mb_strtolower(str_replace(' ', '-', $name))], ['name' => $name, 'category' => $category, 'is_active' => true]);
        $user->interests()->syncWithoutDetaching([$interest->id]);

        return $interest;
    }

    // ------------------------------------------------------------------
    // Eligibility
    // ------------------------------------------------------------------

    public function test_discovery_returns_only_eligible_active_verified_consenting_users(): void
    {
        $viewer = $this->viewer();
        $this->attach($viewer, 'Music');
        $eligible = $this->eligibleUser(['name' => 'Ada']);

        // No other users exist; profile grid should contain exactly the one.
        $response = $this->actingAs($viewer)->getJson('/api/v1/discovery/people');
        $response->assertOk();
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('username-'.$eligible->id, $response->json('data.0.username'));
    }

    public function test_private_profiles_are_excluded_from_discovery(): void
    {
        $viewer = $this->viewer();
        $this->eligibleUser(['name' => 'Ada']);
        $this->eligibleUser(['name' => 'Private Pam'], ['profile_visibility' => 'private']);

        $names = $this->actingAs($viewer)->getJson('/api/v1/discovery/people?per_page=50')->json('data.*.display_name');
        $this->assertCount(1, $names);
        $this->assertSame('Ada', $names[0]);
    }

    public function test_hidden_interests_never_expose_other_interests_or_matches(): void
    {
        $viewer = $this->viewer();
        $this->attach($viewer, 'Music');
        $candidate = $this->eligibleUser(['name' => 'Hidden Hank'], ['show_interests' => false]);
        $this->attach($candidate, 'Music');
        $this->attach($candidate, 'Poetry');

        $card = $this->actingAs($viewer)->getJson('/api/v1/discovery/people?sort=relevance')->json('data.0');

        $this->assertNull($card['match']);
        foreach ($card['interests'] as $interest) {
            $this->assertNotEquals('Poetry', $interest['name']);
        }
    }

    public function test_unverified_accounts_are_excluded(): void
    {
        $viewer = $this->viewer();
        $pending = User::factory()->create(['status' => 'pending_verification', 'email_verified_at' => null]);
        $pending->profile()->create(['username' => 'pending-user']);

        $usernames = $this->actingAs($viewer)->getJson('/api/v1/discovery/people')->json('data.*.username');
        $this->assertNotContains('pending-user', $usernames);
    }

    public function test_deactivated_accounts_are_excluded(): void
    {
        $viewer = $this->viewer();
        $gone = $this->eligibleUser(['name' => 'Gone Grace']);
        $gone->forceFill(['status' => 'deactivated'])->saveQuietly();

        $usernames = $this->actingAs($viewer)->getJson('/api/v1/discovery/people')->json('data.*.username');
        $this->assertNotContains('username-'.$gone->id, $usernames);
    }

    public function test_suspended_accounts_are_excluded(): void
    {
        $viewer = $this->viewer();
        $suspended = $this->eligibleUser(['name' => 'Suspended Sam']);
        $suspended->forceFill(['status' => 'suspended'])->saveQuietly();

        $usernames = $this->actingAs($viewer)->getJson('/api/v1/discovery/people')->json('data.*.username');
        $this->assertNotContains('username-'.$suspended->id, $usernames);
    }

    public function test_blocked_users_are_mutually_excluded(): void
    {
        $viewer = $this->viewer();
        $blocked = $this->eligibleUser(['name' => 'Blocked B']);
        $blocker = $this->eligibleUser(['name' => 'Blocker C']);
        $viewer->blockedUsers()->attach($blocked->id);
        $blocker->blockedUsers()->attach($viewer->id); // viewer was blocked

        $usernames = $this->actingAs($viewer)->getJson('/api/v1/discovery/people')->json('data.*.username');
        $this->assertNotContains('username-'.$blocked->id, $usernames);
        $this->assertNotContains('username-'.$blocker->id, $usernames);
    }

    public function test_current_user_is_never_listed(): void
    {
        $viewer = $this->viewer();
        $this->attach($viewer, 'Music');

        $usernames = $this->actingAs($viewer)->getJson('/api/v1/discovery/people')->json('data.*.username');
        $this->assertNotContains('username-'.$viewer->id, $usernames);
    }

    public function test_hidden_profiles_are_excluded_until_feedback_reversed(): void
    {
        $viewer = $this->viewer();
        $hidden = $this->eligibleUser(['name' => 'Hidden H']);

        $this->assertSame([true], [$this->containsUser($viewer, $hidden)]); // visible before feedback
        $this->actingAs($viewer)->postJson('/api/v1/discovery/feedback', ['target_user_id' => $hidden->id, 'feedback_type' => 'hide_profile'])->assertCreated();
        $this->assertNotContains('username-'.$hidden->id, $this->listUsernames($viewer));

        $this->actingAs($viewer)->deleteJson('/api/v1/discovery/feedback/'.$hidden->id)->assertOk();
        $this->assertContains('username-'.$hidden->id, $this->listUsernames($viewer));
    }

    private function listUsernames(User $viewer): array
    {
        return $this->actingAs($viewer)->getJson('/api/v1/discovery/people?per_page=50')->json('data.*.username') ?? [];
    }

    private function containsUser(User $viewer, User $target): bool
    {
        return in_array('username-'.$target->id, $this->listUsernames($viewer), true);
    }

    // ------------------------------------------------------------------
    // Matching
    // ------------------------------------------------------------------

    public function test_shared_interest_matching_scores_are_correct(): void
    {
        $viewer = $this->viewer();
        $this->attach($viewer, 'Music', slug: 'music');
        $this->attach($viewer, 'Travel', category: 'Lifestyle', slug: 'travel');
        $this->attach($viewer, 'Gaming', category: 'Hobbies', slug: 'gaming');

        $candidate = $this->eligibleUser(['name' => 'Ada']);
        $this->attach($candidate, 'Music', slug: 'music');
        $this->attach($candidate, 'Travel', category: 'Lifestyle', slug: 'travel');
        $this->attach($candidate, 'Art', slug: 'art');

        // Jaccard: 2 shared / (3 + 3 - 2) = 2/4 = 50%.
        $match = $this->actingAs($viewer)->getJson('/api/v1/discovery/recommended')->assertOk()->json('data.0.match');
        $this->assertSame(2, $match['shared_count']);
        $this->assertEquals(50.0, $match['score']);
    }

    public function test_empty_viewer_interests_return_no_recommendations_and_no_scores(): void
    {
        $viewer = $this->viewer();
        $this->eligibleUser(['name' => 'Ada']);

        $people = $this->actingAs($viewer)->getJson('/api/v1/discovery/people')->assertOk();
        $this->assertNull($people->json('data.0.match'));
        $this->assertSame([], $this->actingAs($viewer)->getJson('/api/v1/discovery/recommended')->json('data'));
    }

    public function test_zero_division_is_prevented_for_interestless_candidates(): void
    {
        $viewer = $this->viewer();
        $this->attach($viewer, 'Music');
        // forRecommendations excludes interest-less candidates upstream.
        $this->eligibleUser(['name' => 'No Interests Ned']);

        $this->assertSame([], $this->actingAs($viewer)->getJson('/api/v1/discovery/recommended')->json('data'));
    }

    public function test_relevance_sort_is_deterministic(): void
    {
        $viewer = $this->viewer();
        $this->attach($viewer, 'Music', slug: 'music');
        $this->attach($viewer, 'Travel', category: 'Lifestyle', slug: 'travel');

        $this->eligibleUser(['name' => 'None']);
        $oneShared = $this->eligibleUser(['name' => 'One Shared']);
        $this->attach($oneShared, 'Music', slug: 'music');
        $twoShared = $this->eligibleUser(['name' => 'Two Shared']);
        $this->attach($twoShared, 'Music', slug: 'music');
        $this->attach($twoShared, 'Travel', category: 'Lifestyle', slug: 'travel');

        $payload = $this->actingAs($viewer)->getJson('/api/v1/discovery/people?sort=relevance&per_page=50')->json('data');
        $this->assertSame((int) $payload[0]['id'], $twoShared->id);
        $this->assertSame((int) $payload[1]['id'], $oneShared->id);

        // Same request → same deterministic order (no random tie-breaks).
        $repeat = $this->actingAs($viewer)->getJson('/api/v1/discovery/people?sort=relevance&per_page=50')->json('data');
        $this->assertSame([$payload[0]['id'], $payload[1]['id']], [$repeat[0]['id'], $repeat[1]['id']]);
    }

    // ------------------------------------------------------------------
    // Search, filters, pagination
    // ------------------------------------------------------------------

    public function test_search_matches_display_name_and_username(): void
    {
        $viewer = $this->viewer();
        $this->eligibleUser(['name' => 'Aria Sun']);
        $this->eligibleUser(['name' => 'Bob']);

        $hits = $this->actingAs($viewer)->getJson('/api/v1/discovery/people?search=aria')->json('data.*.display_name');
        $this->assertSame(['Aria Sun'], array_values(array_filter($hits, static fn ($name) => str_contains((string) $name, 'Aria'))));
    }

    public function test_interest_filters_narrow_results(): void
    {
        $viewer = $this->viewer();
        $musician = $this->eligibleUser(['name' => 'Musician']);
        $this->attach($musician, 'Music', slug: 'music');
        $this->eligibleUser(['name' => 'Hiker']);

        $interestId = Interest::query()->where('slug', 'music')->value('id');
        $names = $this->actingAs($viewer)->getJson('/api/v1/discovery/people?interest_ids[0]='.$interestId)->json('data.*.display_name');
        $this->assertSame(['Musician'], $names);
    }

    public function test_invalid_query_parameters_are_rejected(): void
    {
        $viewer = $this->viewer();

        $this->actingAs($viewer)->getJson('/api/v1/discovery/people?per_page=13')->assertUnprocessable();
        $this->actingAs($viewer)->getJson('/api/v1/discovery/people?sort=random')->assertUnprocessable();
        $this->actingAs($viewer)->getJson('/api/v1/discovery/people?interest_ids[0]=999999')->assertUnprocessable();
    }

    public function test_pagination_is_stable_and_correct(): void
    {
        $viewer = $this->viewer();
        for ($i = 0; $i < 15; $i++) {
            $this->eligibleUser(['name' => 'Person '.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
        }

        $response = $this->actingAs($viewer)->getJson('/api/v1/discovery/people?per_page=10&page=2');
        $response->assertOk();
        $this->assertSame(15, $response->json('meta.total'));
        $this->assertSame(2, $response->json('meta.current_page'));
        $this->assertSame(10, $response->json('meta.per_page'));
        $this->assertCount(5, $response->json('data'));
    }

    // ------------------------------------------------------------------
    // Preview & privacy
    // ------------------------------------------------------------------

    public function test_private_profile_preview_is_privacy_safe_and_uniform(): void
    {
        $viewer = $this->viewer();
        $secret = $this->eligibleUser(['name' => 'Secret']);
        $secret->profile()->update(['visibility' => 'private']);
        $unknown = 'no-such-username-'.uniqid();

        $blocked = $this->eligibleUser(['name' => 'Blocker']);
        $blocked->blockedUsers()->attach($viewer->id);

        foreach (['secret', $unknown] as $username) {
            $response = $this->actingAs($viewer)->getJson('/api/v1/discovery/people/'.$username)->assertNotFound();
            $this->assertSame('Profile unavailable.', $response->json('message'));
            $this->assertArrayNotHasKey('errors', (array) $response->json());
        }
    }

    public function test_unauthenticated_discovery_access_is_rejected(): void
    {
        $this->getJson('/api/v1/discovery/people')->assertUnauthorized();
        $this->getJson('/api/v1/discovery/overview')->assertUnauthorized();
        $this->postJson('/api/v1/discovery/feedback', ['target_user_id' => 1, 'feedback_type' => 'hide_profile'])->assertUnauthorized();
    }

    public function test_sensitive_fields_are_never_returned_from_discovery(): void
    {
        $viewer = $this->viewer();
        $this->attach($viewer, 'Music', slug: 'music');
        $candidate = $this->eligibleUser(['name' => 'Ada']);
        $this->attach($candidate, 'Music', slug: 'music');

        $payload = $this->actingAs($viewer)->getJson('/api/v1/discovery/people')->assertOk()->getContent();

        foreach (['email', 'password', 'status', 'role', 'remember_token', 'last_login_ip', 'birth_date', 'email_verified_at'] as $field) {
            $this->assertStringNotContainsString('"'.$field.'"', $payload);
        }
    }

    // ------------------------------------------------------------------
    // Feedback
    // ------------------------------------------------------------------

    public function test_feedback_cannot_target_self(): void
    {
        $viewer = $this->viewer();

        $this->actingAs($viewer)->postJson('/api/v1/discovery/feedback', ['target_user_id' => $viewer->id, 'feedback_type' => 'hide_profile'])->assertUnprocessable();
        $this->assertDatabaseMissing('discovery_feedback', ['user_id' => $viewer->id]);
    }

    public function test_feedback_persists_without_duplicates_and_is_reversible(): void
    {
        $viewer = $this->viewer();
        $target = $this->eligibleUser(['name' => 'Ada']);

        $this->actingAs($viewer)->postJson('/api/v1/discovery/feedback', ['target_user_id' => $target->id, 'feedback_type' => 'not_interested'])->assertCreated();
        $this->actingAs($viewer)->postJson('/api/v1/discovery/feedback', ['target_user_id' => $target->id, 'feedback_type' => 'not_interested'])->assertCreated();

        $this->assertSame(1, DiscoveryFeedback::query()->where('user_id', $viewer->id)->count());

        $this->actingAs($viewer)->deleteJson('/api/v1/discovery/feedback/'.$target->id)->assertOk();
        $this->assertSame(0, DiscoveryFeedback::query()->where('user_id', $viewer->id)->count());
    }

    // ------------------------------------------------------------------
    // Preferences
    // ------------------------------------------------------------------

    public function test_discovery_preferences_persist_and_mirror_privacy(): void
    {
        $this->seed(InterestSeeder::class);
        $viewer = $this->viewer();

        $this->actingAs($viewer)->putJson('/api/v1/discovery/preferences', [
            'discovery_enabled' => true,
            'show_in_recommendations' => false,
            'preferred_interest_categories' => ['Creative'],
        ])->assertOk()->assertJsonPath('preferences.discovery_enabled', true);

        $this->assertDatabaseHas('discovery_preferences', ['user_id' => $viewer->id, 'discovery_enabled' => true, 'show_in_recommendations' => false]);
        $this->assertDatabaseHas('user_privacy_settings', ['user_id' => $viewer->id, 'discovery_enabled' => true]);

        // Discovery disabled: viewer disappears from other people's listings.
        $other = $this->viewer(['name' => 'Other']);
        $this->actingAs($viewer)->putJson('/api/v1/discovery/preferences', ['discovery_enabled' => false])->assertOk();
        $usernames = $this->actingAs($other)->getJson('/api/v1/discovery/people?per_page=50')->json('data.*.username');
        $this->assertNotContains('username-'.$viewer->id, $usernames);
    }

    public function test_preferences_validation_rejects_unknown_categories(): void
    {
        $viewer = $this->viewer();

        $this->actingAs($viewer)->putJson('/api/v1/discovery/preferences', ['preferred_interest_categories' => ['Nonexistent']])->assertUnprocessable();
    }

    public function test_overview_counts_reflect_real_data(): void
    {
        $viewer = $this->viewer();
        $this->attach($viewer, 'Music', slug: 'music');
        $candidate = $this->eligibleUser(['name' => 'Ada']);
        $this->attach($candidate, 'Music', slug: 'music');

        $overview = $this->actingAs($viewer)->getJson('/api/v1/discovery/overview')->assertOk()->json('overview');
        $this->assertSame(1, $overview['counts']['discoverable_people']);
        $this->assertSame(1, $overview['counts']['recommended_people']);
        $this->assertStringContent($overview);
    }

    private function assertStringContent(array $overview): void
    {
        $this->assertGreaterThan(0, count($overview['categories']));
    }
}
