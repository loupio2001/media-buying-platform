@extends('layouts.app')

@section('title', 'Reports | Havas Media Buying Platform')

@section('content')
    <section class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-orange-300">Reports</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">Reports</h1>
                <p class="mt-2 text-slate-300">View and manage campaign performance reports.</p>
            </div>
            <a href="{{ route('web.reports.create') }}" class="rounded-md bg-orange-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-orange-400">
                + New Report
            </a>
        </div>

        <section class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900/80">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-950/40 text-slate-400">
                        <tr>
                            <th class="px-5 py-3 font-medium">Campaign</th>
                            <th class="px-5 py-3 font-medium">Type</th>
                            <th class="px-5 py-3 font-medium">Period</th>
                            <th class="px-5 py-3 font-medium">Status</th>
                            <th class="px-5 py-3 font-medium">Created</th>
                            <th class="px-5 py-3 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reports as $report)
                            <tr class="border-t border-slate-800/80 text-slate-200">
                                <td class="px-5 py-3">{{ $report->campaign?->name ?? '-' }}</td>
                                <td class="px-5 py-3 text-slate-300">{{ ucfirst($report->type) }}</td>
                                <td class="px-5 py-3 text-slate-300">
                                    {{ $report->period_start?->format('d/m/Y') }} – {{ $report->period_end?->format('d/m/Y') }}
                                </td>
                                <td class="px-5 py-3">
                                    @php
                                        $statusColor = match($report->status) {
                                            'ready' => 'text-emerald-300 border-emerald-700/50 bg-emerald-900/20',
                                            'processing' => 'text-yellow-300 border-yellow-700/50 bg-yellow-900/20',
                                            'failed' => 'text-rose-300 border-rose-700/50 bg-rose-900/20',
                                            default => 'text-slate-300 border-slate-700 bg-slate-800/40',
                                        };
                                    @endphp
                                    <span class="rounded-full border px-2 py-0.5 text-xs font-semibold {{ $statusColor }}">
                                        {{ ucfirst($report->status) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-slate-400 text-xs">{{ $report->created_at?->format('d/m/Y') }}</td>
                                <td class="px-5 py-3">
                                    <a href="{{ route('web.reports.show', $report) }}" class="rounded-md border border-slate-700 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-orange-300 hover:border-orange-300/60">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr class="border-t border-slate-800/80 text-slate-300">
                                <td colspan="6" class="px-5 py-4">No reports found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-800 px-5 py-3">
                {{ $reports->links() }}
            </div>
        </section>
    </section>
@endsection
