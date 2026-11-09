<?php

namespace App\Http\Controllers\Api\V1\Discovery;

use App\Http\Controllers\Controller;
use App\Http\Requests\Discovery\DiscoverPeopleRequest;
use App\Http\Requests\Social\SocialListRequest;
use App\Http\Resources\Discovery\DiscoveryInterestResource;
use App\Http\Resources\Discovery\DiscoveryProfileDetailResource;
use App\Http\Resources\Discovery\DiscoveryProfileResource;
use App\Models\User;
use App\Services\Discovery\DiscoveryService;
use App\Services\Discovery\RecommendationService;
use App\Services\Social\RelationshipService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Thin HTTP surface for discovery reads. All business logic delegates to
 * the Discovery services; controllers only shape responses.
 */
class DiscoveryController extends Controller
{
    public function __construct(
        private readonly DiscoveryService $discovery,
    ) {}

    /** GET /discovery/overview — consent state, categories, real counts. */
    public function overview(Request $request): JsonResponse
    {
        return response()->json(['overview' => $this->discovery->overview($request->user())]);
    }

    /** GET /discovery/people — filterable, sortable, paginated explorer. */
    public function people(DiscoverPeopleRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $page = $this->discovery->people($request->user(), array_filter($validated, static fn ($value) => $value !== null));

        $this->attachRelationships($request->user(), collect($page->items()));

        return response()->json([
            'data' => DiscoveryProfileResource::collection($page->items())->resolve(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /** GET /discovery/recommended — explainable shared-interest matches. */
    public function recommended(Request $request): JsonResponse
    {
        $limit = in_array((int) $request->query('limit', 8), [4, 8, 12], true) ? (int) $request->query('limit', 8) : 8;
        $recommendations = app(RecommendationService::class)->recommendedFor($request->user(), $limit);

        $this->attachRelationships($request->user(), collect($recommendations)->pluck('user'));

        return response()->json(['data' => array_map(
            static fn (array $entry): array => DiscoveryProfileResource::make($entry['user'])->resolve(),
            $recommendations,
        )]);
    }

    /** GET /connections/suggestions — actionable, relationship-safe suggestions. */
    public function suggestions(SocialListRequest $request): JsonResponse
    {
        $page = $this->discovery->suggestions($request->user(), $request->validatedRange());
        $this->attachRelationships($request->user(), collect($page->items()));

        return response()->json([
            'data' => DiscoveryProfileResource::collection($page->items())->resolve(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /** GET /discovery/interests — active interests grouped by category with real counts. */
    public function interests(Request $request): JsonResponse
    {
        $grouped = $this->discovery->interestsWithCounts($request->user());

        return response()->json([
            'data' => collect($grouped)->map(static function ($interests, string $category): array {
                return [
                    'category' => $category,
                    'interests' => DiscoveryInterestResource::collection($interests)->resolve(),
                ];
            })->values()->all(),
        ]);
    }

    /** GET /discovery/people/{username} — privacy-safe public profile preview. */
    public function show(Request $request, string $username): JsonResponse
    {
        $profile = $this->discovery->viewableProfile($request->user(), $username);

        if ($profile === null) {
            // Uniform, privacy-safe response: never reveals why.
            return response()->json(['message' => __('Profile unavailable.')], 404);
        }

        // Declarative policy check; a denial is also answered as the same
        // privacy-safe 404 so eligibility rules can never leak detail.
        try {
            Gate::authorize('view', $profile->user);
        } catch (AuthorizationException) {
            return response()->json(['message' => __('Profile unavailable.')], 404);
        }

        $this->attachRelationships($request->user(), collect([$profile->user]));

        return response()->json(['profile' => new DiscoveryProfileDetailResource($profile->user)]);
    }

    /**
     * Batch one relationship-state lookup for the whole page (no N+1)
     * and stamp it on each user for the resources to embed.
     */
    private function attachRelationships(User $viewer, Collection $users): void
    {
        $states = app(RelationshipService::class)->forUsers($viewer, $users);

        foreach ($users as $user) {
            $user->setAttribute('relationship_state', $states[$user->getKey()] ?? null);
        }
    }
}
