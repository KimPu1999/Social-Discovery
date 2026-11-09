<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Discovery\DiscoveryProfileResource;
use App\Http\Resources\Feed\PostResource;
use App\Http\Resources\ProfileResource;
use App\Models\User;
use App\Services\Discovery\RecommendationService;
use App\Services\Feed\FeedService;
use App\Services\Social\SocialNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** One bounded, authorization-scoped read for the member dashboard. */
class DashboardController extends Controller
{
    public function __construct(
        private readonly RecommendationService $recommendations,
        private readonly FeedService $feeds,
        private readonly SocialNotificationService $notifications,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        $viewer = $request->user()->loadMissing(['profile.user.interests', 'privacySettings', 'discoveryPreference']);
        $profile = $viewer->profile;
        $privacy = $viewer->privacySettings;
        $preference = $viewer->discoveryPreference;

        $recommendations = $preference?->discovery_enabled && $preference?->show_in_recommendations
            ? $this->recommendations->recommendedFor($viewer, 6)
            : [];
        $recommendedUsers = collect($recommendations)->pluck('user');
        $relationshipStates = app(\App\Services\Social\RelationshipService::class)->forUsers($viewer, $recommendedUsers);
        $suggestedPeople = $recommendedUsers->map(function (User $user) use ($relationshipStates): array {
            $user->setAttribute('relationship_state', $relationshipStates[$user->getKey()] ?? null);
            return DiscoveryProfileResource::make($user)->resolve();
        })->values()->all();

        $feed = $this->feeds->get($viewer, 'for_you', null, 5);
        $notifications = $this->notifications->feed($viewer, 6, 1);
        $activity = collect($notifications->items())->map(static fn (object $notification): array => [
            'id' => $notification->id,
            'type' => $notification->data['type'] ?? 'social_activity',
            'actor' => $notification->data['actor'] ?? null,
            'url' => $notification->data['url'] ?? '/notifications',
            'read_at' => $notification->read_at,
            'created_at' => $notification->created_at?->toISOString(),
        ])->values()->all();

        $completionFields = [
            'display_name' => filled(trim((string) ($profile?->display_name ?? $viewer->name))) ? 15 : 0,
            'username' => filled(trim((string) ($profile?->username ?? $viewer->username))) ? 15 : 0,
            'avatar' => filled($profile?->avatar_path) ? 15 : 0,
            'bio' => filled(trim((string) ($profile?->bio ?? ''))) ? 15 : 0,
            'interests' => $viewer->interests()->exists() ? 20 : 0,
            'discovery_preference' => $preference !== null ? 10 : 0,
            'email_verified' => $viewer->email_verified_at !== null ? 10 : 0,
        ];

        $viewerId = (int) $viewer->getKey();
        $connectionCount = DB::table('user_connections')->where(static function ($query) use ($viewerId): void {
            $query->where('user_one_id', $viewerId)->orWhere('user_two_id', $viewerId);
        })->count();
        $trending = DB::table('user_interests as ui')
            ->join('interests as i', 'i.id', '=', 'ui.interest_id')
            ->join('users as u', 'u.id', '=', 'ui.user_id')
            ->join('user_profiles as up', 'up.user_id', '=', 'u.id')
            ->leftJoin('discovery_preferences as dp', 'dp.user_id', '=', 'u.id')
            ->where('i.is_active', true)->where('u.status', 'active')->whereNotNull('u.email_verified_at')
            ->where('u.id', '!=', $viewerId)->where('up.visibility', 'public')->where('dp.discovery_enabled', true)->where('dp.show_in_recommendations', true)
            ->whereNotExists(static fn ($query) => $query->select(DB::raw(1))->from('user_blocks as b')->whereColumn('b.blocked_user_id', 'u.id')->where('b.user_id', $viewerId))
            ->whereNotExists(static fn ($query) => $query->select(DB::raw(1))->from('user_blocks as b')->whereColumn('b.user_id', 'u.id')->where('b.blocked_user_id', $viewerId))
            ->groupBy('i.id', 'i.name', 'i.category')->orderByDesc('people_count')->orderBy('i.name')->limit(6)
            ->get(['i.id', 'i.name', 'i.category', DB::raw('count(distinct ui.user_id) as people_count')])
            ->map(static fn (object $interest): array => ['id' => (int) $interest->id, 'name' => $interest->name, 'category' => $interest->category, 'people_count' => (int) $interest->people_count])
            ->values()->all();

        return response()->json(['data' => [
            // Older accounts may not have a profile row yet. Keep the
            // dashboard usable while the profile page repairs that state.
            'profile' => $profile
                ? ProfileResource::make($profile)->resolve($request)
                : [
                    'id' => null,
                    'display_name' => $viewer->name,
                    'username' => $viewer->username,
                    'bio' => null,
                    'location' => null,
                    'website_url' => null,
                    'avatar_url' => null,
                    'cover_image_url' => null,
                    'onboarding_completed_at' => null,
                    'interests' => [],
                ],
            'profile_completion' => ['percentage' => array_sum($completionFields), 'missing' => array_keys(array_filter($completionFields, static fn (int $value): bool => $value === 0))],
            'connection_summary' => [
                'connections' => $connectionCount,
                'followers' => DB::table('user_follows')->where('following_id', $viewerId)->count(),
                'following' => DB::table('user_follows')->where('follower_id', $viewerId)->count(),
                'pending_requests' => DB::table('connection_requests')->where('receiver_id', $viewerId)->where('status', 'pending')->count(),
            ],
            'suggested_people' => $suggestedPeople,
            'recent_activity' => $activity,
            'feed_preview' => PostResource::collection($feed['data'])->resolve($request),
            'interests' => $trending,
            'discovery_status' => [
                'enabled' => (bool) ($preference?->discovery_enabled ?? false),
                'visibility' => $privacy?->profile_visibility ?? 'public',
                'allow_connection_requests' => (bool) ($privacy?->allow_connection_requests ?? false),
            ],
            'community_preview' => ['available' => false, 'data' => []],
            'event_preview' => ['available' => false, 'data' => []],
        ]]);
    }
}
