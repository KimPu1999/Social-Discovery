<?php

namespace App\Http\Controllers\Api\V1\Feed;

use App\Http\Controllers\Controller;
use App\Http\Requests\Feed\FeedRequest;
use App\Http\Resources\Feed\PostResource;
use App\Services\Feed\FeedService;
use Illuminate\Http\JsonResponse;

/** GET /feed — the ranked, cursor-paginated viewer feed. */
class FeedController extends Controller
{
    public function __construct(private readonly FeedService $feeds) {}

    public function index(FeedRequest $request): JsonResponse
    {
        $result = $this->feeds->get(
            $request->user(),
            $request->feedType(),
            $request->feedCursor(),
            $request->feedLimit(),
        );

        return response()->json([
            'type' => $result['meta']['type'],
            'data' => PostResource::collection($result['data']),
            'meta' => $result['meta'],
        ]);
    }
}
