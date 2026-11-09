<?php

namespace App\Http\Resources\Feed;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Minimal public author profile embedded with feed cards. */
class AuthorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var User|null $user */
        $user = $this->resource;

        if ($user === null) {
            return ['id' => null, 'username' => null, 'display_name' => null, 'avatar_url' => null];
        }

        $profile = $user->profile;

        return [
            'id' => (int) $user->getKey(),
            'username' => $profile?->username,
            'display_name' => $profile?->display_name ?? $user->name,
            'avatar_url' => $profile?->avatar_path ? asset('storage/'.$profile->avatar_path) : null,
        ];
    }
}
