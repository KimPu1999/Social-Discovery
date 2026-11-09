<?php

namespace App\Http\Controllers\Api\V1\Social;

use App\Http\Controllers\Controller;
use App\Http\Requests\Social\SocialListRequest;
use App\Models\User;
use App\Services\Social\BlockService;
use Illuminate\Http\JsonResponse;

/**
 * Blocking HTTP surface. Blocks are never announced to the blocked
 * account and never exposed through public endpoints.
 */
class BlockingController extends Controller
{
    public function __construct(private readonly BlockService $blocks) {}

    /** POST /users/{user}/block */
    public function block(User $user): JsonResponse
    {
        $result = $this->blocks->block(request()->user(), $user);

        return response()->json(['message' => 'Account blocked.', 'blocked' => $result['blocked']]);
    }

    /** DELETE /users/{user}/block */
    public function unblock(User $user): JsonResponse
    {
        $result = $this->blocks->unblock(request()->user(), $user);

        return response()->json(['message' => 'Account unblocked.', 'blocked' => $result['blocked']]);
    }

    /** GET /settings/blocked-users */
    public function index(SocialListRequest $request): JsonResponse
    {
        return response()->json($this->blocks->blockedUsers($request->user(), $request->validatedRange()));
    }
}
