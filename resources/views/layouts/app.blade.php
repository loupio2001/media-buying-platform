<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    @php
        $initialToasts = [];

        if (session('status')) {
            $initialToasts[] = [
                'type' => 'success',
                'title' => 'Success',
                'message' => session('status'),
            ];
        }

        if (session('error')) {
            $initialToasts[] = [
                'type' => 'error',
                'title' => 'Error',
                'message' => session('error'),
            ];
        }

        if ($errors->any()) {
            $initialToasts[] = [
                'type' => 'error',
                'title' => 'Validation error',
                'message' => implode(' ', $errors->all()),
            ];
        }
    @endphp
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', config('app.name', 'Havas Media Buying Platform'))</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
        <div class="relative min-h-screen">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute -top-32 left-1/2 h-96 w-96 -translate-x-1/2 rounded-full bg-orange-500/20 blur-3xl"></div>
                <div class="absolute bottom-0 right-0 h-72 w-72 rounded-full bg-sky-500/10 blur-3xl"></div>
            </div>

            <header class="relative border-b border-slate-800/80 bg-slate-900/70 backdrop-blur">
                <div class="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-4 sm:px-8">
                    <a href="{{ route('home') }}" class="text-sm font-semibold uppercase tracking-[0.2em] text-orange-300">
                        Havas Media
                    </a>

                    <nav class="flex items-center gap-3 text-sm">
                        @auth
                            <a href="{{ route('dashboard') }}" class="rounded-md border border-slate-700 px-3 py-1.5 hover:border-orange-300/60">
                                Dashboard
                            </a>
                            <a href="{{ route('web.campaigns.index') }}" class="rounded-md border border-slate-700 px-3 py-1.5 hover:border-orange-300/60">
                                Campaigns
                            </a>
                            <a href="{{ route('web.reports.index') }}" class="rounded-md border border-slate-700 px-3 py-1.5 hover:border-orange-300/60">
                                Reports
                            </a>
                            <a href="{{ route('web.briefs.index') }}" class="rounded-md border border-slate-700 px-3 py-1.5 hover:border-orange-300/60">
                                Briefs
                            </a>
                            <a href="{{ route('web.clients.index') }}" class="rounded-md border border-slate-700 px-3 py-1.5 hover:border-orange-300/60">
                                Clients
                            </a>
                            @if (auth()->user()->isAdmin() || auth()->user()->isManager())
                                <a href="{{ route('web.platform-connections.index') }}" class="rounded-md border border-slate-700 px-3 py-1.5 hover:border-orange-300/60">
                                    Connections
                                </a>
                            @endif
                            @if (auth()->user()->isAdmin())
                                <a href="{{ route('web.admin.users.index') }}" class="rounded-md border border-slate-700 px-3 py-1.5 hover:border-orange-300/60">
                                    Admin
                                </a>
                            @endif

                            {{-- Notification bell --}}
                            <x-notification-bell />

                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="rounded-md border border-slate-700 px-3 py-1.5 hover:border-orange-300/60">
                                    Logout
                                </button>
                            </form>
                        @else
                            <a href="{{ route('login') }}" class="rounded-md border border-slate-700 px-3 py-1.5 hover:border-orange-300/60">
                                Login
                            </a>
                        @endauth
                    </nav>
                </div>
            </header>

            <main class="relative mx-auto w-full max-w-6xl px-6 py-10 sm:px-8">
                @yield('content')
            </main>

            <div
                x-data="toastStack()"
                x-init="init()"
                class="pointer-events-none fixed inset-x-0 bottom-6 z-50 flex justify-center px-4 sm:bottom-8"
            >
                <div class="flex w-full max-w-md flex-col gap-3">
                    <template x-for="toast in toasts" :key="toast.id">
                        <div
                            class="pointer-events-auto rounded-xl border px-4 py-3 shadow-2xl backdrop-blur"
                            :class="toastClasses(toast.type)"
                        >
                            <div class="flex items-start gap-3">
                                <div class="mt-1 h-2.5 w-2.5 rounded-full" :class="toastDotClasses(toast.type)"></div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-white" x-text="toast.title"></p>
                                    <p class="mt-1 text-sm leading-6 text-slate-200" x-text="toast.message"></p>
                                </div>
                                <button
                                    type="button"
                                    class="rounded-md px-2 py-1 text-lg leading-none text-slate-300 transition hover:text-white"
                                    @click="remove(toast.id)"
                                    aria-label="Close toast"
                                >
                                    &times;
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <script>
            window.__initialToasts = @json($initialToasts);
        </script>
    </body>
</html>
