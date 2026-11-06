<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->when($request->user()?->is($this->resource), $this->email),
            'status' => $this->status,
            'role' => $this->role,
            'profile' => ProfileResource::make($this->whenLoaded('profile')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
