<?php

namespace App\Http\Controllers\Api\V1\Social;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Social\MutualConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Mutual (shared) connections between the viewer and another user.
 * Visibility is governed by SocialPolicy@viewMutualConnections; hidden
 * lists answer with an empty, harmless payload.
 */
class MutualConnectionController extends Controller
{
    public function __construct(private readonly MutualConnectionService $mutuals) {}

    /** GET /users/{user}/mutual-connections */
    public function show(User $user): JsonResponse
    {
        $viewer = request()->user();

        if (! Gate::forUser($viewer)->allows('viewMutualConnections', $user)) {
            return response()->json(['count' => 0, 'data' => []]);
        }

        $mutual = $this->mutuals->summary($viewer, $viewer, $user);

        return response()->json(['count' => $mutual['count'], 'data' => $mutual['preview']]);
    }
}
