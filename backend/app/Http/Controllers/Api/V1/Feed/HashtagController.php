<?php

namespace App\Http\Controllers\Api\V1\Feed;

use App\Http\Controllers\Controller;
use App\Http\Resources\Feed\PostResource;
use App\Models\PostHashtag;
use App\Services\Feed\HashtagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** GET /hashtags + GET /hashtags/{slug}/posts */
class HashtagController extends Controller
{
    public function __construct(private readonly HashtagService $hashtags) {}

    /** Trending tag directory with term search. */
    public function index(Request $request): JsonResponse
    {
        $page = $this->hashtags->trending(
            trim((string) $request->query('q', '')),
            (int) $request->query('per_page', 20),
        );

        return response()->json([
            'data' => collect($page->items())->map(static fn ($tag): array => [
                'id' => (int) $tag->id,
                'name' => '#'.$tag->name,
                'slug' => $tag->slug,
                'posts_count' => (int) $tag->posts_count,
            ])->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /** Privacy-aware posts for one hashtag slug. */
    public function posts(Request $request, string $slug): JsonResponse
    {
        $hashtag = PostHashtag::query()->where('slug', $slug)->first();

        if ($hashtag === null) {
            return response()->json(['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 10, 'total' => 0]]);
        }

        $page = $this->hashtags->postsForHashtag(
            $request->user(),
            $hashtag,
            (int) $request->query('per_page', 10),
        );

        return response()->json([
            'hashtag' => ['name' => '#'.$hashtag->name, 'slug' => $hashtag->slug],
            'data' => PostResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }
}
