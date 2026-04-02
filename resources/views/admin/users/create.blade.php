@extends('layouts.app')

@section('title', 'New User | Admin | Havas Media Buying Platform')

@section('content')
    <section class="space-y-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-orange-300">Admin / Users</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">Create User</h1>
        </div>

        <form method="POST" action="{{ route('web.admin.users.store') }}" class="space-y-5 rounded-xl border border-slate-800 bg-slate-900/80 p-6">
            @csrf

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="name" class="mb-1 block text-sm text-slate-300">Full Name <span class="text-rose-400">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                </div>

                <div>
                    <label for="email" class="mb-1 block text-sm text-slate-300">Email <span class="text-rose-400">*</span></label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                </div>

                <div>
                    <label for="password" class="mb-1 block text-sm text-slate-300">Password <span class="text-rose-400">*</span></label>
                    <input type="password" id="password" name="password" required autocomplete="new-password"
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                </div>

                <div>
                    <label for="role" class="mb-1 block text-sm text-slate-300">Role <span class="text-rose-400">*</span></label>
                    <select id="role" name="role" required class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                        <option value="">Select role…</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}" @selected(old('role') === $role->value)>{{ ucfirst($role->value) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="rounded-md bg-orange-500 px-5 py-2 text-sm font-semibold text-slate-950 hover:bg-orange-400">
                    Create User
                </button>
                <a href="{{ route('web.admin.users.index') }}" class="rounded-md border border-slate-700 px-4 py-2 text-sm hover:border-orange-300/60">
                    Cancel
                </a>
            </div>
        </form>
    </section>
@endsection
