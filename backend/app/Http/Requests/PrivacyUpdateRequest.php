<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrivacyUpdateRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }
    public function rules(): array { return ['profile_visibility' => ['sometimes', Rule::in(['public', 'connections', 'private'])], 'discovery_enabled' => ['sometimes', 'boolean'], 'show_online_status' => ['sometimes', 'boolean'], 'allow_message_requests' => ['sometimes', 'boolean'], 'allow_connection_requests' => ['sometimes', 'boolean'], 'show_interests' => ['sometimes', 'boolean'], 'show_location' => ['sometimes', 'boolean']]; }
}
