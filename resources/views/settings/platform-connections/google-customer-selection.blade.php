@extends('layouts.app')

@section('title', 'Select Google Ads Customer | Havas Media Buying Platform')

@section('content')
    <section class="mx-auto max-w-3xl space-y-6">
        <div class="rounded-xl border border-slate-800 bg-slate-900/80 p-6">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-300">Google Ads OAuth</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">Choose the customer ID to link</h1>
            <p class="mt-3 text-sm leading-6 text-slate-300">
                Several Google Ads customer IDs are available for this account. Select the one that matches the Ads account you want to sync.
            </p>

            <form method="POST" action="{{ route('web.platform-connections.oauth.google.confirm', ['platform' => $platform], false) }}" class="mt-6 space-y-3">
                @csrf

                @foreach ($customers as $customer)
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-800 bg-slate-950/50 p-4 text-sm text-slate-200 hover:border-emerald-400/50">
                        <input type="radio" name="account_id" value="{{ $customer['account_id'] }}" class="mt-1 rounded border-slate-600 bg-slate-900 text-emerald-500 focus:ring-emerald-500" @checked($loop->first)>
                        <span>
                            <span class="block font-semibold text-white">Customer ID {{ $customer['account_id'] }}</span>
                            <span class="mt-1 block text-xs uppercase tracking-[0.2em] text-slate-400">{{ $customer['account_name'] }}</span>
                        </span>
                    </label>
                @endforeach

                <div class="flex flex-wrap gap-3 pt-2">
                    <button type="submit" class="rounded-md border border-emerald-300/60 px-4 py-2 text-sm font-semibold text-emerald-200 hover:bg-emerald-400/10">
                        Link selected customer
                    </button>
                    <a href="{{ route('web.platform-connections.index', [], false) }}" class="rounded-md border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-300 hover:border-slate-500">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </section>
@endsection
