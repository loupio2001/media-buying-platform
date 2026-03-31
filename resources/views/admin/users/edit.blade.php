@extends('layouts.app')

@section('title', 'Edit User | Admin | Havas Media Buying Platform')

@section('content')
    <section class="space-y-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-orange-300">Admin / Users</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">Edit User</h1>
            <p class="mt-1 text-slate-400">{{ $user->email }}</p>
        </div>

        @if ($errors->any())
            <div class="rounded-lg border border-rose-700/50 bg-rose-900/20 px-4 py-3 text-sm text-rose-200">
                <ul class="list-disc pl-4 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('web.admin.users.update', $user) }}" class="space-y-5 rounded-xl border border-slate-800 bg-slate-900/80 p-6">
            @csrf
            @method('PATCH')

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="name" class="mb-1 block text-sm text-slate-300">Full Name <span class="text-rose-400">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                </div>

                <div>
                    <label for="email" class="mb-1 block text-sm text-slate-300">Email <span class="text-rose-400">*</span></label>
                    <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                </div>

                <div>
                    <label for="password" class="mb-1 block text-sm text-slate-300">New Password <span class="text-slate-500 text-xs">(leave blank to keep current)</span></label>
                    <input type="password" id="password" name="password" autocomplete="new-password"
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                </div>

                <div>
                    <label for="role" class="mb-1 block text-sm text-slate-300">Role <span class="text-rose-400">*</span></label>
                    <select id="role" name="role" required class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}" @selected(old('role', is_object($user->role) ? $user->role->value : $user->role) === $role->value)>
                                {{ ucfirst($role->value) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="rounded-md bg-orange-500 px-5 py-2 text-sm font-semibold text-slate-950 hover:bg-orange-400">
                    Save Changes
                </button>
                <a href="{{ route('web.admin.users.index') }}" class="rounded-md border border-slate-700 px-4 py-2 text-sm hover:border-orange-300/60">
                    Cancel
                </a>
            </div>
        </form>
    </section>
@endsection
