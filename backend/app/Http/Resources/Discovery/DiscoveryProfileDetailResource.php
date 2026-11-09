<?php

namespace App\Http\Resources\Discovery;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public profile preview under discovery. Only fields the target chose
 * to show are present; unavailable profiles never reach this resource.
 */
class DiscoveryProfileDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $profile = $user->profile;
        $privacy = $user->privacySettings;
        $showLocation = (bool) $privacy?->show_location;

        return [
            // The public profile route needs the stable user id for
            // authorized relationship mutations; no private account data is
            // exposed by including this identifier.
            'id' => $user->getKey(),
            'username' => $profile->username,
            'display_name' => $profile->display_name ?? $user->name,
            'bio' => $profile->bio,
            'location' => $showLocation ? $profile->location : null,
            'location_visible' => $showLocation,
            'website_url' => $profile->website_url,
            'avatar_url' => $profile->avatar_path ? asset('storage/'.$profile->avatar_path) : null,
            'cover_image_url' => $profile->cover_image_path ? asset('storage/'.$profile->cover_image_path) : null,
            'interests_visible' => (bool) $privacy?->show_interests,
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
            'visibility' => $profile->visibility,
            'joined_at' => $user->created_at?->toISOString(),
            // Batched, authorization-safe relationship state (null when uncomputed).
            'relationship' => $user->getAttribute('relationship_state'),
            'actions' => [
                'can_report' => true,
                'can_block' => true,
            ],
        ];
    }
}
