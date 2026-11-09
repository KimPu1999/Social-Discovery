<?php

namespace App\Http\Resources\Discovery;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Privacy-safe profile card for discovery listings and recommendations.
 * Never exposes email, status, role, precise location, birth date or
 * other internal account flags.
 */
class DiscoveryProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $profile = $user->relationLoaded('profile') ? $user->profile : null;
        $showLocation = (bool) $user->relationLoaded('privacySettings') && $user->privacySettings?->show_location;

        return [
            'id' => $user->getKey(),
            'username' => $user->profile?->username ?? $user->username,
            'display_name' => $profile?->display_name ?? $user->name,
            'bio' => $profile?->bio,
            'location' => $showLocation ? $profile?->location : null,
            'avatar_url' => $profile?->avatar_path ? asset('storage/'.$profile->avatar_path) : null,
            'joined_at' => $user->created_at?->toISOString(),
            'interests' => $user->discovery_interests->map(static fn (object $interest): array => [
                'id' => $interest->id,
                'name' => $interest->name,
                'slug' => $interest->slug,
                'category' => $interest->category,
                'shared_with_viewer' => $interest->viewer_selected,
            ]),
            'match' => $user->match_summary['shared'] === null ? null : [
                'score' => $user->match_summary['shared']['score'],
                'shared_count' => $user->match_summary['shared']['shared_count'],
                'shared_interests' => collect($user->match_summary['shared']['shared_interests'])->map(static fn (object $interest): array => [
                    'id' => $interest->id,
                    'name' => $interest->name,
                    'slug' => $interest->slug,
                    'category' => $interest->category,
                ]),
            ],
            // Batched, authorization-safe relationship state (null when uncomputed).
            'relationship' => $user->getAttribute('relationship_state'),
        ];
    }
}
