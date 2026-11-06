<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }
    public function rules(): array
    {
        return ['display_name' => ['required', 'string', 'max:120'], 'username' => ['required', 'alpha_dash', 'min:3', 'max:40', Rule::unique('users', 'username')->ignore($this->user()->id)], 'bio' => ['nullable', 'string', 'max:500'], 'location' => ['nullable', 'string', 'max:120'], 'website_url' => ['nullable', 'url', 'max:255']];
    }
}
