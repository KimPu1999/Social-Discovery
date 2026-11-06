<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateSuperAdmin extends Command
{
    protected $signature = 'social:create-super-admin';
    protected $description = 'Provision a verified Social Discovery Super Admin account.';

    public function handle(AuditLogger $audit): int
    {
        $email = Str::lower(trim((string) $this->ask('Email address')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) { $this->error('Enter a valid email address.'); return self::FAILURE; }
        if (User::withTrashed()->where('email', $email)->exists()) { $this->error('An account with this email already exists.'); return self::FAILURE; }
        $password = (string) $this->secret('Password (minimum 8 characters, upper/lowercase and number)');
        if (strlen($password) < 8 || ! preg_match('/[A-Z]/', $password) || ! preg_match('/[a-z]/', $password) || ! preg_match('/[0-9]/', $password)) { $this->error('Password does not meet the requirements.'); return self::FAILURE; }
        $username = Str::slug(Str::before($email, '@')).'-admin';
        $user = User::forceCreate(['name' => 'Platform Super Admin', 'username' => $username, 'email' => $email, 'password' => Hash::make($password), 'role' => 'super_admin', 'status' => 'active', 'email_verified_at' => now()]);
        $user->profile()->create(['username' => $username, 'display_name' => $user->name]);
        $user->settings()->create(['language' => 'en', 'theme' => 'system']);
        $user->privacySettings()->create(['profile_visibility' => 'private', 'discovery_enabled' => false]);
        $audit->record('super_admin_created', $user, $user, ['provisioned_by' => 'artisan']);
        $this->info('Super Admin created successfully.');
        return self::SUCCESS;
    }
}
