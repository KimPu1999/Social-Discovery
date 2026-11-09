<?php

namespace App\Http\Requests\Feed;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Content report payload for posts and comments. */
class ReportContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::in(['spam', 'harassment', 'hate_content', 'inappropriate_content', 'privacy_violation', 'other'])],
            'details' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
