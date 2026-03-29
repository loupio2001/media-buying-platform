<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.auth.allowed_email_domains', ['havasmad.com']);
    }

    public function test_user_can_login_and_receive_a_token(): void
    {
        User::factory()->create([
            'email' => 'test@havasmad.com',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@havasmad.com',
            'password' => 'password',
            'device_name' => 'phpunit',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['token', 'user'],
                'meta' => ['status'],
            ]);
    }

    public function test_login_is_rejected_for_non_allowed_email_domain(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password',
            'device_name' => 'phpunit',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_authenticated_user_can_fetch_profile(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/me');

        $response
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_authenticated_user_can_logout_and_current_token_is_revoked(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('phpunit');

        $response = $this->withToken($token->plainTextToken)->postJson('/api/auth/logout');

        $response
            ->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonPath('meta.status', 'logged_out');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}