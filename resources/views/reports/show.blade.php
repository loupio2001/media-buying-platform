@extends('layouts.app')

@section('title', $report->title ?? 'Report | Havas Media Buying Platform')

@section('content')
    @php
        $reportCurrency = strtoupper((string) ($report->campaign?->currency ?: 'MAD'));
    @endphp

    <section class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-orange-300">Reports</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">{{ $report->title ?? 'Report #' . $report->id }}</h1>
                <p class="mt-1 text-slate-300">
                    {{ $report->campaign?->name ?? '-' }} &mdash;
                    {{ $report->period_start?->format('d/m/Y') }} – {{ $report->period_end?->format('d/m/Y') }}
                </p>
            </div>
            <div class="flex items-center gap-3">
                @if (auth()->user()->isAdmin() || auth()->user()->isManager())
                    <a href="{{ route('web.reports.edit', $report) }}" class="rounded-md border border-slate-700 px-4 py-2 text-sm hover:border-orange-300/60">
                        Edit
                    </a>
                    <form method="POST" action="{{ route('web.reports.destroy', $report) }}" onsubmit="return confirm('Delete this report permanently?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-md border border-rose-700/70 px-4 py-2 text-sm text-rose-200 hover:border-rose-500">
                            Delete
                        </button>
                    </form>
                    <button id="regen-btn"
                        data-report-id="{{ $report->id }}"
                        class="rounded-md border border-slate-700 px-4 py-2 text-sm hover:border-orange-300/60">
                        ↺ Regenerate AI Comments
                    </button>
                @endif
                <a href="{{ route('web.reports.index') }}" class="rounded-md border border-slate-700 px-4 py-2 text-sm hover:border-orange-300/60">
                    ← Back
                </a>
            </div>
        </div>

        {{-- Summary --}}
        @if ($report->executive_summary)
            <div class="rounded-xl border border-slate-800 bg-slate-900/80 p-5">
                <h2 class="mb-2 text-sm font-semibold uppercase tracking-widest text-orange-300">Executive Summary</h2>
                <p class="text-slate-200">{{ $report->executive_summary }}</p>
            </div>
        @endif

        {{-- Platform Sections --}}
        @forelse ($report->platformSections as $section)
            <div class="rounded-xl border border-slate-800 bg-slate-900/80 p-5 space-y-4">
                <h2 class="text-lg font-semibold text-white">{{ $section->platform?->name ?? 'Platform' }}</h2>

                {{-- Metrics grid --}}
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    @foreach ([
                        'Impressions' => number_format((int)$section->impressions),
                        'Clicks' => number_format((int)$section->clicks),
                        'Spend' => number_format((float)$section->spend, 2) . ' ' . $reportCurrency,
                        'CTR' => number_format((float)$section->ctr, 2) . '%',
                        'CPM' => number_format((float)$section->cpm, 2),
                        'CPC' => number_format((float)$section->cpc, 2),
                    ] as $label => $value)
                        <div class="rounded-lg border border-slate-700 bg-slate-950/60 p-3">
                            <p class="text-xs text-slate-400">{{ $label }}</p>
                            <p class="mt-1 text-base font-semibold text-slate-100">{{ $value }}</p>
                        </div>
                    @endforeach
                </div>

                {{-- AI Commentary --}}
                @if ($section->ai_summary)
                    <div class="rounded-lg border border-sky-800/40 bg-sky-900/10 p-4 space-y-3">
                        <p class="text-xs font-semibold uppercase tracking-widest text-sky-400">AI Commentary</p>
                        <p class="text-slate-200 text-sm">{{ $section->ai_summary }}</p>
                        @if ($section->ai_highlights)
                            <div>
                                <p class="text-xs font-semibold text-slate-400 mb-1">Highlights</p>
                                <ul class="list-disc pl-4 space-y-0.5 text-sm text-slate-300">
                                    @foreach ((array)$section->ai_highlights as $h)
                                        <li>{{ $h }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        @if ($section->ai_concerns)
                            <div>
                                <p class="text-xs font-semibold text-slate-400 mb-1">Concerns</p>
                                <ul class="list-disc pl-4 space-y-0.5 text-sm text-rose-300">
                                    @foreach ((array)$section->ai_concerns as $c)
                                        <li>{{ $c }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        @if ($section->ai_suggested_action)
                            <div>
                                <p class="text-xs font-semibold text-slate-400 mb-1">Suggested Action</p>
                                <p class="text-sm text-emerald-300">{{ $section->ai_suggested_action }}</p>
                            </div>
                        @endif
                    </div>
                @else
                    <p class="text-sm text-slate-500 italic">No AI commentary yet. Click "Regenerate AI Comments" above.</p>
                @endif
            </div>
        @empty
            <div class="rounded-xl border border-slate-800 bg-slate-900/80 p-5 text-slate-400">
                No platform sections available for this report.
            </div>
        @endforelse
    </section>

    <script>
        document.getElementById('regen-btn')?.addEventListener('click', async function () {
            const reportId = this.dataset.reportId;
            this.disabled = true;
            this.textContent = 'Regenerating…';
            try {
                const res = await fetch(`/reports/${reportId}/ai-comments/regenerate`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                });
                if (res.ok) {
                    window.location.reload();
                } else {
                    let message = 'Failed to regenerate AI comments. Please try again.';
                    try {
                        const payload = await res.json();
                        if (payload?.error) {
                            message = `${message}\n\n${payload.error}`;
                        } else if (payload?.message) {
                            message = `${message}\n\n${payload.message}`;
                        }
                    } catch (ignored) {}

                    alert(message);
                    this.disabled = false;
                    this.textContent = '↺ Regenerate AI Comments';
                }
            } catch (e) {
                alert('Network error. Please try again.');
                this.disabled = false;
                this.textContent = '↺ Regenerate AI Comments';
            }
        });
    </script>
@endsection
