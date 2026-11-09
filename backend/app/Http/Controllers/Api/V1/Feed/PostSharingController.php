<?php

namespace App\Http\Controllers\Api\V1\Feed;

use App\Http\Controllers\Controller;
use App\Http\Requests\Feed\StorePostRequest;
use App\Http\Resources\Feed\AuthorResource;
use App\Http\Resources\Feed\PostResource;
use App\Models\Post;
use App\Services\Feed\PostAccessService;
use App\Services\Feed\PostSharingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/** POST /posts/{post}/share — share into the viewer's own feed. */
class PostSharingController extends Controller
{
    public function __construct(
        private readonly PostSharingService $shares,
        private readonly PostAccessService $access,
    ) {}

    public function share(StorePostRequest $request, Post $post): JsonResponse
    {
        try {
            Gate::authorize('share', $post);
        } catch (AuthorizationException) {
            abort(404);
        }

        $shared = $this->shares->share($request->user(), $post, Arr::only($request->validated(), ['body', 'visibility']));

        return response()->json([
            'message' => 'Shared to your feed.',
            'post' => PostResource::make($shared->loadMissing(['media', 'hashtags', 'shareOf.originalPost.user.profile', 'shareOf.originalPost.media'])),
        ], 201);
    }

    /**
     * GET /posts/{post}/share-preview — canonical link card data for the
     * share dialog. Authorized view of the source post only.
     */
    public function preview(Post $post): JsonResponse
    {
        try {
            Gate::authorize('view', $post);
        } catch (AuthorizationException) {
            abort(404);
        }

        if ($post->status !== 'published' || ! $this->access->canShare(request()->user(), $post)) {
            return response()->json(['message' => 'This post cannot be shared.'], 403);
        }

        return response()->json([
            'canonical_url' => url('/posts/'.$post->getKey()),
            'post' => ['id' => (int) $post->getKey(), 'excerpt' => mb_substr((string) $post->body, 0, 160), 'author' => AuthorResource::make($post->user), 'media_count' => $post->media()->count()],
        ]);
    }
}
