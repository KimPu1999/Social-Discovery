<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use App\Models\UserFollow;
use App\Services\Social\BlockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 06 — Social feed, posts, media, reactions, comments, saves,
 * shares, hashtags, search and privacy. Scenario numbering mirrors the
 * phase plan (01–27 here; moderation/reports in the companion test).
 */
class PhaseSixSocialFeedTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    /** Registered, verified, active member with social defaults. */
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

    /** A genuine PNG built with GD (used for content-based upload validation). */
    private function fakePng(int $width = 8, int $height = 8): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        $file = tempnam(sys_get_temp_dir(), 'png');
        imagepng($image, $file);
        imagedestroy($image);

        return new UploadedFile($file, 'photo.png', 'image/png', null, true);
    }

    private function postBody(User $user, string $key): array
    {
        return ['body' => "Post from {$user->id} {$key} #phase6"];
    }

    // ------------------------------------------------------------------
    // 01–03: creation basics
    // ------------------------------------------------------------------

    public function test_01_user_can_create_a_text_post(): void
    {
        $author = $this->member(['name' => 'Arya']);

        $response = $this->actingAs($author)->postJson('/api/v1/posts', $this->postBody($author, 'one'));

        $response->assertCreated();
        $response->assertJsonPath('post.body', "Post from {$author->id} one #phase6");
    }

    public function test_02_user_can_create_an_image_post(): void
    {
        Storage::fake('local');
        $author = $this->member(['name' => 'Bran']);

        $response = $this->actingAs($author)->post('/api/v1/posts', [
            'body' => 'Warm sunset photo',
            'media' => [$this->fakePng(640, 480), $this->fakePng(320, 240)],
        ]);

        $response->assertCreated();
        $media = $response->json('post.media');

        $this->assertCount(2, $media);
        $this->assertSame('image/png', $media[0]['mime_type']);
        $this->assertSame(640, $media[0]['width']);

        // Files live in private storage, referenced by random paths.
        foreach (DB::table('post_media')->get(['storage_path']) as $row) {
            Storage::disk('local')->assertExists($row->storage_path);
            $this->assertStringStartsWith('posts/', $row->storage_path);
        }
    }

    public function test_03_user_can_save_a_draft_and_published_later(): void
    {
        $author = $this->member(['name' => 'Cora']);

        $draft = $this->actingAs($author)->postJson('/api/v1/posts', [
            'body' => 'Draft copy',
            'status' => 'draft',
        ]);
        $draft->assertCreated();
        $draft->assertJsonPath('post.status', 'draft');

        $author->refresh();
        $postId = $draft->json('post.id');

        // Drafts are invisible to other members and to the feed surfaces.
        $reader = $this->member(['name' => 'Readr']);
        $this->actingAs($reader)->getJson("/api/v1/posts/{$postId}")->assertNotFound();

        $published = $this->actingAs($author)->postJson("/api/v1/posts/{$postId}/publish");
        $published->assertOk();
        $published->assertJsonPath('post.status', 'published');

        $this->actingAs($reader)->getJson("/api/v1/posts/{$postId}")->assertOk();
    }

    // ------------------------------------------------------------------
    // 04–09: editing, ownership, deletion, empty + validation
    // ------------------------------------------------------------------

    public function test_04_author_can_edit_own_post(): void
    {
        $author = $this->member(['name' => 'Dorn']);
        $post = Post::factory()->create(['user_id' => $author->id]);

        $response = $this->actingAs($author)->patchJson("/api/v1/posts/{$post->id}", [
            'body' => 'Updated body #edited',
            'visibility' => 'connections',
        ]);

        $response->assertOk();
        $this->assertSame('Updated body #edited', $post->fresh()->body);
        $this->assertSame('edited', $post->fresh()->hashtags->first()->slug);
    }

    public function test_05_user_cannot_edit_another_users_post(): void
    {
        $author = $this->member(['name' => 'Elda']);
        $post = Post::factory()->create(['user_id' => $author->id]);

        $intruder = $this->member(['name' => 'Fen']);

        $this->actingAs($intruder)->patchJson("/api/v1/posts/{$post->id}", ['body' => 'hijacked'])->assertNotFound();
        $this->assertNotSame('hijacked', $post->fresh()->body);
    }

    public function test_06_author_can_delete_own_post(): void
    {
        $author = $this->member(['name' => 'Gil']);
        $post = Post::factory()->create(['user_id' => $author->id]);

        $this->actingAs($author)->deleteJson("/api/v1/posts/{$post->id}")->assertOk();
        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }

    public function test_07_rejects_oversized_post_bodies(): void
    {
        $author = $this->member(['name' => 'Hate']);

        $response = $this->actingAs($author)->postJson('/api/v1/posts', [
            'body' => str_repeat('x', 5001),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('body');
    }

    public function test_08_rejects_empty_posts(): void
    {
        $author = $this->member(['name' => 'Ida']);

        $this->actingAs($author)->postJson('/api/v1/posts', [])->assertStatus(422);

        $this->actingAs($author)->postJson('/api/v1/posts', ['body' => '   '])->assertStatus(422);
    }

    public function test_09_rejects_invalid_media_payloads(): void
    {
        Storage::fake('local');
        $author = $this->member(['name' => 'Jax']);

        // An SVG (script-injection risk): must be rejected despite extension tricks.
        $svg = UploadedFile::fake()->createWithContent('snake.svg.php.png', '<svg onload="alert(1)"></svg>');

        $this->actingAs($author)->post('/api/v1/posts', [
            'body' => 'nasty',
            'media' => [$svg],
        ])->assertStatus(422);

        $this->assertSame(0, DB::table('posts')->count());
    }

    public function test_10_media_access_is_authorized(): void
    {
        Storage::fake('local');
        $author = $this->member(['name' => 'Keta']);

        $post = $this->actingAs($author)->post('/api/v1/posts', [
            'body' => 'photo walk',
            'media' => [$this->fakePng(40, 30)],
        ])->assertCreated();

        $mediaUrl = $post->json('post.media.0.url');
        $this->assertStringContainsString('/api/v1/feed/media/', $mediaUrl);

        $uuid = substr($mediaUrl, strrpos($mediaUrl, '/') + 1);

        // Nobody can fetch media without authentication.
        $this->get($mediaUrl)->assertNotFound();

        // The author can fetch their own attachment.
        $this->actingAs($author)->get($mediaUrl)->assertOk();

        // Total strangers only reach it when the post is public; a private media stays protected.
        $other = $this->member(['name' => 'Keto']);
        $this->actingAs($other)->get($mediaUrl)->assertOk(); // public post, viewer-visible

        // After the post is deleted its media route is gone.
        DB::table('post_media')->where('uuid', $uuid)->update(['post_id' => $post->id]);
        $this->actingAs($author)->deleteJson("/api/v1/posts/{$post->id}")->assertOk();
        $this->assertDatabaseMissing('post_media', ['uuid' => $uuid]);
    }

    // ------------------------------------------------------------------
    // 11–14: reactions + comments
    // ------------------------------------------------------------------

    public function test_11_user_likes_then_unlikes_a_post(): void
    {
        $author = $this->member(['name' => 'Lina']);
        $fan = $this->member(['name' => 'Milo']);
        $post = Post::factory()->create(['user_id' => $author->id]);

        $like = $this->actingAs($fan)->postJson("/api/v1/posts/{$post->id}/like");
        $like->assertOk();
        $like->assertJsonPath('liked', true);
        $like->assertJsonPath('count', 1);

        $this->assertDatabaseCount('post_reactions', 1);

        $unlike = $this->actingAs($fan)->deleteJson("/api/v1/posts/{$post->id}/like");
        $unlike->assertOk();
        $unlike->assertJsonPath('liked', false);
        $unlike->assertJsonPath('count', 0);
    }

    public function test_12_prevents_duplicate_likes(): void
    {
        $author = $this->member(['name' => 'Noor']);
        $fan = $this->member(['name' => 'Opin']);
        $post = Post::factory()->create(['user_id' => $author->id]);

        $this->actingAs($fan)->postJson("/api/v1/posts/{$post->id}/like")->assertOk();
        $this->actingAs($fan)->postJson("/api/v1/posts/{$post->id}/like")->assertOk();

        $this->assertDatabaseCount('post_reactions', 1);
    }

    public function test_13_user_creates_comment_and_reply(): void
    {
        $author = $this->member(['name' => 'Pria']);
        $commenter = $this->member(['name' => 'Quinn']);
        $post = Post::factory()->create(['user_id' => $author->id]);

        $comment = $this->actingAs($commenter)->postJson("/api/v1/posts/{$post->id}/comments", ['body' => 'Great share!']);
        $comment->assertCreated();

        $commentId = $comment->json('comment.id');

        $reply = $this->actingAs($author)->postJson("/api/v1/comments/{$commentId}/replies", ['body' => 'Thank you!']);
        $reply->assertCreated();
        $reply->assertJsonPath('comment.parent_id', $commentId);
    }

    public function test_14_author_edits_and_deletes_own_comment(): void
    {
        $author = $this->member(['name' => 'Raki']);
        $post = Post::factory()->create(['user_id' => $author->id]);

        $comment = $this->actingAs($author)->postJson("/api/v1/posts/{$post->id}/comments", ['body' => 'Original']);
        $comment->assertCreated();
        $commentId = $comment->json('comment.id');

        $this->actingAs($author)->patchJson("/api/v1/comments/{$commentId}", ['body' => 'Edited copy'])->assertOk();
        $this->assertSame('Edited copy', PostComment::query()->find($commentId)->body);

        $this->actingAs($author)->deleteJson("/api/v1/comments/{$commentId}")->assertOk();
        $this->assertSoftDeleted('post_comments', ['id' => $commentId]);
    }

    public function test_15_rejects_invalid_reply_parent(): void
    {
        $author = $this->member(['name' => 'Sana']);
        $other = $this->member(['name' => 'Tavo']);
        $post = Post::factory()->create(['user_id' => $author->id]);
        $otherPost = Post::factory()->create(['user_id' => $other->id]);

        $grandparent = PostComment::query()->create([
            'post_id' => $otherPost->id, 'user_id' => $other->id, 'body' => 'wrong post',
        ]);
        $great = PostComment::query()->create([
            'post_id' => $post->id, 'user_id' => $other->id, 'parent_id' => $grandparent->id, 'body' => 'reply',
        ]);

        // Reply to a top-level comment that belongs to a different post.
        $this->actingAs($author)->postJson("/api/v1/comments/{$grandparent->id}/replies", ['body' => 'x'])
            ->assertStatus(422);

        // Reply to a reply: one-level rule.
        $this->actingAs($author)->postJson("/api/v1/comments/{$great->id}/replies", ['body' => 'x'])
            ->assertStatus(422);
    }

    // ------------------------------------------------------------------
    // 16–18: saves + shares
    // ------------------------------------------------------------------

    public function test_16_save_and_unsave_a_post(): void
    {
        $author = $this->member(['name' => 'Una']);
        $saver = $this->member(['name' => 'Vera']);
        $post = Post::factory()->create(['user_id' => $author->id]);

        $this->actingAs($saver)->postJson("/api/v1/posts/{$post->id}/save")->assertOk();
        $this->actingAs($saver)->postJson("/api/v1/posts/{$post->id}/save")->assertOk();

        $this->assertDatabaseCount('saved_posts', 1);

        $list = $this->actingAs($saver)->getJson('/api/v1/me/saved-posts');
        $list->assertOk();
        $list->assertJsonCount(1, 'data');

        $this->actingAs($saver)->deleteJson("/api/v1/posts/{$post->id}/save")->assertOk();
        $this->assertDatabaseCount('saved_posts', 0);
    }

    public function test_17_saved_list_is_private_to_owner(): void
    {
        $author = $this->member(['name' => 'Wren']);
        $owner = $this->member(['name' => 'Xilo']);
        $snooper = $this->member(['name' => 'Yara']);
        $post = Post::factory()->create(['user_id' => $author->id]);

        $this->actingAs($owner)->postJson("/api/v1/posts/{$post->id}/save")->assertOk();

        $response = $this->actingAs($snooper)->getJson('/api/v1/me/saved-posts');
        $response->assertOk();
        $response->assertJsonCount(0, 'data'); // snooper sees their own (empty) list only
    }

    public function test_18_user_shares_a_public_post_into_their_feed(): void
    {
        $author = $this->member(['name' => 'Zed']);
        $sharer = $this->member(['name' => 'Anon']);
        $post = Post::factory()->create(['user_id' => $author->id, 'body' => 'Worth sharing #tips']);

        $response = $this->actingAs($sharer)->postJson("/api/v1/posts/{$post->id}/share", [
            'body' => 'Look at this.',
        ]);

        $response->assertCreated();
        $this->assertSame('Look at this.', $response->json('post.body'));
        $this->assertSame($post->id, $response->json('post.shared_from.post_id'));

        $this->assertDatabaseHas('post_shares', ['original_post_id' => $post->id]);
    }

    public function test_19_rejects_sharing_private_and_chained_posts(): void
    {
        $author = $this->member(['name' => 'Bato']);
        $sharer = $this->member(['name' => 'Cato']);
        $privatePost = Post::factory()->create(['user_id' => $author->id, 'visibility' => 'private']);

        // A private post is invisible to the sharer — 404 preserves privacy.
        $this->actingAs($sharer)->postJson("/api/v1/posts/{$privatePost->id}/share", ['body' => 'leak'])
            ->assertNotFound();

        // Shares of shares are never allowed.
        $public = Post::factory()->create(['user_id' => $author->id]);
        $sharedPost = $this->actingAs($sharer)->postJson("/api/v1/posts/{$public->id}/share", ['body' => 'one'])
            ->assertCreated()->json('post.id');

        $this->actingAs($sharer)->postJson("/api/v1/posts/{$sharedPost}/share", ['body' => 'two'])
            ->assertNotFound();
    }

    public function test_20_hidden_share_source_never_leaks(): void
    {
        $author = $this->member(['name' => 'Dato']);
        $sharer = $this->member(['name' => 'Eato']);
        $public = Post::factory()->create(['user_id' => $author->id, 'body' => 'public story']);

        $sharedId = $this->actingAs($sharer)->postJson("/api/v1/posts/{$public->id}/share", ['body' => 'spreading'])
            ->assertCreated()->json('post.id');

        // Connecting/removing access: original becomes private.
        $public->update(['visibility' => 'private']);

        $viewer = $this->member(['name' => 'Fato']);
        $response = $this->actingAs($viewer)->getJson("/api/v1/posts/{$sharedId}");
        $response->assertOk();
        $response->assertJsonPath('post.shared_from', null);
    }

    // ------------------------------------------------------------------
    // 21–26: visibility, privacy, blocks
    // ------------------------------------------------------------------

    public function test_21_public_posts_are_visible_in_feeds(): void
    {
        [$author, $viewer] = [$this->member(['name' => 'Gato']), $this->member(['name' => 'Hato'])];
        Post::factory()->create(['user_id' => $author->id, 'body' => 'open post']);

        $response = $this->actingAs($viewer)->getJson('/api/v1/feed?type=latest');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.body', 'open post');
    }

    public function test_22_connections_only_posts_need_a_connection(): void
    {
        [$author, $stranger, $friend] = [$this->member(['name' => 'Ito']), $this->member(['name' => 'Jto']), $this->member(['name' => 'Kto'])];
        $post = Post::factory()->connectionsOnly()->create(['user_id' => $author->id]);

        DB::table('user_connections')->insert([
            'user_one_id' => min($author->id, $friend->id),
            'user_two_id' => max($author->id, $friend->id),
            'connected_at' => now(),
        ]);

        $this->actingAs($stranger)->getJson("/api/v1/posts/{$post->id}")->assertNotFound();
        $this->actingAs($friend)->getJson("/api/v1/posts/{$post->id}")->assertOk();

        $this->actingAs($stranger)->getJson('/api/v1/feed?type=latest')->assertJsonCount(0, 'data');
        $this->actingAs($friend)->getJson('/api/v1/feed?type=latest')->assertJsonCount(1, 'data');
    }

    public function test_23_private_posts_stay_with_the_author(): void
    {
        [$author, $friend, $stranger] = [$this->member(['name' => 'Lto']), $this->member(['name' => 'Mto']), $this->member(['name' => 'Nto'])];
        $post = Post::factory()->private()->create(['user_id' => $author->id]);

        DB::table('user_connections')->insert([
            'user_one_id' => min($author->id, $friend->id),
            'user_two_id' => max($author->id, $friend->id),
            'connected_at' => now(),
        ]);

        $this->actingAs($friend)->getJson("/api/v1/posts/{$post->id}")->assertNotFound();
        $this->actingAs($stranger)->getJson("/api/v1/posts/{$post->id}")->assertNotFound();
        $this->actingAs($author)->getJson("/api/v1/posts/{$post->id}")->assertOk();
    }

    public function test_24_blocked_users_are_excluded_everywhere(): void
    {
        [$author, $blocker] = [$this->member(['name' => 'Oto']), $this->member(['name' => 'Pto'])];
        Post::factory()->create(['user_id' => $author->id, 'body' => 'from blocked author']);
        Post::factory()->create(['user_id' => $blocker->id, 'body' => 'from blocking user']);

        app(BlockService::class)->block($blocker, $author);

        // The blocker sees only their own post; the author only their own.
        $blockerFeed = $this->actingAs($blocker)->getJson('/api/v1/feed?type=latest');
        $blockerFeed->assertOk();
        $blockerFeed->assertJsonCount(1, 'data');
        $blockerFeed->assertJsonPath('data.0.body', 'from blocking user');

        $authorFeed = $this->actingAs($author)->getJson('/api/v1/feed?type=latest');
        $authorFeed->assertOk();
        $authorFeed->assertJsonCount(1, 'data');
        $authorFeed->assertJsonPath('data.0.body', 'from blocked author');

        // Commenting / liking across the block is impossible (404 keeps private parity).
        $blockerPost = Post::factory()->create(['user_id' => $author->id]); // new post deleted below
        $blockerPost->delete();

        $blockedAuthorPost = Post::factory()->create(['user_id' => $author->id, 'visibility' => 'connections']);
        $this->actingAs($blocker)->postJson("/api/v1/posts/{$blockedAuthorPost->id}/like")->assertNotFound();
    }

    public function test_25_suspended_authors_are_excluded_from_feeds(): void
    {
        [$suspended, $viewer] = [$this->member(['name' => 'Qto']), $this->member(['name' => 'Rto'])];
        Post::factory()->create(['user_id' => $suspended->id]);

        $suspended->forceFill(['status' => 'suspended'])->save();

        $this->actingAs($viewer)->getJson('/api/v1/feed?type=latest')->assertJsonCount(0, 'data');
    }

    public function test_26_feed_profile_and_search_privacy(): void
    {
        $hidden = $this->member(['name' => 'Sto']);
        Post::factory()->create(['user_id' => $hidden->id, 'visibility' => 'private', 'body' => 'secret #privatetag']);
        Post::factory()->create(['user_id' => $hidden->id, 'body' => 'visible #sharetags']);

        $viewer = $this->member(['name' => 'Tto']);

        // Accessing a private article through a profile listing is blocked.
        $this->actingAs($viewer)->getJson('/api/v1/users/'.$hidden->id.'/posts')->assertOk();
        $content = $this->actingAs($viewer)->getJson('/api/v1/users/'.$hidden->id.'/posts')->json('data');
        $this->assertCount(1, $content);

        $search = $this->actingAs($viewer)->getJson('/api/v1/posts/search?q=secret');
        $search->assertOk();
        $this->assertCount(0, $search->json('data'));

        $hashtag = $this->actingAs($viewer)->getJson('/api/v1/hashtags/privatetag/posts');
        $hashtag->assertOk();
        $this->assertCount(0, $hashtag->json('data'));
    }

    public function test_27_feed_pagination_is_cursor_stable(): void
    {
        $author = $this->member(['name' => 'Uto']);
        $viewer = $this->member(['name' => 'Vto']);

        $posts = [];
        foreach (range(1, 12) as $index) {
            $posts[] = Post::factory()->create([
                'user_id' => $author->id,
                'published_at' => now()->subMinutes(100 - $index),
                'body' => 'stream-'.$index,
            ]);
        }

        $first = $this->actingAs($viewer)->getJson('/api/v1/feed?type=latest&limit=5');
        $first->assertOk();
        $this->assertCount(5, $first->json('data'));
        $cursor = $first->json('meta.next_cursor');
        $this->assertNotNull($cursor);

        $second = $this->actingAs($viewer)->getJson('/api/v1/feed?type=latest&limit=5&cursor='.$cursor);
        $second->assertOk();
        $this->assertCount(5, $second->json('data'));

        // No duplicates, newest-first across both pages.
        $firstIds = collect($first->json('data'))->pluck('id');
        $secondIds = collect($second->json('data'))->pluck('id');
        $this->assertCount(0, $firstIds->intersect($secondIds));

        $fresh = collect([...$firstIds, ...$secondIds]);
        $this->assertSame($fresh->sortDesc()->values()->all(), $fresh->values()->all());
    }

    // ------------------------------------------------------------------
    // Interactions: hashtags, follow feeds, for_you ranking
    // ------------------------------------------------------------------

    public function test_28_following_feed_only_shows_followed_authors(): void
    {
        [$followed, $unfollowed, $viewer] = [$this->member(['name' => 'Wto']), $this->member(['name' => 'Xto']), $this->member(['name' => 'Yto'])];

        UserFollow::query()->create(['follower_id' => $viewer->id, 'following_id' => $followed->id]);
        Post::factory()->create(['user_id' => $followed->id, 'body' => 'from followed']);
        Post::factory()->create(['user_id' => $unfollowed->id, 'body' => 'from stranger']);

        $feed = $this->actingAs($viewer)->getJson('/api/v1/feed?type=following');
        $feed->assertOk();
        $feed->assertJsonCount(1, 'data');
        $feed->assertJsonPath('data.0.body', 'from followed');

        // Viewer's like/comment counts surface in card payloads.
        $this->assertSame(0, $feed->json('data.0.counts.reactions'));
        $this->assertFalse($feed->json('data.0.viewer.liked'));
    }

    public function test_29_for_you_feed_ranks_by_relevance_signals(): void
    {
        [$author, $viewer] = [$this->member(['name' => 'Zto']), $this->member(['name' => 'Ava'])];

        $interest = DB::table('interests')->insertGetId(['name' => 'pottery', 'slug' => 'pottery', 'category' => 'crafts', 'is_active' => true]);
        DB::table('user_interests')->insert(['user_id' => $viewer->id, 'interest_id' => $interest, 'created_at' => now()]);
        DB::table('user_interests')->insert(['user_id' => $author->id, 'interest_id' => $interest, 'created_at' => now()]);
        UserFollow::query()->create(['follower_id' => $viewer->id, 'following_id' => $author->id]);

        Post::factory()->create(['user_id' => $author->id, 'body' => 'about pottery life']);

        $feed = $this->actingAs($viewer)->getJson('/api/v1/feed?type=for_you');
        $feed->assertOk();
        $feed->assertJsonCount(1, 'data');

        $card = $feed->json('data.0');
        $this->assertSame(5, $card['relevance']); // 3 * shared interest + 2 * followed
        $this->assertTrue($card['relevance_parts']['following']);
        $this->assertFalse($card['relevance_parts']['connection']);
    }

    public function test_30_notifications_are_created_for_likes_comments_and_shares(): void
    {
        [$author, $fan] = [$this->member(['name' => 'Bea']), $this->member(['name' => 'Ben'])];
        $post = Post::factory()->create(['user_id' => $author->id]);

        // Like → notify author.
        $this->actingAs($fan)->postJson("/api/v1/posts/{$post->id}/like")->assertOk();
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', ['type' => 'App\Notifications\PostLikedNotification', 'notifiable_id' => $author->id]);

        // Comment → notify author (second notification).
        $commentId = $this->actingAs($fan)->postJson("/api/v1/posts/{$post->id}/comments", ['body' => 'nice!'])
            ->assertCreated()->json('comment.id');
        $this->assertDatabaseCount('notifications', 2);

        // The author likes their own post → no self-notification.
        $this->actingAs($author)->postJson("/api/v1/posts/{$post->id}/like")->assertOk();
        $this->assertDatabaseCount('notifications', 2);

        // Share → notify the original author.
        $this->actingAs($fan)->postJson("/api/v1/posts/{$post->id}/share", ['body' => 'boost'])
            ->assertCreated();
        $this->assertDatabaseCount('notifications', 3);

        $types = DB::table('notifications')->whereIn('type', [
            'App\Notifications\PostLikedNotification',
            'App\Notifications\PostCommentedNotification',
            'App\Notifications\PostSharedNotification',
        ])->pluck('type')->all();

        $this->assertContains('App\Notifications\PostLikedNotification', $types);
        $this->assertContains('App\Notifications\PostCommentedNotification', $types);
        $this->assertContains('App\Notifications\PostSharedNotification', $types);
    }
}
