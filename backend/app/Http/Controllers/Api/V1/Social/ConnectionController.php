<?php

namespace App\Http\Controllers\Api\V1\Social;

use App\Http\Controllers\Controller;
use App\Http\Requests\Social\SocialListRequest;
use App\Models\User;
use App\Services\Social\ConnectionService;
use App\Services\Social\RelationshipService;
use Illuminate\Http\JsonResponse;

/**
 * Accepted-connection HTTP surface: my list, another user's authorized
 * list, removal and mutual summaries. Privacy gates live in the policy
 * and service; the controller never decides visibility itself.
 */
class ConnectionController extends Controller
{
    public function __construct(private readonly ConnectionService $connections) {}

    /** GET /connections — the authenticated user's own connections. */
    public function index(SocialListRequest $request): JsonResponse
    {
        return response()->json($this->connections->listFor($request->user(), $request->validatedRange()));
    }

    /** GET /users/{user}/connections — only when authorized by privacy. */
    public function forUser(SocialListRequest $request, User $user): JsonResponse
    {
        return response()->json($this->connections->listVisibleTo($request->user(), $user, $request->validatedRange()));
    }

    /** DELETE /connections/{user} — remove the connection with the user. */
    public function destroy(User $user): JsonResponse
    {
        $this->connections->remove(request()->user(), $user);

        return response()->json(['message' => 'Connection removed.', 'relationship' => app(RelationshipService::class)->forPair(request()->user(), $user)]);
    }
}
