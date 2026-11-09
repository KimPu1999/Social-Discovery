<?php

namespace App\Http\Requests\Social;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared pagination/search parameters for every Phase 05 list endpoint.
 */
class SocialListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([5, 10, 20, 50])],
            'search' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', Rule::in(['newest', 'name'])],
        ];
    }

    public function validatedRange(): array
    {
        $validated = parent::validated();

        return [
            'page' => max((int) ($validated['page'] ?? 1), 1),
            'per_page' => (int) ($validated['per_page'] ?? 10),
            'search' => isset($validated['search']) ? (string) $validated['search'] : null,
            'sort' => (string) ($validated['sort'] ?? 'newest'),
        ];
    }
}
