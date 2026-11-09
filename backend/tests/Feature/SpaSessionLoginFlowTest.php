<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpaSessionLoginFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_then_repeated_me_requests_stay_authenticated(): void
    {
        $user = User::factory()->create([
            'email' => 'spa@example.com',
            'username' => 'spa_user',
            'password' => 'StrongPass1',
            'email_verified_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'spa_user',
            'password' => 'StrongPass1',
        ])->assertOk();

        // Simulate repeated SPA boot requests on every navigation.
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('user.id', $user->id);
        $this->getJson('/api/v1/auth/me')->assertOk();
        // The profile/discovery endpoints are the next phase's surface; the key
        // regression here is session persistence across repeated boots.
        $this->getJson('/api/v1/auth/me')->assertOk();
    }
}
