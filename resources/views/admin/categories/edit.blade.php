@extends('layouts.app')

@section('title', 'Edit Benchmarks | Admin | Havas Media Buying Platform')

@section('content')
    <section class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-orange-300">Admin / Categories</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">{{ $category->name }} Benchmarks</h1>
                <p class="mt-1 text-slate-400">Set min/max benchmark values per metric and platform.</p>
            </div>
            <a href="{{ route('web.admin.categories.index') }}" class="rounded-md border border-slate-700 px-4 py-2 text-sm hover:border-orange-300/60">
                ← Back
            </a>
        </div>

        <form method="POST" action="{{ route('web.admin.categories.update', $category) }}">
            @csrf
            @method('PATCH')

            @php
                $metrics = ['ctr', 'cpm', 'cpc', 'cpa', 'cpl', 'vtr', 'frequency'];
            @endphp

            <div class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900/80">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-950/40 text-slate-400">
                            <tr>
                                <th class="px-5 py-3 font-medium">Metric</th>
                                @foreach ($platforms as $platform)
                                    <th class="px-5 py-3 font-medium text-center" colspan="2">
                                        {{ $platform->name }}
                                        <div class="flex gap-3 justify-center mt-0.5">
                                            <span class="text-xs text-slate-500">Min</span>
                                            <span class="text-xs text-slate-500">Max</span>
                                        </div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($metrics as $metric)
                                <tr class="border-t border-slate-800/80">
                                    <td class="px-5 py-3 font-semibold text-orange-300 uppercase text-xs tracking-wider">
                                        {{ strtoupper($metric) }}
                                    </td>
                                    @foreach ($platforms as $platform)
                                        @php
                                            $benchmark = $benchmarks[$platform->id][$metric] ?? null;
                                        @endphp
                                        <td class="px-2 py-2">
                                            <input type="number" step="0.01"
                                                name="benchmarks[{{ $platform->id }}][{{ $metric }}][min]"
                                                value="{{ old("benchmarks.{$platform->id}.{$metric}.min", $benchmark?->min_value) }}"
                                                placeholder="Min"
                                                class="w-20 rounded border border-slate-700 bg-slate-950 px-2 py-1 text-slate-100 text-xs focus:border-orange-300 focus:outline-none">
                                        </td>
                                        <td class="px-2 py-2">
                                            <input type="number" step="0.01"
                                                name="benchmarks[{{ $platform->id }}][{{ $metric }}][max]"
                                                value="{{ old("benchmarks.{$platform->id}.{$metric}.max", $benchmark?->max_value) }}"
                                                placeholder="Max"
                                                class="w-20 rounded border border-slate-700 bg-slate-950 px-2 py-1 text-slate-100 text-xs focus:border-orange-300 focus:outline-none">
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-slate-800 px-5 py-4 flex items-center gap-3">
                    <button type="submit" class="rounded-md bg-orange-500 px-5 py-2 text-sm font-semibold text-slate-950 hover:bg-orange-400">
                        Save Benchmarks
                    </button>
                    <a href="{{ route('web.admin.categories.index') }}" class="rounded-md border border-slate-700 px-4 py-2 text-sm hover:border-orange-300/60">
                        Cancel
                    </a>
                </div>
            </div>
        </form>
    </section>
@endsection
