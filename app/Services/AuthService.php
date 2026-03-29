<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    public function login(array $credentials, string $ipAddress = 'unknown'): array
    {
        if (!User::isEmailDomainAllowed($credentials['email'])) {
            throw ValidationException::withMessages([
                'email' => ['The provided email domain is not allowed.'],
            ]);
        }

        $user = User::query()->where('email', $credentials['email'])->first();

        if (!$user || !$user->is_active || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        $tokenName = $credentials['device_name'] ?? sprintf('api-%s', $ipAddress);
        $plainTextToken = $user->createToken($tokenName)->plainTextToken;

        return [
            'token' => $plainTextToken,
            'user' => $user->fresh(),
        ];
    }

    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }
}