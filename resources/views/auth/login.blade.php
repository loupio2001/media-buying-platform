@extends('layouts.app')

@section('title', 'Login | Havas Media Buying Platform')

@section('content')
    <div class="mx-auto w-full max-w-md rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-2xl shadow-slate-950/40 sm:p-8">
        <h1 class="text-2xl font-semibold text-white">Connexion</h1>
        <p class="mt-2 text-sm text-slate-300">Accede a la plateforme de pilotage media.</p>

        <form action="{{ route('login.store') }}" method="POST" class="mt-6 space-y-4">
            @csrf

            <div>
                <label for="email" class="mb-1 block text-sm font-medium text-slate-200">Email</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 outline-none ring-0 transition focus:border-orange-300"
                >
                @error('email')
                    <p class="mt-1 text-sm text-red-300">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="mb-1 block text-sm font-medium text-slate-200">Mot de passe</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    required
                    class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 outline-none ring-0 transition focus:border-orange-300"
                >
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-300">
                <input type="checkbox" name="remember" class="h-4 w-4 rounded border-slate-600 bg-slate-950 text-orange-400">
                Se souvenir de moi
            </label>

            <button type="submit" class="w-full rounded-lg bg-orange-500 px-4 py-2 font-semibold text-slate-950 transition hover:bg-orange-400">
                Se connecter
            </button>
        </form>
    </div>
@endsection
