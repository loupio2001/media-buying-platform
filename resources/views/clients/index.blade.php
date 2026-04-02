@extends('layouts.app')

@section('title', 'Clients | Havas Media Buying Platform')

@section('content')
    <section class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-orange-300">Clients</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">Clients</h1>
                <p class="mt-2 text-slate-300">Manage advertiser clients and their campaigns.</p>
            </div>
            <a href="{{ route('web.clients.create') }}" class="rounded-md bg-orange-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-orange-400">
                + New Client
            </a>
        </div>

        <section class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900/80">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-950/40 text-slate-400">
                        <tr>
                            <th class="px-5 py-3 font-medium">Name</th>
                            <th class="px-5 py-3 font-medium">Category</th>
                            <th class="px-5 py-3 font-medium">Contact</th>
                            <th class="px-5 py-3 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($clients as $client)
                            <tr class="border-t border-slate-800/80 text-slate-200">
                                <td class="px-5 py-3 font-medium">{{ $client->name }}</td>
                                <td class="px-5 py-3 text-slate-300">{{ $client->category?->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-300">{{ $client->contact_name ?? '—' }}</td>
                                <td class="px-5 py-3">
                                    <a href="{{ route('web.clients.show', $client) }}" class="rounded-md border border-slate-700 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-orange-300 hover:border-orange-300/60">
                                        View
                                    </a>
                                    @if (auth()->user()->isAdmin() || auth()->user()->isManager())
                                        <a href="{{ route('web.clients.edit', $client) }}" class="ml-2 rounded-md border border-slate-700 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-slate-200 hover:border-orange-300/60">
                                            Edit
                                        </a>
                                        <form method="POST" action="{{ route('web.clients.destroy', $client) }}" class="ml-2 inline" onsubmit="return confirm('Delete this client permanently?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-md border border-rose-700/70 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-rose-200 hover:border-rose-500">
                                                Delete
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr class="border-t border-slate-800/80 text-slate-300">
                                <td colspan="4" class="px-5 py-4">No clients found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-800 px-5 py-3">
                {{ $clients->links() }}
            </div>
        </section>
    </section>
@endsection
