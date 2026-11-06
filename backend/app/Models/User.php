<?php

namespace App\Models;

use App\Notifications\VerifyEmailNotification;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, MustVerifyEmail;

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'last_login_at' => 'datetime'];
    }

    public function profile(): HasOne { return $this->hasOne(UserProfile::class); }
    public function interests(): BelongsToMany { return $this->belongsToMany(Interest::class, 'user_interests'); }
    public function settings(): HasOne { return $this->hasOne(UserSetting::class); }
    public function privacySettings(): HasOne { return $this->hasOne(UserPrivacySetting::class); }
    public function isSuperAdmin(): bool { return $this->role === 'super_admin'; }
    public function isModerator(): bool { return $this->role === 'moderator'; }
    public function isAdmin(): bool { return $this->isSuperAdmin() || $this->isModerator(); }
    public function sendEmailVerificationNotification(): void { $this->notify(new VerifyEmailNotification()); }
    public function sendPasswordResetNotification($token): void { $this->notify(new ResetPasswordNotification($token)); }
}
