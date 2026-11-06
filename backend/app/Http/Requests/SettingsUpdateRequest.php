<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettingsUpdateRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }
    public function rules(): array { return ['language' => ['sometimes', Rule::in(['en', 'my'])], 'theme' => ['sometimes', Rule::in(['light', 'dark', 'system'])], 'email_notifications' => ['sometimes', 'boolean'], 'push_notifications' => ['sometimes', 'boolean']]; }
}
