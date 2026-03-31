<?php

namespace App\Services\Api;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class UserApiService
{
    public function index(int $perPage = 15): LengthAwarePaginator
    {
        return User::query()->orderBy('name')->paginate($perPage);
    }

    public function store(array $data): User
    {
        $data['password'] = Hash::make($data['password']);

        return User::create($data);
    }

    public function update(User $user, array $data): User
    {
        if (isset($data['password']) && $data['password'] !== '') {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return $user->refresh();
    }

    public function delete(User $user): void
    {
        $user->delete();
    }
}
