@extends('layouts.app')

@section('title', 'New Brief | Havas Media Buying Platform')

@section('content')
    <section class="space-y-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-orange-300">Briefs</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">Create Brief</h1>
        </div>

        <form method="POST" action="{{ route('web.briefs.store') }}" class="space-y-5 rounded-xl border border-slate-800 bg-slate-900/80 p-6">
            @csrf

            <div>
                <label for="campaign_id" class="mb-1 block text-sm text-slate-300">Campaign</label>
                <select id="campaign_id" name="campaign_id" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                    <option value="">Select campaign…</option>
                    @foreach ($campaigns as $campaign)
                        <option value="{{ $campaign->id }}" @selected(old('campaign_id') == $campaign->id)>
                            {{ $campaign->name }}{{ $campaign->client ? ' — ' . $campaign->client->name : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="objective" class="mb-1 block text-sm text-slate-300">Objective</label>
                <textarea id="objective" name="objective" rows="3"
                    class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none"
                    placeholder="Campaign objective, target audience, KPIs…">{{ old('objective') }}</textarea>
            </div>

            <div>
                <label for="target_audience" class="mb-1 block text-sm text-slate-300">Target Audience</label>
                <textarea id="target_audience" name="target_audience" rows="2"
                    class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none"
                    placeholder="Age, gender, interests, geo…">{{ old('target_audience') }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="budget_total" class="mb-1 block text-sm text-slate-300">Total Budget</label>
                    <input type="number" id="budget_total" name="budget_total" value="{{ old('budget_total') }}" min="0" step="0.01"
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                </div>
                <div></div>
                <div>
                    <label for="flight_start" class="mb-1 block text-sm text-slate-300">Flight Start</label>
                    <input type="date" id="flight_start" name="flight_start" value="{{ old('flight_start') }}"
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                </div>
                <div>
                    <label for="flight_end" class="mb-1 block text-sm text-slate-300">Flight End</label>
                    <input type="date" id="flight_end" name="flight_end" value="{{ old('flight_end') }}"
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="rounded-md bg-orange-500 px-5 py-2 text-sm font-semibold text-slate-950 hover:bg-orange-400">
                    Create Brief
                </button>
                <a href="{{ route('web.briefs.index') }}" class="rounded-md border border-slate-700 px-4 py-2 text-sm hover:border-orange-300/60">
                    Cancel
                </a>
            </div>
        </form>
    </section>
@endsection
