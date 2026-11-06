<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->resource;
        return ['id' => $profile->id, 'display_name' => $profile->display_name, 'username' => $profile->username, 'bio' => $profile->bio, 'location' => $request->user()?->is($profile->user) || $profile->visibility === 'public' ? $profile->location : null, 'website_url' => $profile->website_url, 'avatar_url' => $profile->avatar_path ? asset('storage/'.$profile->avatar_path) : null, 'cover_image_url' => $profile->cover_image_path ? asset('storage/'.$profile->cover_image_path) : null, 'onboarding_completed_at' => $profile->onboarding_completed_at?->toISOString(), 'interests' => $profile->relationLoaded('user') && $profile->user->relationLoaded('interests') ? InterestResource::collection($profile->user->interests) : []];
    }
}
