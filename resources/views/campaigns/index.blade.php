@extends('layouts.app')

@section('title', 'Campaigns | Havas Media Buying Platform')

@section('content')
    <section class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-orange-300">Campaigns</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">Campaign list</h1>
                <p class="mt-2 text-slate-300">Filter and browse recent campaigns.</p>
            </div>
            @if (auth()->user()->isAdmin() || auth()->user()->isManager())
                <a href="{{ route('web.campaigns.create') }}" class="rounded-md bg-orange-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-orange-400">
                    Create Campaign
                </a>
            @endif
        </div>

        <form method="GET" action="{{ route('web.campaigns.index') }}" class="grid gap-3 rounded-xl border border-slate-800 bg-slate-900/80 p-4 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <label for="q" class="mb-1 block text-sm text-slate-300">Search</label>
                <input id="q" name="q" value="{{ $search }}" placeholder="Campaign name" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
            </div>

            <div>
                <label for="status" class="mb-1 block text-sm text-slate-300">Status</label>
                <select id="status" name="status" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                    <option value="">All</option>
                    @foreach ($statusOptions as $status)
                        <option value="{{ $status->value }}" @selected($selectedStatus === $status->value)>
                            {{ ucfirst($status->value) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="sm:col-span-3 flex items-center gap-2">
                <button type="submit" class="rounded-md bg-orange-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-orange-400">
                    Apply filters
                </button>
                <a href="{{ route('web.campaigns.index') }}" class="rounded-md border border-slate-700 px-4 py-2 text-sm hover:border-orange-300/60">
                    Reset
                </a>
            </div>
        </form>

        <section class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900/80">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-950/40 text-slate-400">
                        <tr>
                            <th class="px-5 py-3 font-medium">Name</th>
                            <th class="px-5 py-3 font-medium">Client</th>
                            <th class="px-5 py-3 font-medium">Status</th>
                            <th class="px-5 py-3 font-medium">Budget (MAD)</th>
                            <th class="px-5 py-3 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($campaigns as $campaign)
                            <tr class="border-t border-slate-800/80 text-slate-200">
                                <td class="px-5 py-3">{{ $campaign->name }}</td>
                                <td class="px-5 py-3 text-slate-300">{{ $campaign->client?->name ?? '-' }}</td>
                                <td class="px-5 py-3 text-slate-300">{{ ucfirst(is_object($campaign->status) ? $campaign->status->value : $campaign->status) }}</td>
                                <td class="px-5 py-3">{{ number_format((float) $campaign->total_budget, 2, '.', ' ') }}</td>
                                <td class="px-5 py-3">
                                    <a href="{{ route('web.campaigns.show', $campaign) }}" class="rounded-md border border-slate-700 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-orange-300 hover:border-orange-300/60">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr class="border-t border-slate-800/80 text-slate-300">
                                <td colspan="5" class="px-5 py-4">No campaigns found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-800 px-5 py-3">
                {{ $campaigns->links() }}
            </div>
        </section>
    </section>
@endsection
