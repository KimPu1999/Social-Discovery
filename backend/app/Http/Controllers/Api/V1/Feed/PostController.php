<?php

namespace App\Http\Controllers\Api\V1\Feed;

use App\Http\Controllers\Controller;
use App\Http\Requests\Feed\StorePostRequest;
use App\Http\Resources\Feed\PostResource;
use App\Models\Post;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Feed\HashtagService;
use App\Services\Feed\PostAccessService;
use App\Services\Feed\PostSearchService;
use App\Services\Feed\PostService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Thin HTTP surface for post lifecycle + listing + search.
 * All domain rules live in the Feed services and policies.
 */
class PostController extends Controller
{
    public function __construct(
        private readonly PostService $posts,
        private readonly PostAccessService $access,
        private readonly PostSearchService $search,
        private readonly HashtagService $hashtags,
        private readonly AuditLogger $audit,
    ) {}

    /** GET /posts/{post} */
    public function show(Post $post): JsonResponse
    {
        $visible = $this->posts->show(request()->user(), $post);

        return response()->json(['post' => PostResource::make($visible)]);
    }

    /** POST /posts */
    public function store(StorePostRequest $request): JsonResponse
    {
        $post = $this->posts->create($request->user(), $request->createPayload(), $request->file('media', []));

        return response()->json([
            'message' => $post->status === 'draft' ? 'Draft saved.' : 'Post published.',
            'post' => PostResource::make($post->loadMissing(['media', 'hashtags'])),
        ], 201);
    }

    /** PATCH /posts/{post} */
    public function update(StorePostRequest $request, Post $post): JsonResponse
    {
        $this->authorizeOrAbort('update', $post);

        $updated = $this->posts->update(
            $request->user(),
            $post,
            $request->safe(['body', 'visibility'])->toArray(),
            $request->file('media', []),
            $request->input('removed_media_ids', []),
        );

        return response()->json([
            'message' => 'Post updated.',
            'post' => PostResource::make($updated->loadMissing(['media', 'hashtags'])),
        ]);
    }

    /** DELETE /posts/{post} */
    public function destroy(Post $post): JsonResponse
    {
        $this->authorizeOrAbort('delete', $post);

        $this->posts->delete(request()->user(), $post);

        return response()->json(['message' => 'Post deleted.', 'deleted' => true]);
    }

    /** POST /posts/{post}/publish — draft → published. */
    public function publish(Post $post): JsonResponse
    {
        $this->authorizeOrAbort('publish', $post);

        $published = $this->posts->publish(request()->user(), $post);

        return response()->json([
            'message' => 'Post published.',
            'post' => PostResource::make($published->loadMissing(['media', 'hashtags'])),
        ]);
    }

    /** GET /me/posts — all own posts, optional ?status=draft|published. */
    public function mine(Request $request): JsonResponse
    {
        $status = $request->query('status', 'all');
        $page = $this->posts->own($request->user(), $status, (int) $request->query('per_page', 10));

        return response()->json(['data' => PostResource::collection($page->items()), 'meta' => $this->meta($page)]);
    }

    /** GET /me/posts/drafts */
    public function drafts(Request $request): JsonResponse
    {
        $page = $this->posts->own($request->user(), 'draft', (int) $request->query('per_page', 10));

        return response()->json(['data' => PostResource::collection($page->items()), 'meta' => $this->meta($page)]);
    }

    /** GET /users/{user}/posts — privacy-filtered archive of authored posts. */
    public function forUser(Request $request, User $user): JsonResponse
    {
        try {
            Gate::authorize('view', $user);
        } catch (AuthorizationException) {
            return response()->json(['message' => 'Profile unavailable.'], 404);
        }

        $page = $this->posts->forAuthor(request()->user(), $user, (int) $request->query('per_page', 10));

        return response()->json(['data' => PostResource::collection($page->items()), 'meta' => $this->meta($page)]);
    }

    /** GET /posts/search?q= */
    public function search(Request $request): JsonResponse
    {
        $page = $this->search->search($request->user(), (string) $request->query('q', ''), (int) $request->query('per_page', 10));

        return response()->json(['data' => PostResource::collection($page->items()), 'meta' => $this->meta($page)]);
    }

    /** POST /posts/{post}/hide — stop seeing this post in own feeds. */
    public function hide(Post $post): JsonResponse
    {
        $this->authorizeOrAbort('hide', $post);

        DB::table('post_hides')->updateOrInsert(
            ['user_id' => (int) request()->user()?->getKey(), 'post_id' => $post->getKey()],
            ['created_at' => now()],
        );

        return response()->json(['message' => 'Post hidden from your feed.', 'hidden' => true]);
    }

    /** DELETE /posts/{post}/hide */
    public function unhide(Post $post): JsonResponse
    {
        DB::table('post_hides')
            ->where('user_id', (int) request()->user()?->getKey())
            ->where('post_id', $post->getKey())
            ->delete();

        return response()->json(['message' => 'Post unhidden.', 'hidden' => false]);
    }

    private function meta($page): array
    {
        return [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
        ];
    }

    private function authorizeOrAbort(string $ability, Post $post): void
    {
        try {
            Gate::authorize($ability, $post);
        } catch (AuthorizationException) {
            // Never reveal that another user's protected post exists.
            abort(404);
        }
    }
}
