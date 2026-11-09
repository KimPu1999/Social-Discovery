<?php

namespace App\Http\Requests\Discovery;

use App\Services\Discovery\DiscoveryEligibilityService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DiscoveryPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $categories = Rule::in(app(DiscoveryEligibilityService::class)->validCategories());

        return [
            'discovery_enabled' => ['sometimes', 'boolean'],
            'show_in_recommendations' => ['sometimes', 'boolean'],
            'preferred_interest_categories' => ['sometimes', 'nullable', 'array', 'max:20'],
            'preferred_interest_categories.*' => ['string', $categories],
        ];
    }
}
