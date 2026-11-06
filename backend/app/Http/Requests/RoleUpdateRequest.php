<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleUpdateRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isSuperAdmin() === true; }
    public function rules(): array { return ['role' => ['required', Rule::in(['user', 'moderator'])]]; }
}
