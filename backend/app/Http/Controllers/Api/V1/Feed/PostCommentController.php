<?php

namespace App\Http\Controllers\Api\V1\Feed;

use App\Http\Controllers\Controller;
use App\Http\Requests\Feed\CommentStoreRequest;
use App\Http\Resources\Feed\CommentResource;
use App\Models\Post;
use App\Models\PostComment;
use App\Services\Feed\CommentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Comment lifecycle: list (paginated), create, reply, edit, delete.
 * Authorization lives in PostCommentPolicy + CommentService.
 */
class PostCommentController extends Controller
{
    public function __construct(private readonly CommentService $comments) {}

    /** GET /posts/{post}/comments */
    public function index(Request $request, Post $post): JsonResponse
    {
        try {
            Gate::authorize('view', $post);
        } catch (AuthorizationException) {
            abort(404);
        }

        if ($post->status !== 'published') {
            abort(404);
        }

        $page = $this->comments->index(
            $request->user(),
            $post,
            (int) $request->query('per_page', 10),
        );

        return response()->json([
            'data' => CommentResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /** POST /posts/{post}/comments */
    public function store(CommentStoreRequest $request, Post $post): JsonResponse
    {
        $comment = $this->comments->create($request->user(), $post, $request->input('body'));

        return response()->json([
            'message' => 'Comment added.',
            'comment' => CommentResource::make($comment->loadMissing('user.profile')),
        ], 201);
    }

    /** POST /comments/{comment}/replies */
    public function reply(CommentStoreRequest $request, PostComment $comment): JsonResponse
    {
        $reply = $this->comments->create($request->user(), $comment->post, $request->input('body'), $comment);

        return response()->json([
            'message' => 'Reply added.',
            'comment' => CommentResource::make($reply->loadMissing('user.profile')),
        ], 201);
    }

    /** PATCH /comments/{comment} */
    public function update(CommentStoreRequest $request, PostComment $comment): JsonResponse
    {
        try {
            Gate::authorize('update', $comment);
        } catch (AuthorizationException) {
            return response()->json(['message' => 'You can only edit your own comments.'], 403);
        }

        if ($comment->deleted_at !== null) {
            abort(404);
        }

        $updated = $this->comments->update($request->user(), $comment, $request->input('body'));

        return response()->json([
            'message' => 'Comment updated.',
            'comment' => CommentResource::make($updated->loadMissing('user.profile')),
        ]);
    }

    /** DELETE /comments/{comment} */
    public function destroy(PostComment $comment): JsonResponse
    {
        try {
            Gate::authorize('delete', $comment);
        } catch (AuthorizationException) {
            return response()->json(['message' => 'You can only delete your own comments.'], 403);
        }

        if ($comment->deleted_at !== null) {
            abort(404);
        }

        $this->comments->delete(request()->user(), $comment);

        return response()->json(['message' => 'Comment deleted.', 'deleted' => true]);
    }
}
