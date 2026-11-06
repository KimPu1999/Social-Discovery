<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StatusUpdateRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isSuperAdmin() === true; }
    public function rules(): array { return ['status' => ['required', Rule::in(['active', 'suspended', 'deactivated'])]]; }
}
