<?php

namespace App\Http\Requests\Social;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SendConnectionRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $viewerId = (int) $this->user()?->getKey();

        return ['receiver_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at'), static function (string $attribute, mixed $value, callable $fail) use ($viewerId): void {
            if ((int) $value === $viewerId) {
                $fail('You cannot send a connection request to your own account.');
            }

        }, static function (string $attribute, mixed $value, callable $fail): void {
            $receiverStatus = DB::table('users')->where('id', $value)->whereNull('deleted_at')->value('status');
            if ($receiverStatus !== 'active') {
                $fail('This account is not accepting connection requests right now.');
            }
        }]];
    }

    public function messages(): array
    {
        return ['receiver_id.required' => 'Choose someone to connect with.'];
    }
}
