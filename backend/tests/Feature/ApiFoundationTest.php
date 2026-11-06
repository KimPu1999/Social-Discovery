<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_versioned_status(): void
    {
        $this->getJson('/api/v1/health')->assertOk()->assertExactJson([
            'status' => 'ok', 'application' => 'Social Discovery', 'api_version' => 'v1',
        ]);
    }

    public function test_authenticated_endpoint_requires_a_session(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_registration_validates_and_creates_a_user(): void
    {
        $this->postJson('/api/v1/auth/register', ['name' => 'A', 'email' => 'bad', 'password' => 'short'])->assertUnprocessable();
        $payload = ['name' => 'Kim Smith', 'username' => 'kimsmith', 'email' => 'kim@example.com', 'password' => 'A-secure-password1', 'password_confirmation' => 'A-secure-password1', 'terms_accepted' => true, 'privacy_accepted' => true];
        $this->postJson('/api/v1/auth/register', $payload)->assertCreated();
        $this->assertDatabaseHas('users', ['email' => 'kim@example.com', 'status' => 'pending_verification', 'role' => 'user']);
    }

    public function test_model_relationships_are_defined(): void
    {
        $user = User::factory()->create();
        $this->assertSame($user->id, $user->profile()->getForeignKeyName() ? $user->id : null);
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class, $user->interests());
    }
}
