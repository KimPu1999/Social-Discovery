<?php

namespace App\Http\Requests\Feed;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Post create/update payload. Authoritative validation for composer input,
 * including inline supplied text-only/image rules.
 */
class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('patch');

        return [
            'body' => [Rule::requiredIf($this->wantsStatusPublished()), 'string', 'max:5000'],
            'visibility' => ['nullable', Rule::in(['public', 'connections', 'private'])],
            'status' => $isUpdate ? ['prohibited'] : ['nullable', Rule::in(['draft', 'published'])],
            'media' => ['nullable', 'array', 'max:10'],
            'media.*' => ['file', 'image', 'max:5120'],
            'removed_media_ids' => ['nullable', 'array'],
            'removed_media_ids.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.max' => 'Posts may contain up to 5000 characters.',
            'body.required' => 'Add some text or photos before posting.',
            'media.max' => 'A post may contain at most 10 images.',
            'media.*.max' => 'Each image may be at most 5 MB.',
            'status.prohibited' => 'Use the publish action to change post status.',
        ];
    }

    private function wantsStatusPublished(): bool
    {
        if ($this->boolean('as_draft')) {
            return false;
        }

        return ($this->input('status') ?? 'published') === 'published';
    }

    /** Normalized creation payload used by PostService. */
    public function createPayload(): array
    {
        $data = $this->safe(['body', 'visibility', 'status']);

        return [
            'body' => $this->filled('body') ? (string) $data['body'] : null,
            'visibility' => $data['visibility'] ?? 'public',
            'status' => $this->boolean('as_draft') ? 'draft' : ($data['status'] ?? 'published'),
            'media' => $this->file('media', []),
        ];
    }
}
