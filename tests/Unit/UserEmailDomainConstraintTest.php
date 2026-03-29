<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UserEmailDomainConstraintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.auth.allowed_email_domains', ['havasmad.com']);
    }

    public function test_user_creation_rejects_non_allowed_domain(): void
    {
        $this->expectException(ValidationException::class);

        User::factory()->create([
            'email' => 'forbidden@example.com',
        ]);
    }

    public function test_user_update_rejects_non_allowed_domain(): void
    {
        $user = User::factory()->create([
            'email' => 'valid@havasmad.com',
        ]);

        $this->expectException(ValidationException::class);

        $user->update([
            'email' => 'forbidden@example.com',
        ]);
    }
}