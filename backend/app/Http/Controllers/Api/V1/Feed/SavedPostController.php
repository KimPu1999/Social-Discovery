<?php

namespace App\Http\Controllers\Api\V1\Feed;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\Feed\SavedPostService;
use Illuminate\Http\JsonResponse;

/** POST/DELETE /posts/{post}/save + GET /me/saved-posts. */
class SavedPostController extends Controller
{
    public function __construct(private readonly SavedPostService $saves) {}

    public function save(Post $post): JsonResponse
    {
        [$saved, $total] = $this->saves->save(request()->user(), $post);

        return response()->json(['message' => 'Saved.', 'saved' => $saved, 'total' => $total]);
    }

    public function unsave(Post $post): JsonResponse
    {
        [$saved, $total] = $this->saves->unsave(request()->user(), $post);

        return response()->json(['message' => 'Removed from saved.', 'saved' => $saved, 'total' => $total]);
    }

    public function index(\Illuminate\Http\Request $request): JsonResponse
    {
        $page = $this->saves->list($request->user(), (int) $request->query('per_page', 10));

        return response()->json([
            'data' => $page->getCollection()->map(static function ($saved): array {
                $post = $saved->post;
                $payload = PostResource::make($post)->toArray($request);

                return ['saved_at' => $saved->created_at, 'post' => $payload];
            })->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }
}
