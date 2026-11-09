<?php

namespace App\Models;

use App\Enums\ConnectionRequestStatus;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'username', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'last_login_ip'])]
class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, MustVerifyEmail, Notifiable, SoftDeletes;

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'last_login_at' => 'datetime'];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function interests(): BelongsToMany
    {
        return $this->belongsToMany(Interest::class, 'user_interests');
    }

    public function settings(): HasOne
    {
        return $this->hasOne(UserSetting::class);
    }

    public function privacySettings(): HasOne
    {
        return $this->hasOne(UserPrivacySetting::class);
    }

    public function discoveryPreference(): HasOne
    {
        return $this->hasOne(DiscoveryPreference::class);
    }

    public function discoveryFeedback(): HasMany
    {
        return $this->hasMany(DiscoveryFeedback::class, 'user_id');
    }

    public function blockedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_blocks', 'user_id', 'blocked_user_id');
    }

    public function blockedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_blocks', 'blocked_user_id', 'user_id');
    }

    /** Follow edges where this user is the follower. */
    public function followings(): HasMany
    {
        return $this->hasMany(UserFollow::class, 'follower_id');
    }

    /** Follow edges pointing at this user. */
    public function followers(): HasMany
    {
        return $this->hasMany(UserFollow::class, 'following_id');
    }

    /** Outgoing connection requests sent by this user. */
    public function sentConnectionRequests(): HasMany
    {
        return $this->hasMany(ConnectionRequest::class, 'sender_id');
    }

    /** Incoming connection requests addressed to this user. */
    public function receivedConnectionRequests(): HasMany
    {
        return $this->hasMany(ConnectionRequest::class, 'receiver_id');
    }

    /** Accepted connection row between this user and the given user, if any. */
    public static function connectionWith(int $userOne, int $userTwo): ?UserConnection
    {
        [$smaller, $larger] = [min($userOne, $userTwo), max($userOne, $userTwo)];

        return UserConnection::query()
            ->where('user_one_id', $smaller)
            ->where('user_two_id', $larger)
            ->first();
    }

    /** The pending request between two users in either direction. */
    public static function pendingRequestBetween(int $userOne, int $userTwo): ?ConnectionRequest
    {
        return ConnectionRequest::query()
            ->whereIn('sender_id', [$userOne, $userTwo])
            ->whereIn('receiver_id', [$userOne, $userTwo])
            ->whereColumn('sender_id', '!=', 'receiver_id')
            ->where('status', ConnectionRequestStatus::Pending->value)
            ->first();
    }

    /**
     * Account eligibility for social interactions: must be active,
     * verified and not deleted. Centralizes the suspended/deleted checks
     * used by every Phase 05 service.
     */
    public function isSociallyActive(): bool
    {
        return $this->status === 'active' && $this->email_verified_at !== null;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isModerator(): bool
    {
        return $this->role === 'moderator';
    }

    public function isAdmin(): bool
    {
        return $this->isSuperAdmin() || $this->isModerator();
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
