<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\PasswordUpdateRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $user = User::create(['name' => $request->string('name')->toString(), 'username' => Str::lower($request->string('username')->toString()), 'email' => Str::lower($request->string('email')->toString()), 'password' => $request->string('password')->toString()]);
            $user->forceFill(['role' => 'user', 'status' => 'pending_verification'])->save();
            $user->profile()->create(['username' => $user->username, 'display_name' => $user->name]);
            $user->settings()->create(['language' => 'en', 'theme' => 'system', 'email_notifications' => true, 'push_notifications' => false]);
            $user->privacySettings()->create(['profile_visibility' => 'public', 'discovery_enabled' => false, 'show_online_status' => false, 'allow_message_requests' => false, 'allow_connection_requests' => true, 'show_interests' => true, 'show_location' => false]);
            $user->discoveryPreference()->create(['discovery_enabled' => false, 'show_in_recommendations' => true]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        $user->sendEmailVerificationNotification();
        $this->audit->record('registration', $user, $user, [], $request->ip());

        return response()->json(['user' => new UserResource($user), 'message' => 'Account created. Please verify your email.'], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $login = Str::lower($request->string('email')->toString());
        $user = User::where('email', $login)->orWhere('username', $login)->first();
        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            $this->audit->record('login_failure', $user, $user, [], $request->ip());

            return response()->json(['message' => 'The provided credentials are incorrect.'], 422);
        }
        if ($user->status === 'suspended') {
            return response()->json(['message' => 'This account is suspended.'], 403);
        }
        if ($user->status === 'deactivated') {
            return response()->json(['message' => 'This account is deactivated.'], 403);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();
        $this->audit->record('login_success', $user, $user, [], $request->ip());

        return response()->json(['user' => new UserResource($user), 'email_verified' => $user->hasVerifiedEmail()]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => new UserResource($request->user()), 'email_verified' => $request->user()->hasVerifiedEmail()]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->audit->record('logout', $request->user(), $request->user(), [], $request->ip());
        Auth::guard('web')->logout();
        $request->session()->flush();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Signed out successfully.']);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->tokens()->delete();

        return $this->logout($request);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user->hasVerifiedEmail(), 400, 'Email is already verified.');
        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification email sent.']);
    }

    public function verifyEmail(Request $request, int $id, string $hash): JsonResponse
    {
        abort_unless($request->hasValidSignature(), 403, 'The verification link is invalid or expired.');
        $user = User::findOrFail($id);
        abort_unless(hash_equals($hash, sha1($user->getEmailForVerification())), 403, 'The verification link is invalid.');
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
            $user->forceFill(['status' => 'active'])->save();
            $this->audit->record('email_verified', $user, $user, [], $request->ip());
        }
        if (! $request->expectsJson()) {
            return redirect()->to(rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/').'/verify-email/result?status=success');
        }

        return response()->json(['message' => 'Email verified successfully.']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email:rfc']]);
        Password::sendResetLink(['email' => Str::lower($request->string('email')->toString())]);

        return response()->json(['message' => 'If an account exists, password reset instructions have been sent.']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string'], 'email' => ['required', 'email:rfc'], 'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/[A-Z]/', 'regex:/[a-z]/', 'regex:/[0-9]/']]);
        $status = Password::reset($data, function (User $user, string $password): void {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            $user->tokens()->delete();
        });
        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => 'The reset link is invalid or expired.'], 422);
        }

        return response()->json(['message' => 'Password reset successfully.']);
    }

    public function updatePassword(PasswordUpdateRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update(['password' => $request->string('password')->toString()]);
        $user->tokens()->delete();
        $this->audit->record('password_changed', $user, $user, [], $request->ip());

        return response()->json(['message' => 'Password updated successfully.']);
    }

    public function updateEmail(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'unique:users,email'], 'password' => ['required', 'current_password']]);
        $user = $request->user();
        $user->forceFill(['email' => strtolower($data['email']), 'email_verified_at' => null, 'status' => 'pending_verification'])->save();
        $user->sendEmailVerificationNotification();
        $this->audit->record('email_changed', $user, $user, [], $request->ip());

        return response()->json(['message' => 'Email updated. Please verify the new address.']);
    }
}
