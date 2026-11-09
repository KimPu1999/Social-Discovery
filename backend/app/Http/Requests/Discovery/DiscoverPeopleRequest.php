<?php

namespace App\Http\Requests\Discovery;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DiscoverPeopleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:60'],
            'interest_ids' => ['sometimes', 'array', 'max:10'],
            'interest_ids.*' => ['integer', Rule::exists('interests', 'id')->where('is_active', true)],
            'sort' => ['sometimes', Rule::in(['relevance', 'recently_joined', 'username'])],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'per_page' => ['sometimes', Rule::in([10, 20, 50])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $tags = [];
        foreach (['per_page', 'page'] as $key) {
            if (! $this->filled($key)) {
                continue;
            }
            $tags[$key] = ctype_digit((string) $this->input($key)) ? (int) $this->input($key) : $this->input($key);
        }
        $this->merge($tags);
    }
}
