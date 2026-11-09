<?php

namespace App\Http\Resources\Discovery;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiscoveryInterestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'category' => $this->category,
            // Real, SQL-computed count of eligible discoverable people.
            'people_count' => (int) $this->people_count,
        ];
    }
}
