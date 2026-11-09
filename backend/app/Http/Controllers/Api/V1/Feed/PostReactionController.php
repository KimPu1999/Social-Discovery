<?php

namespace App\Http\Controllers\Api\V1\Feed;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\Feed\PostReactionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/** POST/DELETE /posts/{post}/like */
class PostReactionController extends Controller
{
    public function __construct(private readonly PostReactionService $reactions) {}

    public function like(Post $post): JsonResponse
    {
        try {
            Gate::authorize('like', $post);
        } catch (AuthorizationException) {
            abort(404);
        }

        [$liked, $count] = $this->reactions->like(request()->user(), $post);

        return response()->json(['message' => 'Liked.', 'liked' => $liked, 'count' => (int) $count]);
    }

    public function unlike(Post $post): JsonResponse
    {
        try {
            Gate::authorize('like', $post);
        } catch (AuthorizationException) {
            abort(404);
        }

        [$liked, $count] = $this->reactions->unlike(request()->user(), $post);

        return response()->json(['message' => 'Like removed.', 'liked' => $liked, 'count' => (int) $count]);
    }
}
