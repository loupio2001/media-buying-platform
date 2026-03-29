<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Havas Media Buying Platform') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
        <main class="mx-auto flex min-h-screen w-full max-w-5xl flex-col justify-center px-6 py-16 sm:px-10">
            <p class="mb-4 inline-flex w-fit rounded-full border border-orange-400/40 bg-orange-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-widest text-orange-300">
                Havas Morocco
            </p>

            <h1 class="max-w-3xl text-3xl font-semibold leading-tight text-white sm:text-5xl">
                Media Buying Platform
            </h1>

            <p class="mt-5 max-w-2xl text-base leading-7 text-slate-300 sm:text-lg">
                L'application est bien connectee. La page d'accueil Laravel par defaut est desactivee et la racine pointe maintenant vers votre interface projet.
            </p>

            <div class="mt-10 grid gap-4 sm:grid-cols-2">
                <a href="/api" class="rounded-xl border border-slate-700 bg-slate-900/80 p-5 transition hover:border-orange-300/60 hover:bg-slate-900">
                    <p class="text-sm uppercase tracking-wider text-slate-400">Endpoint</p>
                    <p class="mt-2 text-lg font-medium text-white">API</p>
                </a>

                <a href="/" class="rounded-xl border border-slate-700 bg-slate-900/80 p-5 transition hover:border-orange-300/60 hover:bg-slate-900">
                    <p class="text-sm uppercase tracking-wider text-slate-400">Status</p>
                    <p class="mt-2 text-lg font-medium text-white">Application active</p>
                </a>
            </div>
        </main>
    </body>
</html>
