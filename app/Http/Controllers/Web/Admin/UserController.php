<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Api\UserApiService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(private UserApiService $userApiService) {}

    public function index(): View
    {
        $users = $this->userApiService->index(20);

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        $roles = UserRole::cases();

        return view('admin.users.create', compact('roles'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::enum(UserRole::class)],
        ]);

        $this->userApiService->store($data);

        return redirect()
            ->route('web.admin.users.index')
            ->with('status', 'User created successfully.');
    }

    public function edit(int $user): View
    {
        $user = User::findOrFail($user);
        $roles = UserRole::cases();

        return view('admin.users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, int $user): RedirectResponse
    {
        $userModel = User::findOrFail($user);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', Rule::enum(UserRole::class)],
        ]);

        $this->userApiService->update($userModel, $data);

        return redirect()
            ->route('web.admin.users.index')
            ->with('status', 'User updated successfully.');
    }
}
