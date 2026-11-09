<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use App\Services\Feed\ContentModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 06 — content reporting + moderator workflow (scenarios 28–29 in
 * the phase plan). Covers report creation, duplicate resistance,
 * moderation decisions, restoration and audit trails.
 */
class PhaseSixModerationTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    private function member(array $attributes = [], array $privacy = []): User
    {
        $user = User::factory()->create(['status' => 'active', 'email_verified_at' => now(), ...$attributes]);
        $user->profile()->create([
            'username' => strtolower(str_replace(' ', '', $attributes['name'] ?? 'user')).'-'.(++$this->sequence),
            'visibility' => 'public',
            'display_name' => $attributes['name'] ?? ('User '.$user->id),
        ]);
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

    private function moderator(): User
    {
        return $this->member(['name' => 'Mody', 'role' => 'moderator']);
    }

    public function test_28_user_can_report_a_post(): void
    {
        $author = $this->member(['name' => 'Rita']);
        $reporter = $this->member(['name' => 'Sora']);
        $post = Post::factory()->create(['user_id' => $author->id, 'body' => 'dump spot']);

        $response = $this->actingAs($reporter)->postJson("/api/v1/posts/{$post->id}/report", [
            'reason' => 'spam',
            'details' => 'Same link every hour.',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('user_reports', [
            'reporter_id' => $reporter->id,
            'reported_user_id' => $author->id,
            'reportable_type' => Post::class,
            'reportable_id' => $post->id,
            'reason' => 'spam',
            'status' => 'pending',
        ]);
    }

    public function test_28b_duplicate_reports_collapse_into_details(): void
    {
        $service = app(ContentModerationService::class);
        $author = $this->member(['name' => 'Timo']);
        $reporter = $this->member(['name' => 'Umao']);
        $post = Post::factory()->create(['user_id' => $author->id]);

        $first = $service->report($reporter, $post, 'spam', 'first detail');
        $second = $service->report($reporter, $post, 'spam', 'second detail');

        $this->assertFalse($first['duplicate']);
        $this->assertTrue($second['duplicate']);
        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(1, DB::table('user_reports')->where('reporter_id', $reporter->id)->count());
    }

    public function test_28c_report_requires_a_known_reason(): void
    {
        [$author, $reporter] = [$this->member(['name' => 'Vria']), $this->member(['name' => 'Wka'])];
        $post = Post::factory()->create(['user_id' => $author->id]);

        $this->actingAs($reporter)->postJson("/api/v1/posts/{$post->id}/report", ['reason' => 'nonsense'])
            ->assertStatus(422);
    }

    public function test_29_moderators_review_and_remove_violating_content(): void
    {
        $moderator = $this->moderator();
        $author = $this->member(['name' => 'Ximi']);
        $reporter = $this->member(['name' => 'Yuki']);
        $post = Post::factory()->create(['user_id' => $author->id, 'body' => 'bad words']);

        $report = app(ContentModerationService::class)->report($reporter, $post, 'hate_content', '...');

        // Queue is visible to moderators only.
        $this->actingAs($reporter)->getJson('/api/v1/moderator/feed-reports')->assertForbidden();

        $queue = $this->actingAs($moderator)->getJson('/api/v1/moderator/feed-reports');
        $queue->assertOk();
        $queue->assertJsonCount(1, 'data');
        $this->assertSame('hate_content', $queue->json('data.0.reason'));
        $this->assertSame('bad words', $queue->json('data.0.target.body'));

        // Review start + removal decision.
        $reviewed = $this->actingAs($moderator)->postJson("/api/v1/moderator/feed-reports/{$report['id']}/review");
        $reviewed->assertOk();

        $resolved = $this->actingAs($moderator)->patchJson("/api/v1/moderator/feed-reports/{$report['id']}", [
            'status' => 'resolved',
            'decision' => 'remove_content',
            'note' => 'Guideline violation confirmed.',
        ]);
        $resolved->assertOk();

        $post->refresh();
        $this->assertSame('removed', $post->status);

        // Report record reflects the decision + audit trail exists.
        $this->assertDatabaseHas('user_reports', ['id' => $report['id'], 'status' => 'resolved']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'moderation.report.resolved', 'auditable_id' => null]);
    }

    public function test_29b_removed_posts_hide_from_everything_and_can_be_restored(): void
    {
        $moderator = $this->moderator();
        $author = $this->member(['name' => 'Zvi']);
        $reporter = $this->member(['name' => 'Ammi']);
        $viewer = $this->member(['name' => 'Bnm']);
        $post = Post::factory()->create(['user_id' => $author->id, 'body' => 'questionable']);
        $report = app(ContentModerationService::class)->report($reporter, $post, 'inappropriate_content');

        DB::table('user_reports')->where('id', $report['id'])->update(['status' => 'resolved']);
        $post->forceFill(['status' => 'removed'])->save();

        $this->actingAs($viewer)->getJson("/api/v1/posts/{$post->id}")->assertNotFound();
        $this->actingAs($viewer)->getJson('/api/v1/feed?type=latest')->assertJsonCount(0, 'data');
        $this->actingAs($viewer)->getJson('/api/v1/posts/search?q=questionable')->assertJsonCount(0, 'data');

        // Restoration puts the content back.
        $this->actingAs($moderator)->patchJson("/api/v1/moderator/feed-reports/{$report['id']}", [
            'status' => 'resolved',
            'decision' => 'restore_content',
            'note' => 'False positive.',
        ])->assertOk();

        $post->refresh();
        $this->assertSame('published', $post->status);
        $this->actingAs($viewer)->getJson("/api/v1/posts/{$post->id}")->assertOk();
    }

    public function test_29c_comment_reports_flow_through_the_same_pipeline(): void
    {
        $moderator = $this->moderator();
        $author = $this->member(['name' => 'Ccc']);
        $reporter = $this->member(['name' => 'Ddd']);
        $post = Post::factory()->create(['user_id' => $author->id]);
        $comment = PostComment::query()->create(['post_id' => $post->id, 'user_id' => $author->id, 'body' => 'rude words']);

        $report = app(ContentModerationService::class)->report($reporter, $comment, 'harassment');

        $this->assertDatabaseHas('user_reports', ['reportable_type' => PostComment::class, 'reportable_id' => $comment->id]);

        $this->actingAs($moderator)->patchJson("/api/v1/moderator/feed-reports/{$report['id']}", [
            'status' => 'dismissed',
            'decision' => 'none',
            'note' => 'Context looked fine.',
        ])->assertOk();

        // Comment untouched on dismissal.
        $this->assertNull($comment->fresh()->deleted_at);
    }

    public function test_29d_posts_hiding_is_private_per_viewer(): void
    {
        [$author, $viewer, $other] = [$this->member(['name' => 'Eee']), $this->member(['name' => 'Fff']), $this->member(['name' => 'Ggg'])];
        $post = Post::factory()->create(['user_id' => $author->id]);

        $this->actingAs($viewer)->postJson("/api/v1/posts/{$post->id}/hide")->assertOk();

        $this->actingAs($viewer)->getJson('/api/v1/feed?type=latest')->assertJsonCount(0, 'data');
        // Other viewers still see the post.
        $this->actingAs($other)->getJson('/api/v1/feed?type=latest')->assertJsonCount(1, 'data');
    }
}
