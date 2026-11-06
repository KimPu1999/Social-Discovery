<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AccountController
{
    public function __construct(private readonly AuditLogger $audit) {}
    public function deactivate(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $user = $request->user(); $user->forceFill(['status' => 'deactivated'])->save();
        $this->audit->record('account_deactivated', $user, $user, [], $request->ip());
        auth()->logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
        return response()->json(['message' => 'Account deactivated.']);
    }
    public function reactivate(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email:rfc'], 'password' => ['required', 'string']]);
        $user = \App\Models\User::withTrashed()->where('email', strtolower($request->string('email')->toString()))->first();
        abort_if(! $user || $user->status !== 'deactivated' || ! Hash::check($request->string('password')->toString(), $user->password), 422, 'The account could not be reactivated.');
        $user->restore(); $user->forceFill(['status' => 'active'])->save();
        $this->audit->record('account_reactivated', $user, $user, [], $request->ip());
        return response()->json(['message' => 'Account reactivated.']);
    }
    public function deleteRequest(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $user = $request->user(); $this->audit->record('account_deletion_requested', $user, $user, [], $request->ip());
        return response()->json(['message' => 'Your account deletion request has been recorded.']);
    }
}
