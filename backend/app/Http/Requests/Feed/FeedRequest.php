<?php

namespace App\Http\Requests\Feed;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Feed list parameters: type + cursor + bound limit. */
class FeedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::in(['for_you', 'following', 'latest'])],
            'cursor' => ['nullable', 'string', 'max:300'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:30'],
        ];
    }

    public function feedType(): string
    {
        return $this->validated()['type'] ?? 'for_you';
    }

    public function feedCursor(): ?string
    {
        $cursor = $this->validated()['cursor'] ?? null;

        return filled($cursor) ? (string) $cursor : null;
    }

    public function feedLimit(): ?int
    {
        return isset($this->validated()['limit']) ? (int) $this->validated()['limit'] : null;
    }
}
