<?php

namespace App\Services\Feed;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostShare;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Single authority for "can this viewer access this content" across feed,
 * detail page, media delivery, comments, reactions, saves, shares, search
 * and reports. Every Phase 06 surface delegates here so no route can
 * disagree about privacy.
 */
class PostAccessService
{
    /** Drafts, moderated removals and soft-deleted posts: author only. */
    public function canView(User $viewer, Post $post): bool
    {
        if ($post->deleted_at !== null || $post->status === 'draft' || $post->status === 'removed') {
            return $viewer->is($post->user);
        }

        if ($viewer->is($post->user)) {
            return true;
        }

        if (! $post->user->isSociallyActive()) {
            return false;
        }

        if ($this->blockedBetween($viewer, $post->user)) {
            // Blocked pairs lose access to each other's content entirely —
            // fail closed so no protected (or public) post leaks.
            return false;
        }

        return match ($post->visibility) {
            'connections' => User::connectionWith((int) $viewer->getKey(), (int) $post->user->getKey()) !== null,
            'private' => false,
            default => true,
        };
    }

    public function canReact(User $viewer, Post $post): bool
    {
        return $post->status === 'published' && $this->canView($viewer, $post);
    }

    public function canComment(User $viewer, Post $post): bool
    {
        return $this->canReact($viewer, $post);
    }

    public function canViewComments(User $viewer, Post $post): bool
    {
        return $post->status === 'published' && $this->canView($viewer, $post);
    }

    /** Can `viewer` see `comment`? Delegates to the comment's post visibility. */
    public function canViewComment(User $viewer, PostComment $comment): bool
    {
        if ($comment->deleted_at !== null) {
            return false;
        }

        return $this->canViewComments($viewer, $comment->post);
    }

    /** Shares of shares are forbidden (no recursive share chains). */
    public function canShare(User $viewer, Post $post): bool
    {
        $isSharedPost = PostShare::query()->where('shared_post_id', $post->getKey())->exists();

        return ! $isSharedPost && $post->status === 'published' && $this->canView($viewer, $post);
    }

    /** Whether either side of this pair blocked the other (Phase 05 blocks). */
    public function blockedBetween(User $first, User $second): bool
    {
        $firstId = (int) $first->getKey();
        $secondId = (int) $second->getKey();

        return DB::table('user_blocks')
            ->where(static function ($query) use ($firstId, $secondId): void {
                $query->where(static function ($sub) use ($firstId, $secondId): void {
                    $sub->where('user_id', $firstId)->where('blocked_user_id', $secondId);
                })->orWhere(static function ($sub) use ($firstId, $secondId): void {
                    $sub->where('user_id', $secondId)->where('blocked_user_id', $firstId);
                });
            })
            ->exists();
    }

    /**
     * IDs of authors whose content the viewer must never see: people the
     * viewer blocked, and people who blocked the viewer.
     *
     * @return array<int, int>
     */
    public function excludedAuthorIds(User $viewer): array
    {
        $viewerId = (int) $viewer->getKey();

        return DB::table('user_blocks')
            ->where('user_id', $viewerId)
            ->orWhere('blocked_user_id', $viewerId)
            ->selectRaw('CASE WHEN user_id = ? THEN blocked_user_id ELSE user_id END as other_id', [$viewerId])
            ->groupBy('other_id')
            ->get()
            ->map(static fn ($row) => (int) $row->other_id)
            ->all();
    }

    /**
     * Feed-level exclusions: blocked pairs in either direction plus accounts
     * that are not socially active. Viewer's own posts are always kept.
     */
    public function scopeEligible(Builder $query, User $viewer): Builder
    {
        $excluded = $this->excludedAuthorIds($viewer);

        // Only authors whose account is active AND email-verified may surface.
        $eligibleAuthors = User::query()
            ->select('id')
            ->where('status', 'active')
            ->whereNotNull('email_verified_at');

        return $query
            ->where('posts.status', 'published')
            ->whereIn('posts.user_id', $eligibleAuthors)
            ->when($excluded !== [], static fn ($builder) => $builder->whereNotIn('posts.user_id', $excluded));
    }
}
