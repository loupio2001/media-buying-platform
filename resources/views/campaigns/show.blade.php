@extends('layouts.app')

@section('title', 'Campaign Details | Havas Media Buying Platform')

@section('content')
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
                <p class="text-sm text-slate-400">Total budget (MAD)</p>
                <p class="mt-3 text-lg font-semibold text-white">{{ number_format((float) $campaign->total_budget, 2, '.', ' ') }}</p>
            </article>

            <article class="rounded-xl border border-slate-800 bg-slate-900/80 p-5">
                <p class="text-sm text-slate-400">Total spend (MAD)</p>
                <p class="mt-3 text-lg font-semibold text-white">{{ number_format($kpi['total_spend'], 2, '.', ' ') }}</p>
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
                            <th class="px-5 py-3 font-medium">Budget (MAD)</th>
                            <th class="px-5 py-3 font-medium">Type</th>
                            <th class="px-5 py-3 font-medium">Spend (MAD)</th>
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
                                <td class="px-5 py-3">{{ number_format((float) $item->budget, 2, '.', ' ') }}</td>
                                <td class="px-5 py-3 text-slate-300">{{ $item->budget_type }}</td>
                                <td class="px-5 py-3">{{ number_format((float) $item->total_spend, 2, '.', ' ') }}</td>
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

        @if (auth()->user()->isAdmin() || auth()->user()->isManager())
            <section class="space-y-4 rounded-xl border border-slate-800 bg-slate-900/80 p-5">
                <div>
                    <h2 class="text-lg font-semibold text-white">Link Platform</h2>
                    <p class="mt-1 text-sm text-slate-300">Attach this campaign to a source platform so manual sync can pull its data.</p>
                </div>

                <form method="POST" action="{{ route('web.campaigns.platforms.store', $campaign) }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @csrf

                    <div>
                        <label for="platform_id" class="mb-1 block text-sm text-slate-300">Platform <span class="text-rose-400">*</span></label>
                        <select id="platform_id" name="platform_id" required class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                            <option value="">Select platform...</option>
                            @foreach ($availablePlatforms as $platform)
                                <option value="{{ $platform->id }}" @selected((int) old('platform_id') === $platform->id) @disabled(in_array($platform->id, $linkedPlatformIds, true))>
                                    {{ $platform->name }}{{ in_array($platform->id, $linkedPlatformIds, true) ? ' (already linked)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="platform_connection_id" class="mb-1 block text-sm text-slate-300">Platform connection</label>
                        <select id="platform_connection_id" name="platform_connection_id" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                            <option value="">Select connection...</option>
                            @foreach ($platformConnections as $connection)
                                <option value="{{ $connection->id }}" @selected((int) old('platform_connection_id') === $connection->id)>
                                    {{ $connection->platform?->name ?? 'Platform' }} - {{ $connection->account_name ?: $connection->account_id }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="external_campaign_id" class="mb-1 block text-sm text-slate-300">External campaign ID <span class="text-rose-400">*</span></label>
                        <input type="text" id="external_campaign_id" name="external_campaign_id" value="{{ old('external_campaign_id') }}" required maxlength="100"
                            class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                    </div>

                    <div>
                        <label for="budget" class="mb-1 block text-sm text-slate-300">Budget (MAD) <span class="text-rose-400">*</span></label>
                        <input type="number" id="budget" name="budget" value="{{ old('budget') }}" min="0" step="0.01" required
                            class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                    </div>

                    <div>
                        <label for="budget_type" class="mb-1 block text-sm text-slate-300">Budget type <span class="text-rose-400">*</span></label>
                        <select id="budget_type" name="budget_type" required class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                            <option value="lifetime" @selected(old('budget_type', 'lifetime') === 'lifetime')>Lifetime</option>
                            <option value="daily" @selected(old('budget_type') === 'daily')>Daily</option>
                        </select>
                    </div>

                    <div>
                        <label for="currency" class="mb-1 block text-sm text-slate-300">Currency</label>
                        <input type="text" id="currency" name="currency" value="{{ old('currency', 'MAD') }}" maxlength="10"
                            class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                    </div>

                    <div class="sm:col-span-2">
                        <label for="notes" class="mb-1 block text-sm text-slate-300">Notes</label>
                        <textarea id="notes" name="notes" rows="3"
                            class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">{{ old('notes') }}</textarea>
                    </div>

                    <div class="sm:col-span-2 flex items-center justify-between">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-300">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', '1') === '1') class="rounded border-slate-700 bg-slate-950 text-orange-500 focus:ring-orange-400">
                            Active link
                        </label>

                        <button type="submit" class="rounded-md bg-orange-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-orange-400">
                            Link Platform
                        </button>
                    </div>
                </form>
            </section>
        @endif

        <section class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900/80">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800 px-5 py-4">
                <h2 class="text-lg font-semibold text-white">Trend {{ $selectedPeriod }} days</h2>

                <div class="flex flex-wrap items-center gap-2">
                    @foreach ($periodOptions as $period)
                        <a
                            href="{{ route('web.campaigns.show', ['campaign' => $campaign->id, 'days' => $period]) }}"
                            class="rounded-md border px-3 py-1.5 text-xs font-semibold uppercase tracking-wider {{ $selectedPeriod === $period ? 'border-orange-300/60 text-orange-300' : 'border-slate-700 text-slate-300 hover:border-orange-300/60' }}"
                        >
                            {{ $period }}d
                        </a>
                    @endforeach
                    <a
                        href="{{ route('web.campaigns.trend.csv', ['campaign' => $campaign->id, 'days' => $selectedPeriod]) }}"
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
                        Last day: <span class="font-semibold text-white">{{ number_format((float) $spendSparkline['lastSpend'], 2, '.', ' ') }} MAD</span>
                        | Max: <span class="font-semibold text-white">{{ number_format((float) $spendSparkline['maxSpend'], 2, '.', ' ') }} MAD</span>
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
                            <th class="px-5 py-3 font-medium">Spend (MAD)</th>
                            <th class="px-5 py-3 font-medium">Impressions</th>
                            <th class="px-5 py-3 font-medium">Clicks</th>
                            <th class="px-5 py-3 font-medium">CTR (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($dailyTrend as $row)
                            <tr class="border-t border-slate-800/80 text-slate-200">
                                <td class="px-5 py-3">{{ $row->snapshot_date }}</td>
                                <td class="px-5 py-3">{{ number_format((float) $row->total_spend, 2, '.', ' ') }}</td>
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
