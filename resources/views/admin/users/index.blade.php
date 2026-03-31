@extends('layouts.app')

@section('title', 'Users | Admin | Havas Media Buying Platform')

@section('content')
    <section class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-orange-300">Admin</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">Users</h1>
                <p class="mt-2 text-slate-300">Manage platform users and roles.</p>
            </div>
            <a href="{{ route('web.admin.users.create') }}" class="rounded-md bg-orange-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-orange-400">
                + New User
            </a>
        </div>

        <section class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900/80">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-950/40 text-slate-400">
                        <tr>
                            <th class="px-5 py-3 font-medium">Name</th>
                            <th class="px-5 py-3 font-medium">Email</th>
                            <th class="px-5 py-3 font-medium">Role</th>
                            <th class="px-5 py-3 font-medium">Active</th>
                            <th class="px-5 py-3 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr class="border-t border-slate-800/80 text-slate-200">
                                <td class="px-5 py-3">{{ $user->name }}</td>
                                <td class="px-5 py-3 text-slate-300">{{ $user->email }}</td>
                                <td class="px-5 py-3">
                                    @php
                                        $role = is_object($user->role) ? $user->role->value : $user->role;
                                        $roleColor = match($role) {
                                            'admin' => 'text-orange-300 border-orange-700/50 bg-orange-900/20',
                                            'manager' => 'text-sky-300 border-sky-700/50 bg-sky-900/20',
                                            default => 'text-slate-300 border-slate-700 bg-slate-800/40',
                                        };
                                    @endphp
                                    <span class="rounded-full border px-2 py-0.5 text-xs font-semibold {{ $roleColor }}">
                                        {{ ucfirst($role) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="{{ $user->is_active ? 'text-emerald-300' : 'text-rose-400' }}">
                                        {{ $user->is_active ? 'Yes' : 'No' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3">
                                    <a href="{{ route('web.admin.users.edit', $user) }}" class="rounded-md border border-slate-700 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-orange-300 hover:border-orange-300/60">
                                        Edit
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr class="border-t border-slate-800/80 text-slate-300">
                                <td colspan="5" class="px-5 py-4">No users found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-800 px-5 py-3">
                {{ $users->links() }}
            </div>
        </section>
    </section>
@endsection
