@extends('layouts.app')

@section('title', 'New Campaign | Havas Media Buying Platform')

@section('content')
    <section class="space-y-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-orange-300">Campaigns</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">Create Campaign</h1>
            <p class="mt-2 text-slate-300">Create a local campaign to map and sync platform data.</p>
        </div>

        <form method="POST" action="{{ route('web.campaigns.store') }}" class="space-y-5 rounded-xl border border-slate-800 bg-slate-900/80 p-6">
            @csrf

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="client_id" class="mb-1 block text-sm text-slate-300">Client <span class="text-rose-400">*</span></label>
                    <select id="client_id" name="client_id" required class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                        <option value="">Select client...</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" @selected((int) old('client_id') === $client->id)>{{ $client->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="objective" class="mb-1 block text-sm text-slate-300">Objective <span class="text-rose-400">*</span></label>
                    <select id="objective" name="objective" required class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                        <option value="">Select objective...</option>
                        @foreach (\App\Enums\CampaignObjective::cases() as $objective)
                            <option value="{{ $objective->value }}" @selected(old('objective') === $objective->value)>
                                {{ ucfirst(str_replace('_', ' ', $objective->value)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <label for="name" class="mb-1 block text-sm text-slate-300">Campaign name <span class="text-rose-400">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required maxlength="200"
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                </div>

                <div>
                    <label for="start_date" class="mb-1 block text-sm text-slate-300">Start date <span class="text-rose-400">*</span></label>
                    <input type="date" id="start_date" name="start_date" value="{{ old('start_date') }}" required
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                </div>

                <div>
                    <label for="end_date" class="mb-1 block text-sm text-slate-300">End date <span class="text-rose-400">*</span></label>
                    <input type="date" id="end_date" name="end_date" value="{{ old('end_date') }}" required
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                </div>

                <div>
                    <label for="total_budget" class="mb-1 block text-sm text-slate-300">Total budget <span class="text-rose-400">*</span></label>
                    <input type="number" id="total_budget" name="total_budget" value="{{ old('total_budget') }}" min="0" step="0.01" required
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                </div>

                <div>
                    <label for="currency" class="mb-1 block text-sm text-slate-300">Currency</label>
                    <input type="text" id="currency" name="currency" value="{{ old('currency', 'MAD') }}" maxlength="10"
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                </div>

                <div class="sm:col-span-2">
                    <label for="internal_notes" class="mb-1 block text-sm text-slate-300">Internal notes</label>
                    <textarea id="internal_notes" name="internal_notes" rows="4"
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">{{ old('internal_notes') }}</textarea>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="rounded-md bg-orange-500 px-5 py-2 text-sm font-semibold text-slate-950 hover:bg-orange-400">
                    Create Campaign
                </button>
                <a href="{{ route('web.campaigns.index') }}" class="rounded-md border border-slate-700 px-4 py-2 text-sm hover:border-orange-300/60">
                    Cancel
                </a>
            </div>
        </form>
    </section>
@endsection
