<?php

namespace App\Http\Controllers\Api\V1\Social;

use App\Http\Controllers\Controller;
use App\Http\Requests\Social\SocialListRequest;
use App\Models\User;
use App\Services\Social\ConnectionService;
use App\Services\Social\FollowService;
use App\Services\Social\MutualConnectionService;
use App\Services\Social\RelationshipService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Thin HTTP surface for the follow graph: actions, lists, relationship
 * state and counts. All logic lives in the social services.
 */
class FollowController extends Controller
{
    public function __construct(
        private readonly FollowService $follows,
        private readonly RelationshipService $relationships,
        private readonly ConnectionService $connections,
        private readonly MutualConnectionService $mutuals,
    ) {}

    /** POST /users/{user}/follow */
    public function follow(User $user): JsonResponse
    {
        // Authorization lives inside the service (conflict + privacy aware).
        [$follow, $relationship, $created] = $this->follows->follow(request()->user(), $user);

        return response()->json([
            'message' => $created ? 'You are now following this account.' : 'Already following this account.',
            'followed' => true,
            'relationship' => $relationship,
        ]);
    }

    /** DELETE /users/{user}/follow */
    public function unfollow(User $user): JsonResponse
    {
        [$relationship] = $this->follows->unfollow(request()->user(), $user);

        return response()->json(['message' => 'You are no longer following this account.', 'followed' => false, 'relationship' => $relationship]);
    }

    /** GET /users/{user}/followers */
    public function followers(SocialListRequest $request, User $user): JsonResponse
    {
        $this->gate('viewFollowers', $user);

        $result = $this->follows->followers($request->user(), $user, $request->validatedRange());

        return response()->json($result);
    }

    /** GET /users/{user}/following */
    public function following(SocialListRequest $request, User $user): JsonResponse
    {
        $this->gate('viewFollowing', $user);

        $result = $this->follows->following($request->user(), $user, $request->validatedRange());

        return response()->json($result);
    }

    /**
     * GET /users/{user}/relationship — authorization-safe relationship
     * state plus database-backed counts, for profile and card surfaces.
     */
    public function relationship(User $user): JsonResponse
    {
        $viewer = request()->user();
        $relationship = $viewer->is($user) ? null : $this->relationships->forPair($viewer, $user);

        $counts = [
            'followers' => Gate::forUser($viewer)->allows('viewFollowers', $user) ? $this->follows->followerCount($user) : null,
            'following' => Gate::forUser($viewer)->allows('viewFollowing', $user) ? $this->follows->followingCount($user) : null,
            'connections' => Gate::forUser($viewer)->allows('viewConnections', $user) ? $this->connections->countFor($user) : null,
        ];

        $mutual = null;
        if (! $viewer->is($user) && Gate::forUser($viewer)->allows('viewMutualConnections', $user)) {
            $mutual = $this->mutuals->summary($viewer, $viewer, $user);
        }

        return response()->json(['relationship' => $relationship, 'counts' => $counts, 'mutual' => $mutual]);
    }

    private function gate(string $ability, User $target): void
    {
        try {
            Gate::authorize($ability, $target);
        } catch (AuthorizationException $exception) {
            abort(404, $exception->getMessage());
        }
    }
}
