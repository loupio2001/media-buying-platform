@extends('layouts.app')

@section('title', 'Campaign Details | Havas Media Buying Platform')

@section('content')
    @php
        $campaignCurrency = strtoupper((string) ($campaign->currency ?: 'MAD'));
        $platformOptions = $campaign->campaignPlatforms
            ->map(fn ($item) => $item->platform)
            ->filter()
            ->unique('id')
            ->values();
    @endphp

    <section class="space-y-6">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-orange-300">Campaign</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">{{ $campaign->name }}</h1>
                <p class="mt-2 text-slate-300">Client: {{ $campaign->client?->name ?? '-' }}</p>
            </div>

            <div class="flex items-center gap-3">
                @if (auth()->user()->isAdmin() || auth()->user()->isManager())
                    <form method="POST" action="{{ route('web.campaigns.status.update', $campaign) }}" class="flex items-center gap-2">
                        @csrf
                        @method('PATCH')
                        <label for="status" class="text-xs uppercase tracking-wider text-slate-400">Status</label>
                        <select id="status" name="status" class="rounded-md border border-slate-700 bg-slate-950 px-2 py-1.5 text-sm text-slate-100 focus:border-orange-300 focus:outline-none">
                            @foreach (\App\Enums\CampaignStatus::cases() as $status)
                                <option value="{{ $status->value }}" @selected((is_object($campaign->status) ? $campaign->status->value : $campaign->status) === $status->value)>
                                    {{ ucfirst($status->value) }}
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" class="rounded-md bg-orange-500 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-slate-950 hover:bg-orange-400">
                            Update
                        </button>
                    </form>

                    <a href="{{ route('web.campaigns.edit', $campaign) }}" class="rounded-md border border-slate-700 px-3 py-1.5 text-sm hover:border-orange-300/60">
                        Edit
                    </a>

                    <form method="POST" action="{{ route('web.campaigns.destroy', $campaign) }}" onsubmit="return confirm('Delete this campaign permanently?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-md border border-rose-700/70 px-3 py-1.5 text-sm text-rose-200 hover:border-rose-500">
                            Delete
                        </button>
                    </form>
                @endif

                <a href="{{ route('dashboard') }}" class="rounded-md border border-slate-700 px-3 py-1.5 text-sm hover:border-orange-300/60">
                    Back to dashboard
                </a>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <article class="rounded-xl border border-slate-800 bg-slate-900/80 p-5">
                <p class="text-sm text-slate-400">Status</p>
                <p class="mt-3 text-lg font-semibold text-white">{{ ucfirst(is_object($campaign->status) ? $campaign->status->value : $campaign->status) }}</p>
            </article>

            <article class="rounded-xl border border-slate-800 bg-slate-900/80 p-5">
                <p class="text-sm text-slate-400">Total budget ({{ $campaignCurrency }})</p>
                <p class="mt-3 text-lg font-semibold text-white">{{ number_format((float) $campaign->total_budget, 2, '.', ' ') }} {{ $campaignCurrency }}</p>
            </article>

            <article class="rounded-xl border border-slate-800 bg-slate-900/80 p-5">
                <p class="text-sm text-slate-400">Total spend ({{ $campaignCurrency }})</p>
                <p class="mt-3 text-lg font-semibold text-white">{{ number_format($kpi['total_spend'], 2, '.', ' ') }} {{ $campaignCurrency }}</p>
            </article>

            <article class="rounded-xl border border-slate-800 bg-slate-900/80 p-5">
                <p class="text-sm text-slate-400">CTR (%)</p>
                <p class="mt-3 text-lg font-semibold text-white">{{ number_format($kpi['ctr'], 4, '.', ' ') }}</p>
            </article>
        </div>

        <section class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900/80">
            <div class="border-b border-slate-800 px-5 py-4">
                <h2 class="text-lg font-semibold text-white">Platforms</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-950/40 text-slate-400">
                        <tr>
                            <th class="px-5 py-3 font-medium">Platform</th>
                            <th class="px-5 py-3 font-medium">External campaign ID</th>
                            <th class="px-5 py-3 font-medium">Budget ({{ $campaignCurrency }})</th>
                            <th class="px-5 py-3 font-medium">Type</th>
                            <th class="px-5 py-3 font-medium">Spend ({{ $campaignCurrency }})</th>
                            <th class="px-5 py-3 font-medium">Impressions</th>
                            <th class="px-5 py-3 font-medium">Clicks</th>
                            <th class="px-5 py-3 font-medium">CTR (%)</th>
                            <th class="px-5 py-3 font-medium">Active</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($platformTotals as $item)
                            <tr class="border-t border-slate-800/80 text-slate-200">
                                <td class="px-5 py-3">{{ $item->platform_name }}</td>
                                <td class="px-5 py-3 text-slate-300">{{ $item->external_campaign_id ?? '-' }}</td>
                                <td class="px-5 py-3">{{ number_format((float) $item->budget, 2, '.', ' ') }} {{ $campaignCurrency }}</td>
                                <td class="px-5 py-3 text-slate-300">{{ $item->budget_type }}</td>
                                <td class="px-5 py-3">{{ number_format((float) $item->total_spend, 2, '.', ' ') }} {{ $campaignCurrency }}</td>
                                <td class="px-5 py-3">{{ number_format((float) $item->total_impressions, 0, '.', ' ') }}</td>
                                <td class="px-5 py-3">{{ number_format((float) $item->total_clicks, 0, '.', ' ') }}</td>
                                <td class="px-5 py-3">{{ number_format((float) $item->calc_ctr, 4, '.', ' ') }}</td>
                                <td class="px-5 py-3">{{ $item->is_active ? 'Yes' : 'No' }}</td>
                            </tr>
                        @empty
                            <tr class="border-t border-slate-800/80 text-slate-300">
                                <td colspan="9" class="px-5 py-4">No platform connected yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-xl border border-slate-800 bg-slate-900/80 p-5 space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-white">AI Campaign Commentary</h2>
                    <p class="mt-1 text-sm text-slate-400">Comments are refreshed only when you click Update comments, using current filters.</p>
                </div>

                @if (auth()->user()->isAdmin() || auth()->user()->isManager())
                    <form method="POST" action="{{ route('web.campaigns.ai-comments.regenerate', $campaign) }}" class="flex items-center gap-2">
                        @csrf
                        <input type="hidden" name="days" value="{{ $selectedPeriod }}">
                        @if ($selectedPlatformId)
                            <input type="hidden" name="platform_id" value="{{ $selectedPlatformId }}">
                        @endif
                        <button type="submit" class="rounded-md bg-orange-500 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-slate-950 hover:bg-orange-400">
                            Update comments
                        </button>
                    </form>
                @endif
            </div>

            @if ($campaign->ai_commentary_generated_at)
                <p class="text-xs text-slate-500">
                    Last update: {{ $campaign->ai_commentary_generated_at->format('d/m/Y H:i') }}
                </p>
            @endif

            @if ($campaign->ai_commentary_summary)
                <div class="rounded-lg border border-sky-800/40 bg-sky-900/10 p-4 space-y-3">
                    <p class="text-sm text-slate-200">{{ $campaign->ai_commentary_summary }}</p>

                    @if (! empty($campaign->ai_commentary_highlights))
                        <div>
                            <p class="text-xs font-semibold text-slate-400 mb-1">Highlights</p>
                            <ul class="list-disc pl-4 space-y-0.5 text-sm text-slate-300">
                                @foreach ((array) $campaign->ai_commentary_highlights as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (! empty($campaign->ai_commentary_concerns))
                        <div>
                            <p class="text-xs font-semibold text-slate-400 mb-1">Concerns</p>
                            <ul class="list-disc pl-4 space-y-0.5 text-sm text-rose-300">
                                @foreach ((array) $campaign->ai_commentary_concerns as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($campaign->ai_commentary_suggested_action)
                        <div>
                            <p class="text-xs font-semibold text-slate-400 mb-1">Suggested Action</p>
                            <p class="text-sm text-emerald-300">{{ $campaign->ai_commentary_suggested_action }}</p>
                        </div>
                    @endif
                </div>
            @else
                <p class="text-sm text-slate-500 italic">No AI commentary yet. Click Update comments to generate insights from current filters.</p>
            @endif
        </section>

        <section class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900/80">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800 px-5 py-4">
                <h2 class="text-lg font-semibold text-white">Trend {{ $selectedPeriod }} days</h2>

                <div class="flex flex-wrap items-center gap-2">
                    <form method="GET" action="{{ route('web.campaigns.show', $campaign) }}" class="flex items-center gap-2">
                        <input type="hidden" name="days" value="{{ $selectedPeriod }}">
                        <label for="platform_id" class="text-xs uppercase tracking-wider text-slate-400">Platform</label>
                        <select id="platform_id" name="platform_id" onchange="this.form.submit()" class="rounded-md border border-slate-700 bg-slate-950 px-2 py-1.5 text-xs text-slate-100 focus:border-orange-300 focus:outline-none">
                            <option value="">All</option>
                            @foreach ($platformOptions as $platformOption)
                                <option value="{{ $platformOption->id }}" @selected((int) $selectedPlatformId === (int) $platformOption->id)>{{ $platformOption->name }}</option>
                            @endforeach
                        </select>
                    </form>

                    @foreach ($periodOptions as $period)
                        <a
                            href="{{ route('web.campaigns.show', ['campaign' => $campaign->id, 'days' => $period, 'platform_id' => $selectedPlatformId]) }}"
                            class="rounded-md border px-3 py-1.5 text-xs font-semibold uppercase tracking-wider {{ $selectedPeriod === $period ? 'border-orange-300/60 text-orange-300' : 'border-slate-700 text-slate-300 hover:border-orange-300/60' }}"
                        >
                            {{ $period }}d
                        </a>
                    @endforeach
                    <a
                        href="{{ route('web.campaigns.trend.csv', ['campaign' => $campaign->id, 'days' => $selectedPeriod, 'platform_id' => $selectedPlatformId]) }}"
                        class="rounded-md border border-slate-700 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-sky-300 hover:border-sky-300/60"
                    >
                        Export CSV
                    </a>
                </div>
            </div>

            <div class="grid gap-4 border-b border-slate-800 bg-slate-950/20 px-5 py-4 lg:grid-cols-2">
                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-400">Spend sparkline</p>
                    <p class="mt-1 text-sm text-slate-300">
                        Last day: <span class="font-semibold text-white">{{ number_format((float) $spendSparkline['lastSpend'], 2, '.', ' ') }} {{ $campaignCurrency }}</span>
                        | Max: <span class="font-semibold text-white">{{ number_format((float) $spendSparkline['maxSpend'], 2, '.', ' ') }} {{ $campaignCurrency }}</span>
                    </p>

                    @if (! $spendSparkline['hasData'])
                        <p class="mt-2 inline-flex rounded-full border border-amber-300/40 bg-amber-500/10 px-2 py-1 text-[11px] font-semibold uppercase tracking-wider text-amber-300">
                            No data yet
                        </p>
                    @endif

                    <svg viewBox="0 0 240 72" class="mt-2 h-16 w-full max-w-[320px]" role="img" aria-label="Spend trend sparkline">
                        <defs>
                            <linearGradient id="spendAreaGradient" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#fb923c" stop-opacity="0.55" />
                                <stop offset="100%" stop-color="#fb923c" stop-opacity="0.05" />
                            </linearGradient>
                        </defs>
                        @if ($spendSparkline['areaPoints'] !== '')
                            <polygon points="{{ $spendSparkline['areaPoints'] }}" fill="url(#spendAreaGradient)" />
                        @endif
                        @if ($spendSparkline['linePoints'] !== '')
                            <polyline points="{{ $spendSparkline['linePoints'] }}" fill="none" stroke="#fb923c" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                        @endif
                    </svg>
                </div>

                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-400">Clicks sparkline</p>
                    <p class="mt-1 text-sm text-slate-300">
                        Last day: <span class="font-semibold text-white">{{ number_format((float) $clicksSparkline['lastSpend'], 0, '.', ' ') }} clicks</span>
                        | Max: <span class="font-semibold text-white">{{ number_format((float) $clicksSparkline['maxSpend'], 0, '.', ' ') }} clicks</span>
                    </p>

                    @if (! $clicksSparkline['hasData'])
                        <p class="mt-2 inline-flex rounded-full border border-amber-300/40 bg-amber-500/10 px-2 py-1 text-[11px] font-semibold uppercase tracking-wider text-amber-300">
                            No data yet
                        </p>
                    @endif

                    <svg viewBox="0 0 240 72" class="mt-2 h-16 w-full max-w-[320px]" role="img" aria-label="Clicks trend sparkline">
                        <defs>
                            <linearGradient id="clicksAreaGradient" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#38bdf8" stop-opacity="0.55" />
                                <stop offset="100%" stop-color="#38bdf8" stop-opacity="0.05" />
                            </linearGradient>
                        </defs>
                        @if ($clicksSparkline['areaPoints'] !== '')
                            <polygon points="{{ $clicksSparkline['areaPoints'] }}" fill="url(#clicksAreaGradient)" />
                        @endif
                        @if ($clicksSparkline['linePoints'] !== '')
                            <polyline points="{{ $clicksSparkline['linePoints'] }}" fill="none" stroke="#38bdf8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                        @endif
                    </svg>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-950/40 text-slate-400">
                        <tr>
                            <th class="px-5 py-3 font-medium">Date</th>
                            <th class="px-5 py-3 font-medium">Spend ({{ $campaignCurrency }})</th>
                            <th class="px-5 py-3 font-medium">Impressions</th>
                            <th class="px-5 py-3 font-medium">Clicks</th>
                            <th class="px-5 py-3 font-medium">CTR (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($dailyTrend as $row)
                            <tr class="border-t border-slate-800/80 text-slate-200">
                                <td class="px-5 py-3">{{ $row->snapshot_date }}</td>
                                <td class="px-5 py-3">{{ number_format((float) $row->total_spend, 2, '.', ' ') }} {{ $campaignCurrency }}</td>
                                <td class="px-5 py-3">{{ number_format((float) $row->total_impressions, 0, '.', ' ') }}</td>
                                <td class="px-5 py-3">{{ number_format((float) $row->total_clicks, 0, '.', ' ') }}</td>
                                <td class="px-5 py-3">{{ number_format((float) $row->calc_ctr, 4, '.', ' ') }}</td>
                            </tr>
                        @empty
                            <tr class="border-t border-slate-800/80 text-slate-300">
                                <td colspan="5" class="px-5 py-4">No daily snapshot available for the selected period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </section>
@endsection
