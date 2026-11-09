<?php

namespace App\Http\Resources\Discovery;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiscoveryPreferenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'discovery_enabled' => (bool) $this->discovery_enabled,
            'show_in_recommendations' => (bool) $this->show_in_recommendations,
            'preferred_interest_categories' => $this->preferred_interest_categories ?? [],
        ];
    }
}
