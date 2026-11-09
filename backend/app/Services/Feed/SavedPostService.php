<?php

namespace App\Services\Feed;

use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Private save-for-later list. Saved access is re-checked at read time, so
 * when a post later becomes unavailable (private, blocked, removed,
 * deleted) it silently disappears from the saved view.
 */
class SavedPostService
{
    public function __construct(private readonly PostAccessService $access) {}

    /** Idempotent save. Returns [saved, count of saved posts]. */
    public function save(User $viewer, Post $post): array
    {
        if (! $this->access->canView($viewer, $post) || $post->status !== 'published') {
            throw ValidationException::withMessages(['post' => 'You cannot save this post.']);
        }

        DB::table('saved_posts')->updateOrInsert(
            ['user_id' => (int) $viewer->getKey(), 'post_id' => $post->getKey()],
            ['created_at' => now()],
        );

        return [true, DB::table('saved_posts')->where('user_id', (int) $viewer->getKey())->count()];
    }

    public function unsave(User $viewer, Post $post): array
    {
        DB::table('saved_posts')
            ->where('user_id', (int) $viewer->getKey())
            ->where('post_id', $post->getKey())
            ->delete();

        return [false, DB::table('saved_posts')->where('user_id', (int) $viewer->getKey())->count()];
    }

    /** Owner-only list; each row re-validated against current access. */
    public function list(User $owner, int $perPage = 10): LengthAwarePaginator
    {
        $page = $owner->savedPosts()
            ->whereHas('post', static function ($query): void {
                $query->where('status', 'published')->whereNull('posts.deleted_at');
            })
            ->with(['post.media', 'post.user.profile', 'post.hashtags', 'post.user:id,name,username'])
            ->orderByDesc('id')
            ->paginate($perPage);

        // Access is re-checked per row: saved posts that since became
        // inaccessible (private, removed, blocked) never render content.
        $page->getCollection()->filter(fn ($saved): bool => $this->access->canView($owner, $saved->post));

        return $page;
    }
}
