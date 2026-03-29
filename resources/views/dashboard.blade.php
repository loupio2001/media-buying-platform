@extends('layouts.app')

@section('title', 'Dashboard | Havas Media Buying Platform')

@section('content')
    <section class="space-y-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-orange-300">Tableau de bord</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">Bienvenue, {{ auth()->user()->name }}</h1>
            <p class="mt-2 max-w-2xl text-slate-300">
                Base web en place. Prochaine etape: brancher la liste des campagnes et les KPI depuis l'API interne.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <article class="rounded-xl border border-slate-800 bg-slate-900/80 p-5">
                <p class="text-sm text-slate-400">Campagnes totales</p>
                <p class="mt-3 text-2xl font-semibold text-white">{{ $summary['total_campaigns'] }}</p>
            </article>

            <article class="rounded-xl border border-slate-800 bg-slate-900/80 p-5">
                <p class="text-sm text-slate-400">Budget total (MAD)</p>
                <p class="mt-3 text-2xl font-semibold text-white">{{ number_format($summary['total_budget'], 2, '.', ' ') }}</p>
            </article>

            <article class="rounded-xl border border-slate-800 bg-slate-900/80 p-5">
                <p class="text-sm text-slate-400">Spend total (MAD)</p>
                <p class="mt-3 text-2xl font-semibold text-white">{{ number_format($summary['total_spend'], 2, '.', ' ') }}</p>
            </article>

            <article class="rounded-xl border border-slate-800 bg-slate-900/80 p-5">
                <p class="text-sm text-slate-400">CTR global (%)</p>
                <p class="mt-3 text-2xl font-semibold text-white">{{ number_format($summary['global_ctr'], 4, '.', ' ') }}</p>
            </article>
        </div>

        <p class="text-sm text-slate-400">
            Campagnes actives: <span class="font-semibold text-white">{{ $summary['active_campaigns'] }}</span>
            | Campagnes en cours: <span class="font-semibold text-white">{{ $summary['running_campaigns'] }}</span>
        </p>

        <section class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900/80">
            <div class="border-b border-slate-800 px-5 py-4">
                <h2 class="text-lg font-semibold text-white">Campagnes recentes</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-950/40 text-slate-400">
                        <tr>
                            <th class="px-5 py-3 font-medium">Nom</th>
                            <th class="px-5 py-3 font-medium">Client</th>
                            <th class="px-5 py-3 font-medium">Statut</th>
                            <th class="px-5 py-3 font-medium">Budget (MAD)</th>
                            <th class="px-5 py-3 font-medium">Periode</th>
                            <th class="px-5 py-3 font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentCampaigns as $campaign)
                            <tr class="border-t border-slate-800/80 text-slate-200">
                                <td class="px-5 py-3">
                                    <a href="{{ route('web.campaigns.show', $campaign) }}" class="font-medium text-orange-300 hover:text-orange-200">
                                        {{ $campaign->name }}
                                    </a>
                                </td>
                                <td class="px-5 py-3 text-slate-300">{{ $campaign->client?->name ?? '-' }}</td>
                                <td class="px-5 py-3">
                                    <span class="rounded-full border border-slate-700 px-2 py-0.5 text-xs uppercase tracking-wider text-slate-200">
                                        {{ ucfirst(is_object($campaign->status) ? $campaign->status->value : $campaign->status) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3">{{ number_format((float) $campaign->total_budget, 2, '.', ' ') }}</td>
                                <td class="px-5 py-3 text-slate-300">
                                    {{ $campaign->start_date?->format('Y-m-d') }} -> {{ $campaign->end_date?->format('Y-m-d') }}
                                </td>
                                <td class="px-5 py-3">
                                    <a href="{{ route('web.campaigns.show', $campaign) }}" class="rounded-md border border-slate-700 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-orange-300 hover:border-orange-300/60">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr class="border-t border-slate-800/80 text-slate-300">
                                <td colspan="6" class="px-5 py-4">Aucune campagne disponible pour le moment.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </section>
@endsection
