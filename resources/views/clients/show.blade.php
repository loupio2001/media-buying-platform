@extends('layouts.app')

@section('title', $client->name . ' | Havas Media Buying Platform')

@section('content')
    <section class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-orange-300">Clients</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">{{ $client->name }}</h1>
                @if ($client->category)
                    <p class="mt-1 text-slate-400">{{ $client->category->name }}</p>
                @endif
            </div>
            <div class="flex items-center gap-3">
                @if (auth()->user()->isAdmin() || auth()->user()->isManager())
                    <a href="{{ route('web.clients.edit', $client) }}" class="rounded-md border border-slate-700 px-4 py-2 text-sm hover:border-orange-300/60">
                        Edit
                    </a>
                    <form method="POST" action="{{ route('web.clients.destroy', $client) }}" onsubmit="return confirm('Delete this client permanently?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-md border border-rose-700/70 px-4 py-2 text-sm text-rose-200 hover:border-rose-500">
                            Delete
                        </button>
                    </form>
                @endif
                <a href="{{ route('web.clients.index') }}" class="rounded-md border border-slate-700 px-4 py-2 text-sm hover:border-orange-300/60">
                    ← Back
                </a>
            </div>
        </div>

        {{-- Client Details --}}
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 rounded-xl border border-slate-800 bg-slate-900/80 p-5">
            @foreach ([
                'Industry' => $client->industry ?? '—',
                'Contact' => $client->contact_name ?? '—',
                'Email' => $client->contact_email ?? '—',
                'Phone' => $client->contact_phone ?? '—',
            ] as $label => $value)
                <div>
                    <p class="text-xs text-slate-500">{{ $label }}</p>
                    <p class="mt-0.5 text-sm text-slate-200">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        {{-- Campaigns --}}
        <div>
            <h2 class="mb-3 text-lg font-semibold text-white">Campaigns</h2>
            <section class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900/80">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-950/40 text-slate-400">
                            <tr>
                                <th class="px-5 py-3 font-medium">Name</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                                <th class="px-5 py-3 font-medium">Budget</th>
                                <th class="px-5 py-3 font-medium">Dates</th>
                                <th class="px-5 py-3 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($client->campaigns as $campaign)
                                <tr class="border-t border-slate-800/80 text-slate-200">
                                    <td class="px-5 py-3">{{ $campaign->name }}</td>
                                    <td class="px-5 py-3 text-slate-300">{{ ucfirst(is_object($campaign->status) ? $campaign->status->value : $campaign->status) }}</td>
                                    <td class="px-5 py-3">{{ number_format((float)$campaign->total_budget, 0) }} {{ strtoupper((string) ($campaign->currency ?: 'MAD')) }}</td>
                                    <td class="px-5 py-3 text-slate-400 text-xs">
                                        {{ $campaign->start_date?->format('d/m/Y') }} – {{ $campaign->end_date?->format('d/m/Y') }}
                                    </td>
                                    <td class="px-5 py-3">
                                        <a href="{{ route('web.campaigns.show', $campaign) }}" class="rounded-md border border-slate-700 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-orange-300 hover:border-orange-300/60">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr class="border-t border-slate-800/80 text-slate-300">
                                    <td colspan="5" class="px-5 py-4">No campaigns yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </section>
@endsection
