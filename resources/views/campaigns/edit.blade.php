@extends('layouts.app')

@section('title', 'Edit Campaign | Havas Media Buying Platform')

@section('content')
    @php
        $campaignCurrency = strtoupper((string) ($campaign->currency ?: 'MAD'));
    @endphp

    <section class="space-y-6">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-orange-300">Campaigns</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">Edit Campaign</h1>
                <p class="mt-2 text-slate-300">Update campaign details and platform links.</p>
            </div>

            <a href="{{ route('web.campaigns.show', $campaign) }}" class="rounded-md border border-slate-700 px-3 py-1.5 text-sm hover:border-orange-300/60">
                Back to details
            </a>
        </div>

        <form method="POST" action="{{ route('web.campaigns.update', $campaign) }}" class="space-y-5 rounded-xl border border-slate-800 bg-slate-900/80 p-6">
            @csrf
            @method('PATCH')

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="client_id" class="mb-1 block text-sm text-slate-300">Client <span class="text-rose-400">*</span></label>
                    <select id="client_id" name="client_id" required class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                        <option value="">Select client...</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" @selected((int) old('client_id', $campaign->client_id) === $client->id)>{{ $client->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="objective" class="mb-1 block text-sm text-slate-300">Objective <span class="text-rose-400">*</span></label>
                    <select id="objective" name="objective" required class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                        <option value="">Select objective...</option>
                        @foreach (\App\Enums\CampaignObjective::cases() as $objective)
                            <option value="{{ $objective->value }}" @selected(old('objective', is_object($campaign->objective) ? $campaign->objective->value : $campaign->objective) === $objective->value)>
                                {{ ucfirst(str_replace('_', ' ', $objective->value)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <label for="name" class="mb-1 block text-sm text-slate-300">Campaign name <span class="text-rose-400">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name', $campaign->name) }}" required maxlength="200"
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                </div>

                <div>
                    <label for="start_date" class="mb-1 block text-sm text-slate-300">Start date <span class="text-rose-400">*</span></label>
                    <input type="date" id="start_date" name="start_date" value="{{ old('start_date', optional($campaign->start_date)->format('Y-m-d')) }}" required
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                </div>

                <div>
                    <label for="end_date" class="mb-1 block text-sm text-slate-300">End date <span class="text-rose-400">*</span></label>
                    <input type="date" id="end_date" name="end_date" value="{{ old('end_date', optional($campaign->end_date)->format('Y-m-d')) }}" required
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                </div>

                <div>
                    <label for="total_budget" class="mb-1 block text-sm text-slate-300">Total budget <span class="text-rose-400">*</span></label>
                    <input type="number" id="total_budget" name="total_budget" value="{{ old('total_budget', (float) $campaign->total_budget) }}" min="0" step="0.01" required
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                </div>

                <div>
                    <label for="currency" class="mb-1 block text-sm text-slate-300">Currency</label>
                    <input type="text" id="currency" name="currency" value="{{ old('currency', $campaignCurrency) }}" maxlength="10"
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                </div>

                <div class="sm:col-span-2">
                    <label for="internal_notes" class="mb-1 block text-sm text-slate-300">Internal notes</label>
                    <textarea id="internal_notes" name="internal_notes" rows="4"
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">{{ old('internal_notes', $campaign->internal_notes) }}</textarea>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="rounded-md bg-orange-500 px-5 py-2 text-sm font-semibold text-slate-950 hover:bg-orange-400">
                    Save changes
                </button>
                <a href="{{ route('web.campaigns.show', $campaign) }}" class="rounded-md border border-slate-700 px-4 py-2 text-sm hover:border-orange-300/60">
                    Cancel
                </a>
            </div>
        </form>

        <section class="space-y-4 rounded-xl border border-slate-800 bg-slate-900/80 p-5">
            <div>
                <h2 class="text-lg font-semibold text-white">Link Platform</h2>
                <p class="mt-1 text-sm text-slate-300">Attach this campaign to a source platform so manual sync can pull its data.</p>
            </div>

            @if ($campaign->campaignPlatforms->isNotEmpty())
                <div class="space-y-3 rounded-lg border border-slate-800 bg-slate-950/40 p-4">
                    <p class="text-sm font-semibold text-slate-200">Current links</p>
                    <div class="space-y-2">
                        @foreach ($campaign->campaignPlatforms as $campaignPlatform)
                            <div class="flex flex-col gap-3 rounded-md border border-slate-800 bg-slate-950/50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="font-medium text-white">
                                        {{ $campaignPlatform->platform?->name ?? 'Platform' }}
                                    </p>
                                    <p class="text-xs text-slate-400">
                                        Connection: {{ $campaignPlatform->connection?->account_name ?: $campaignPlatform->connection?->account_id ?: 'n/a' }}
                                        · External campaign ID: {{ $campaignPlatform->external_campaign_id }}
                                    </p>
                                </div>

                                <form method="POST" action="{{ route('web.campaigns.platforms.destroy', ['campaign' => $campaign, 'campaignPlatform' => $campaignPlatform], false) }}" onsubmit="return confirm('Unlink this platform from the campaign?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-md border border-rose-700 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-rose-200 hover:border-rose-400">
                                        Unlink
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

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
                    <label for="budget" class="mb-1 block text-sm text-slate-300">Budget ({{ $campaignCurrency }}) <span class="text-rose-400">*</span></label>
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
                    <label for="platform_currency" class="mb-1 block text-sm text-slate-300">Currency</label>
                    <input type="text" id="platform_currency" name="currency" value="{{ old('currency', $campaignCurrency) }}" maxlength="10"
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
    </section>
@endsection
