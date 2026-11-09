<?php

namespace App\Services\Feed;

use App\Models\Post;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Social\BlockService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Post lifecycle: create (draft or published), edit, publish, delete,
 * per-user and per-author listings. Media + hashtags are written inside
 * the same transaction as the post row.
 */
class PostService
{
    public function __construct(
        private readonly PostMediaService $media,
        private readonly HashtagService $hashtags,
        private readonly PostAccessService $access,
        private readonly AuditLogger $audit,
    ) {}

    /** Create a post. `status` comes from validated request data. */
    public function create(User $author, array $data, array $files = []): Post
    {
        return DB::transaction(function () use ($author, $data, $files): Post {
            $this->assertMediaAllowed($author, $files);

            $post = Post::query()->create([
                'user_id' => $author->getKey(),
                'body' => $data['body'] ?? null,
                'visibility' => $data['visibility'] ?? 'public',
                'status' => $data['status'] ?? 'published',
                'published_at' => ($data['status'] ?? 'published') === 'published' ? now() : null,
            ]);

            if ($files !== []) {
                $this->assertMediaAllowed($files);
                $this->media->attach($post, $files);
            }

            $this->hashtags->syncForPost($post);

            return $post;
        });
    }

    /**
     * Edit the author's own post. Status is never editable through this
     * action — moderation-removed and published states move via explicit
     * publish/moderation endpoints only.
     */
    public function update(User $author, Post $post, array $data, array $files = [], array $removedMediaIds = []): Post
    {
        return DB::transaction(function () use ($author, $post, $data, $files, $removedMediaIds): Post {
            $post->forceFill([
                'body' => $data['body'] ?? $post->body,
                'visibility' => $data['visibility'] ?? $post->visibility,
                'edited_at' => now(),
            ])->save();

            if ($removedMediaIds !== []) {
                $this->media->detach($post, $removedMediaIds);
            }

            if ($files !== []) {
                $this->assertMediaAllowed($files, $post->media()->count());
                $this->media->attach($post, $files);
            }

            $this->hashtags->syncForPost($post);

            $this->audit->record('post.updated', $author, $post, ['visibility' => $post->visibility]);

            return $post->refresh();
        });
    }

    /** Draft → published. One-way; drafts cannot go back to draft. */
    public function publish(User $author, Post $post): Post
    {
        if ($post->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Only drafts can be published.']);
        }

        if ($post->deleted_at !== null) {
            abort(404);
        }

        return DB::transaction(function () use ($author, $post): Post {
            $post->forceFill([
                'status' => 'published',
                'published_at' => now(),
                'edited_at' => null,
            ])->save();

            $this->audit->record('post.published', $author, $post);

            return $post;
        });
    }

    /** Author delete: media files + rows removed, post soft-deleted. */
    public function delete(User $author, Post $post): void
    {
        DB::transaction(function () use ($author, $post): void {
            $this->media->deleteForPost($post);

            DB::table('saved_posts')->where('post_id', $post->getKey())->delete();

            $post->delete();

            $this->audit->record('post.deleted', $author, $post, ['visibility' => $post->visibility]);
        });
    }

    /** The author's own posts of any status (drafts included), newest first. */
    public function own(User $user, string $status = 'all', int $perPage = 10): LengthAwarePaginator
    {
        $query = $user->posts()
            ->with(['media', 'hashtags'])
            ->orderByDesc('id');

        if ($status === 'draft') {
            $query->where('status', 'draft');
        }

        return $query->paginate($perPage);
    }

    /** An authored profile's visible posts, newest first (privacy filtered). */
    public function forAuthor(User $viewer, User $author, int $perPage = 10): LengthAwarePaginator
    {
        return Post::query()
            ->with(['media', 'user.profile', 'hashtags'])
            ->where('posts.user_id', $author->getKey())
            ->visibleTo($viewer)
            ->eligibleFor($viewer)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /** Post detail through the access gate; 404 keeps private data silent. */
    public function show(User $viewer, Post $post): Post
    {
        if (! $this->access->canView($viewer, $post)) {
            abort(404);
        }

        return $post->load(['media', 'user.profile', 'hashtags']);
    }

    /** Drafts remain editable; posted images stay under the 10-image cap. */
    private function assertMediaAllowed(array $files, int $existingCount = 0): void
    {
        if (count($files) + $existingCount > PostMedia::MAX_PER_POST) {
            throw ValidationException::withMessages(['media' => 'A post may hold at most 10 images.']);
        }
    }
}
