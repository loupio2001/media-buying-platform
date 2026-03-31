@extends('layouts.app')

@section('title', 'New Report | Havas Media Buying Platform')

@section('content')
    <section class="space-y-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-orange-300">Reports</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">Create Report</h1>
        </div>

        @if ($errors->any())
            <div class="rounded-lg border border-rose-700/50 bg-rose-900/20 px-4 py-3 text-sm text-rose-200">
                <ul class="list-disc pl-4 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('web.reports.store') }}" class="space-y-5 rounded-xl border border-slate-800 bg-slate-900/80 p-6">
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
                <label for="type" class="mb-1 block text-sm text-slate-300">Report Type</label>
                <select id="type" name="type" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                    <option value="">Select type…</option>
                    <option value="weekly" @selected(old('type') === 'weekly')>Weekly</option>
                    <option value="monthly" @selected(old('type') === 'monthly')>Monthly</option>
                    <option value="campaign" @selected(old('type') === 'campaign')>Full Campaign</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="period_start" class="mb-1 block text-sm text-slate-300">Period Start</label>
                    <input type="date" id="period_start" name="period_start" value="{{ old('period_start') }}"
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                </div>
                <div>
                    <label for="period_end" class="mb-1 block text-sm text-slate-300">Period End</label>
                    <input type="date" id="period_end" name="period_end" value="{{ old('period_end') }}"
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="rounded-md bg-orange-500 px-5 py-2 text-sm font-semibold text-slate-950 hover:bg-orange-400">
                    Generate Report
                </button>
                <a href="{{ route('web.reports.index') }}" class="rounded-md border border-slate-700 px-4 py-2 text-sm hover:border-orange-300/60">
                    Cancel
                </a>
            </div>
        </form>
    </section>
@endsection
