@extends('layouts.app')

@section('title', 'Briefs | Havas Media Buying Platform')

@section('content')
    <section class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-orange-300">Briefs</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">Campaign Briefs</h1>
                <p class="mt-2 text-slate-300">Manage campaign briefs and AI analysis.</p>
            </div>
            <a href="{{ route('web.briefs.create') }}" class="rounded-md bg-orange-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-orange-400">
                + New Brief
            </a>
        </div>

        <section class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900/80">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-950/40 text-slate-400">
                        <tr>
                            <th class="px-5 py-3 font-medium">Campaign</th>
                            <th class="px-5 py-3 font-medium">Objective</th>
                            <th class="px-5 py-3 font-medium">Budget</th>
                            <th class="px-5 py-3 font-medium">Status</th>
                            <th class="px-5 py-3 font-medium">AI Score</th>
                            <th class="px-5 py-3 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($briefs as $brief)
                            <tr class="border-t border-slate-800/80 text-slate-200">
                                <td class="px-5 py-3">{{ $brief->campaign?->name ?? '-' }}</td>
                                <td class="px-5 py-3 text-slate-300 max-w-xs truncate">{{ $brief->objective ?? '-' }}</td>
                                <td class="px-5 py-3">{{ $brief->budget_total ? number_format((float)$brief->budget_total, 0) . ' MAD' : '-' }}</td>
                                <td class="px-5 py-3">
                                    @php
                                        $status = is_object($brief->status) ? $brief->status->value : $brief->status;
                                        $statusColor = match($status) {
                                            'approved' => 'text-emerald-300 border-emerald-700/50 bg-emerald-900/20',
                                            'pending_review' => 'text-yellow-300 border-yellow-700/50 bg-yellow-900/20',
                                            'rejected' => 'text-rose-300 border-rose-700/50 bg-rose-900/20',
                                            default => 'text-slate-300 border-slate-700 bg-slate-800/40',
                                        };
                                    @endphp
                                    <span class="rounded-full border px-2 py-0.5 text-xs font-semibold {{ $statusColor }}">
                                        {{ ucfirst(str_replace('_', ' ', $status ?? 'draft')) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3">
                                    @if ($brief->ai_brief_quality_score)
                                        <span class="font-semibold {{ $brief->ai_brief_quality_score >= 7 ? 'text-emerald-300' : ($brief->ai_brief_quality_score >= 4 ? 'text-yellow-300' : 'text-rose-300') }}">
                                            {{ $brief->ai_brief_quality_score }}/10
                                        </span>
                                    @else
                                        <span class="text-slate-500">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <a href="{{ route('web.briefs.show', $brief) }}" class="rounded-md border border-slate-700 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-orange-300 hover:border-orange-300/60">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr class="border-t border-slate-800/80 text-slate-300">
                                <td colspan="6" class="px-5 py-4">No briefs found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-800 px-5 py-3">
                {{ $briefs->links() }}
            </div>
        </section>
    </section>
@endsection
