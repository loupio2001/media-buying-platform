@extends('layouts.app')

@section('title', 'Brief | Havas Media Buying Platform')

@section('content')
    @php
        $briefCurrency = strtoupper((string) ($brief->campaign?->currency ?: 'MAD'));
    @endphp

    <section class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-orange-300">Briefs</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">
                    Brief — {{ $brief->campaign?->name ?? '#' . $brief->id }}
                </h1>
                <p class="mt-1 text-slate-300">
                    {{ $brief->campaign?->client?->name ?? '' }}
                    @if ($brief->flight_start)
                        &mdash; {{ $brief->flight_start->format('d/m/Y') }} – {{ $brief->flight_end?->format('d/m/Y') }}
                    @endif
                </p>
            </div>
            <div class="flex items-center gap-3">
                @if (! $brief->ai_brief_quality_score)
                    <button id="analyze-btn" data-brief-id="{{ $brief->id }}"
                        class="rounded-md bg-orange-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-orange-400">
                        ✦ Run AI Analysis
                    </button>
                @endif
                <a href="{{ route('web.briefs.index') }}" class="rounded-md border border-slate-700 px-4 py-2 text-sm hover:border-orange-300/60">
                    ← Back
                </a>
            </div>
        </div>

        {{-- Brief Details --}}
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ([
                'Budget' => $brief->budget_total ? number_format((float)$brief->budget_total, 0) . ' ' . $briefCurrency : '—',
                'Flight Start' => $brief->flight_start?->format('d/m/Y') ?? '—',
                'Flight End' => $brief->flight_end?->format('d/m/Y') ?? '—',
                'Status' => ucfirst(str_replace('_', ' ', is_object($brief->status) ? $brief->status->value : ($brief->status ?? 'draft'))),
            ] as $label => $value)
                <div class="rounded-lg border border-slate-700 bg-slate-900/80 p-3">
                    <p class="text-xs text-slate-400">{{ $label }}</p>
                    <p class="mt-1 text-sm font-semibold text-slate-100">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        @if ($brief->objective)
            <div class="rounded-xl border border-slate-800 bg-slate-900/80 p-5">
                <h2 class="mb-2 text-sm font-semibold uppercase tracking-widest text-orange-300">Objective</h2>
                <p class="text-slate-200 text-sm">{{ $brief->objective }}</p>
            </div>
        @endif

        {{-- AI Analysis --}}
        @if ($brief->ai_brief_quality_score)
            <div class="space-y-4">
                <h2 class="text-lg font-semibold text-white">AI Analysis</h2>

                {{-- Quality Score --}}
                <div class="flex items-center gap-4 rounded-xl border border-slate-800 bg-slate-900/80 p-5">
                    <div class="text-4xl font-bold {{ $brief->ai_brief_quality_score >= 7 ? 'text-emerald-300' : ($brief->ai_brief_quality_score >= 4 ? 'text-yellow-300' : 'text-rose-300') }}">
                        {{ $brief->ai_brief_quality_score }}/10
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-300">Brief Quality Score</p>
                        <p class="text-xs text-slate-500">
                            {{ $brief->ai_brief_quality_score >= 7 ? 'Good quality brief' : ($brief->ai_brief_quality_score >= 4 ? 'Needs improvement' : 'Poor quality — many gaps') }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    {{-- Missing Information --}}
                    @if ($brief->ai_missing_info)
                        <div class="rounded-xl border border-slate-800 bg-slate-900/80 p-5">
                            <h3 class="mb-3 text-sm font-semibold uppercase tracking-widest text-rose-400">Missing Information</h3>
                            <ul class="space-y-1 text-sm text-slate-300">
                                @foreach ((array)$brief->ai_missing_info as $item)
                                    <li class="flex items-start gap-2"><span class="text-rose-400">✗</span> {{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- KPI Challenges --}}
                    @if ($brief->ai_kpi_challenges)
                        <div class="rounded-xl border border-slate-800 bg-slate-900/80 p-5">
                            <h3 class="mb-3 text-sm font-semibold uppercase tracking-widest text-yellow-400">KPI Challenges</h3>
                            <ul class="space-y-1 text-sm text-slate-300">
                                @foreach ((array)$brief->ai_kpi_challenges as $item)
                                    <li class="flex items-start gap-2"><span class="text-yellow-400">⚠</span> {{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Questions for Client --}}
                    @if ($brief->ai_questions_for_client)
                        <div class="rounded-xl border border-slate-800 bg-slate-900/80 p-5">
                            <h3 class="mb-3 text-sm font-semibold uppercase tracking-widest text-sky-400">Questions for Client</h3>
                            <ul class="space-y-1 text-sm text-slate-300">
                                @foreach ((array)$brief->ai_questions_for_client as $q)
                                    <li class="flex items-start gap-2"><span class="text-sky-400">?</span> {{ $q }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Channel Rationale --}}
                    @if ($brief->ai_channel_rationale)
                        <div class="rounded-xl border border-slate-800 bg-slate-900/80 p-5">
                            <h3 class="mb-3 text-sm font-semibold uppercase tracking-widest text-emerald-400">Channel Rationale</h3>
                            <p class="text-sm text-slate-300">{{ $brief->ai_channel_rationale }}</p>
                        </div>
                    @endif
                </div>

                {{-- Budget Split --}}
                @if ($brief->ai_budget_split)
                    <div class="rounded-xl border border-slate-800 bg-slate-900/80 p-5">
                        <h3 class="mb-3 text-sm font-semibold uppercase tracking-widest text-orange-300">Recommended Budget Split</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-slate-400">
                                    <tr>
                                        <th class="py-2 pr-5 text-left font-medium">Platform</th>
                                        <th class="py-2 pr-5 text-left font-medium">Allocation</th>
                                        <th class="py-2 text-left font-medium">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ((array)$brief->ai_budget_split as $platform => $pct)
                                        <tr class="border-t border-slate-800">
                                            <td class="py-2 pr-5 text-slate-200">{{ $platform }}</td>
                                            <td class="py-2 pr-5 text-orange-300 font-semibold">{{ $pct }}%</td>
                                            <td class="py-2 text-slate-300">
                                                {{ $brief->budget_total ? number_format((float)$brief->budget_total * $pct / 100, 0) . ' ' . $briefCurrency : '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- Media Plan Draft --}}
                @if ($brief->ai_media_plan_draft)
                    <div class="rounded-xl border border-slate-800 bg-slate-900/80 p-5">
                        <h3 class="mb-3 text-sm font-semibold uppercase tracking-widest text-orange-300">Media Plan Draft</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-slate-400">
                                    <tr>
                                        <th class="py-2 pr-5 text-left font-medium">Phase</th>
                                        <th class="py-2 pr-5 text-left font-medium">Duration</th>
                                        <th class="py-2 text-left font-medium">Objective</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ((array)$brief->ai_media_plan_draft as $phase)
                                        <tr class="border-t border-slate-800">
                                            <td class="py-2 pr-5 text-slate-200 font-semibold">{{ $phase['phase'] ?? '-' }}</td>
                                            <td class="py-2 pr-5 text-slate-300">{{ $phase['duration'] ?? '-' }}</td>
                                            <td class="py-2 text-slate-300">{{ $phase['objective'] ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        @else
            <div class="rounded-xl border border-slate-800 bg-slate-900/80 p-6 text-center">
                <p class="text-slate-400">No AI analysis available yet.</p>
                <p class="mt-1 text-sm text-slate-500">Click "Run AI Analysis" to analyze this brief with Claude.</p>
            </div>
        @endif
    </section>

    <script>
        document.getElementById('analyze-btn')?.addEventListener('click', async function () {
            const briefId = this.dataset.briefId;
            this.disabled = true;
            this.textContent = 'Analyzing…';
            try {
                const res = await fetch(`/api/briefs/${briefId}/analyze`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                });
                if (res.ok) {
                    window.location.reload();
                } else {
                    alert('Failed to start AI analysis. Please try again.');
                    this.disabled = false;
                    this.textContent = '✦ Run AI Analysis';
                }
            } catch (e) {
                alert('Network error. Please try again.');
                this.disabled = false;
                this.textContent = '✦ Run AI Analysis';
            }
        });
    </script>
@endsection
