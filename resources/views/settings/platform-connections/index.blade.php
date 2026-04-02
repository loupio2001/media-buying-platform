@extends('layouts.app')

@section('title', 'Platform Connections | Havas Media Buying Platform')

@section('content')
    <section class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-orange-300">Settings</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">Platform Connections</h1>
                <p class="mt-2 max-w-2xl text-slate-300">
                    Configure advertising accounts and credentials used by collectors and scheduled data pulls.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <form method="POST" action="{{ route('web.platform-connections.sync-all', [], false) }}" data-toast-loading="Manual sync started for all active platform campaigns." data-toast-loading-title="Starting sync">
                    @csrf
                    <button type="submit" class="inline-flex items-center justify-center rounded-md border border-sky-300/60 px-4 py-2 text-sm font-semibold text-sky-200 hover:bg-sky-400/10">
                        Force Sync Now
                    </button>
                </form>
                <a href="{{ route('web.platform-connections.oauth.authorize', ['platform' => 'meta'], false) }}"
                   class="inline-flex items-center justify-center rounded-md border border-orange-300/60 px-4 py-2 text-sm font-semibold text-orange-200 hover:bg-orange-400/10">
                    Connect Meta OAuth
                </a>
                <a href="{{ route('web.platform-connections.oauth.authorize', ['platform' => 'google'], false) }}"
                   class="inline-flex items-center justify-center rounded-md border border-emerald-300/60 px-4 py-2 text-sm font-semibold text-emerald-200 hover:bg-emerald-400/10">
                    Connect Google Ads OAuth
                </a>
            </div>
        </div>

        <section class="rounded-xl border border-slate-800 bg-slate-900/80 p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="space-y-3">
                    <div>
                        <h2 class="text-lg font-semibold text-white">Google Ads</h2>
                        <p class="mt-1 text-sm text-slate-300">
                            OAuth + API setup read from `.env` and used by the Google Ads connector.
                        </p>
                        <p class="mt-1 text-xs text-slate-400">
                            After OAuth, you will choose the Google Ads customer ID to link when several accounts are available.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2 text-xs">
                        <span class="rounded-full border px-3 py-1 {{ $googleAdsConfig['client_id_configured'] ? 'border-emerald-400/50 text-emerald-200' : 'border-rose-400/50 text-rose-200' }}">
                            Client ID: {{ $googleAdsConfig['client_id_configured'] ? 'OK' : 'Missing' }}
                        </span>
                        <span class="rounded-full border px-3 py-1 {{ $googleAdsConfig['client_secret_configured'] ? 'border-emerald-400/50 text-emerald-200' : 'border-rose-400/50 text-rose-200' }}">
                            Secret: {{ $googleAdsConfig['client_secret_configured'] ? 'OK' : 'Missing' }}
                        </span>
                        <span class="rounded-full border px-3 py-1 {{ $googleAdsConfig['developer_token_configured'] ? 'border-emerald-400/50 text-emerald-200' : 'border-rose-400/50 text-rose-200' }}">
                            Developer token: {{ $googleAdsConfig['developer_token_configured'] ? 'OK' : 'Missing' }}
                        </span>
                    </div>
                </div>

                <a href="{{ route('web.platform-connections.oauth.authorize', ['platform' => 'google'], false) }}"
                   class="inline-flex items-center justify-center rounded-md border border-emerald-300/60 px-4 py-2 text-sm font-semibold text-emerald-200 hover:bg-emerald-400/10">
                    Start Google OAuth
                </a>
            </div>

            <div class="mt-4 grid gap-3 text-sm text-slate-300 md:grid-cols-2">
                <div class="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                    <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Redirect URI</p>
                    <p class="mt-1 break-all text-slate-100">{{ $googleAdsConfig['redirect_uri'] }}</p>
                </div>
                <div class="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                    <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Scopes</p>
                    <p class="mt-1 text-slate-100">{{ $googleAdsConfig['scopes'] ? implode(', ', $googleAdsConfig['scopes']) : 'n/a' }}</p>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-slate-800 bg-slate-900/80 p-5">
            <h2 class="text-lg font-semibold text-white">Add manual connection</h2>
            <form method="POST" action="{{ route('web.platform-connections.store', [], false) }}" class="mt-4 grid gap-4 md:grid-cols-2">
                @csrf
                <label class="space-y-2 text-sm text-slate-300">
                    <span>Platform</span>
                    <select name="platform_id" class="w-full rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100">
                        <option value="">Select platform</option>
                        @foreach ($connectablePlatforms as $platform)
                            <option value="{{ $platform->id }}">{{ $platform->name }} ({{ $platform->slug }})</option>
                        @endforeach
                    </select>
                </label>

                <label class="space-y-2 text-sm text-slate-300">
                    <span>Account ID</span>
                    <input type="text" name="account_id" class="w-full rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100" placeholder="e.g. 123456789">
                </label>

                <label class="space-y-2 text-sm text-slate-300">
                    <span>Account Name</span>
                    <input type="text" name="account_name" class="w-full rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100" placeholder="Optional account label">
                </label>

                <label class="space-y-2 text-sm text-slate-300">
                    <span>Auth Type</span>
                    <select name="auth_type" class="w-full rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100">
                        <option value="api_key">api_key</option>
                        <option value="oauth2">oauth2</option>
                        <option value="service_account">service_account</option>
                    </select>
                </label>

                <label class="space-y-2 text-sm text-slate-300 md:col-span-2">
                    <span>API Key (required for api_key auth)</span>
                    <input type="text" name="api_key" class="w-full rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100" placeholder="Only used for api_key">
                </label>

                <label class="space-y-2 text-sm text-slate-300 md:col-span-2">
                    <span>Access Token (required for oauth2 auth)</span>
                    <textarea name="access_token" rows="3" class="w-full rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100" placeholder="OAuth2 access token"></textarea>
                </label>

                <label class="space-y-2 text-sm text-slate-300 md:col-span-2">
                    <span>Refresh Token (optional for oauth2 auth)</span>
                    <input type="text" name="refresh_token" class="w-full rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100" placeholder="OAuth2 refresh token">
                </label>

                <label class="space-y-2 text-sm text-slate-300">
                    <span>Token Expires At (optional)</span>
                    <input type="datetime-local" name="token_expires_at" class="w-full rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100">
                </label>

                <div class="md:col-span-2">
                    <button type="submit" class="rounded-md border border-slate-700 px-4 py-2 text-sm font-semibold text-orange-300 hover:border-orange-300/60">
                        Create connection
                    </button>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900/80">
            <div class="border-b border-slate-800 px-5 py-4">
                <h2 class="text-lg font-semibold text-white">Existing connections</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-950/40 text-slate-400">
                        <tr>
                            <th class="px-5 py-3 font-medium">Platform</th>
                            <th class="px-5 py-3 font-medium">Account</th>
                            <th class="px-5 py-3 font-medium">Auth</th>
                            <th class="px-5 py-3 font-medium">Health</th>
                            <th class="px-5 py-3 font-medium">Last sync</th>
                            <th class="px-5 py-3 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($platforms as $platform)
                            @forelse ($platform->connections as $connection)
                                <tr class="border-t border-slate-800/80 text-slate-200">
                                    <td class="px-5 py-3">{{ $platform->name }}</td>
                                    <td class="px-5 py-3">
                                        <p class="font-medium">{{ $connection->account_name ?: '-' }}</p>
                                        <p class="text-xs text-slate-400">ID: {{ $connection->account_id }}</p>
                                    </td>
                                    <td class="px-5 py-3 uppercase">{{ $connection->auth_type }}</td>
                                    <td class="px-5 py-3">{{ $healthLabels[$connection->id] ?? 'Unknown' }}</td>
                                    <td class="px-5 py-3 text-slate-300">
                                        {{ $connection->last_sync_at?->format('Y-m-d H:i') ?? '-' }}
                                        <span class="ml-2 text-xs text-slate-500">{{ (int) ($connection->campaign_platforms_count ?? 0) }} linked</span>
                                    </td>
                                    <td class="px-5 py-3">
                                        @php $hasLinkedCampaigns = (int) ($connection->campaign_platforms_count ?? 0) > 0; @endphp
                                        <div class="flex flex-wrap items-center gap-2">
                                            <form method="POST" action="{{ route('web.platform-connections.update', ['platformConnection' => $connection], false) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="is_connected" value="{{ $connection->is_connected ? '0' : '1' }}">
                                                <button type="submit" class="rounded-md border border-slate-700 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-slate-200 hover:border-orange-300/60">
                                                    {{ $connection->is_connected ? 'Disconnect' : 'Reconnect' }}
                                                </button>
                                            </form>

                                            <form method="POST" action="{{ route('web.platform-connections.sync', ['platformConnection' => $connection], false) }}" data-toast-loading="Manual sync started for {{ $connection->account_name ?: 'this platform connection' }}." data-toast-loading-title="Starting sync">
                                                @csrf
                                                <button type="submit" @disabled(! $hasLinkedCampaigns) title="{{ $hasLinkedCampaigns ? 'Run a manual sync' : 'No linked campaign-platforms to sync' }}" class="rounded-md border border-sky-700 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-sky-200 hover:border-sky-400 disabled:cursor-not-allowed disabled:opacity-40">
                                                    Force Sync
                                                </button>
                                            </form>

                                            <form method="POST" action="{{ route('web.platform-connections.destroy', ['platformConnection' => $connection], false) }}" onsubmit="return confirm('Delete this platform connection?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-md border border-rose-700 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-rose-200 hover:border-rose-400">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr class="border-t border-slate-800/80 text-slate-300">
                                    <td colspan="6" class="px-5 py-4">{{ $platform->name }}: no connection configured yet.</td>
                                </tr>
                            @endforelse
                        @empty
                            <tr class="border-t border-slate-800/80 text-slate-300">
                                <td colspan="6" class="px-5 py-4">No active platform available.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </section>
@endsection
