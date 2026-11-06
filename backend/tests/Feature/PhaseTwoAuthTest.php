<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PhaseTwoAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_private_defaults_and_ignores_role_input(): void
    {
        $response = $this->postJson('/api/v1/auth/register', ['name' => 'Ava Stone', 'username' => 'avastone', 'email' => 'ava@example.com', 'password' => 'StrongPass1', 'password_confirmation' => 'StrongPass1', 'terms_accepted' => true, 'privacy_accepted' => true, 'role' => 'super_admin']);
        $response->assertCreated()->assertJsonPath('user.role', 'user');
        $user = User::where('email', 'ava@example.com')->firstOrFail();
        $this->assertDatabaseHas('user_profiles', ['user_id' => $user->id, 'username' => 'avastone']);
        $this->assertDatabaseHas('user_privacy_settings', ['user_id' => $user->id, 'discovery_enabled' => 0]);
    }

    public function test_login_updates_security_metadata_and_logout_invalidates_session(): void
    {
        $user = User::factory()->create(['email' => 'login@example.com', 'password' => 'StrongPass1']);
        $this->postJson('/api/v1/auth/login', ['email' => 'login@example.com', 'password' => 'StrongPass1'])->assertOk()->assertJsonPath('user.id', $user->id);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertGuest('web');
    }

    public function test_suspended_users_cannot_login(): void
    {
        User::factory()->create(['email' => 'suspended@example.com', 'password' => 'StrongPass1', 'status' => 'suspended']);
        $this->postJson('/api/v1/auth/login', ['email' => 'suspended@example.com', 'password' => 'StrongPass1'])->assertForbidden();
    }

    public function test_email_verification_activates_pending_account(): void
    {
        $user = User::factory()->unverified()->create(['status' => 'pending_verification']);
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(10), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->getJson($url)->assertOk();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'active']);
    }

    public function test_regular_users_cannot_access_admin_routes(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/v1/admin/users')->assertForbidden();
    }

    public function test_super_admin_can_change_regular_user_status_but_not_super_admin(): void
    {
        $admin = User::factory()->create(); $admin->forceFill(['role' => 'super_admin'])->save();
        $user = User::factory()->create();
        $this->actingAs($admin)->patchJson('/api/v1/admin/users/'.$user->id.'/status', ['status' => 'suspended'])->assertOk();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'suspended']);
        $this->actingAs($admin)->patchJson('/api/v1/admin/users/'.$admin->id.'/role', ['role' => 'user'])->assertForbidden();
    }
}
