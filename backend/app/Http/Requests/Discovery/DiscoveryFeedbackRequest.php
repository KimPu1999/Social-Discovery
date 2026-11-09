<?php

namespace App\Http\Requests\Discovery;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DiscoveryFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            // No self-targeting; targets must be real, active, non-deleted users.
            'target_user_id' => [
                'required',
                'integer',
                Rule::notIn([$this->user()?->getKey() ?? -1]),
                Rule::exists('users', 'id')->where(static function ($query): void {
                    $query->whereNull('deleted_at')->whereIn('status', ['active']);
                }),
            ],
            'feedback_type' => ['required', Rule::in(['not_interested', 'hide_profile'])],
        ];
    }
}
