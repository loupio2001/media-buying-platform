@extends('layouts.app')

@section('title', 'Notifications | Havas Media Buying Platform')

@section('content')
    <section class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-orange-300">Notifications</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">Notifications</h1>
            </div>
        </div>

        {{-- Type filters --}}
        <div class="flex flex-wrap gap-2 text-sm">
            <a href="{{ route('web.notifications.index') }}"
                class="rounded-full border px-3 py-1 {{ $selectedType === null ? 'border-orange-500 bg-orange-500/10 text-orange-300' : 'border-slate-700 text-slate-300 hover:border-orange-300/60' }}">
                All
            </a>
            @foreach (['performance_flag', 'budget_warning', 'report_ready'] as $type)
                <a href="{{ route('web.notifications.index', ['type' => $type]) }}"
                    class="rounded-full border px-3 py-1 {{ $selectedType === $type ? 'border-orange-500 bg-orange-500/10 text-orange-300' : 'border-slate-700 text-slate-300 hover:border-orange-300/60' }}">
                    {{ ucfirst(str_replace('_', ' ', $type)) }}
                </a>
            @endforeach
        </div>

        <section class="space-y-3">
            @forelse ($notifications as $notification)
                @php
                    $severityColor = match($notification->severity ?? 'info') {
                        'critical' => 'border-rose-700/50 bg-rose-900/10',
                        'warning' => 'border-yellow-700/50 bg-yellow-900/10',
                        default => 'border-slate-700 bg-slate-900/40',
                    };
                    $typeIcon = match($notification->type ?? '') {
                        'performance_flag' => '📉',
                        'budget_warning' => '💰',
                        'report_ready' => '📊',
                        default => '🔔',
                    };
                @endphp
                <div id="notif-{{ $notification->id }}" class="flex items-start justify-between gap-4 rounded-xl border p-4 {{ $severityColor }} {{ $notification->is_read ? 'opacity-60' : '' }}">
                    <div class="flex items-start gap-3">
                        <span class="text-xl">{{ $typeIcon }}</span>
                        <div>
                            <p class="text-sm font-semibold text-slate-100">{{ $notification->title }}</p>
                            @if ($notification->message)
                                <p class="mt-0.5 text-sm text-slate-300">{{ $notification->message }}</p>
                            @endif
                            <div class="mt-1 flex items-center gap-3 text-xs text-slate-500">
                                <span>{{ $notification->created_at?->diffForHumans() }}</span>
                                @if ($notification->severity)
                                    <span class="rounded-full border border-current px-1.5 py-0.5 {{ $notification->severity === 'critical' ? 'text-rose-400' : ($notification->severity === 'warning' ? 'text-yellow-400' : 'text-slate-400') }}">
                                        {{ ucfirst($notification->severity) }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        @if (! $notification->is_read)
                            <button onclick="markRead({{ $notification->id }})"
                                class="rounded border border-slate-700 px-2 py-1 text-xs text-slate-300 hover:border-emerald-600 hover:text-emerald-300">
                                Mark read
                            </button>
                        @endif
                        <button onclick="dismiss({{ $notification->id }})"
                            class="rounded border border-slate-700 px-2 py-1 text-xs text-slate-300 hover:border-rose-600 hover:text-rose-300">
                            Dismiss
                        </button>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-slate-800 bg-slate-900/80 p-8 text-center text-slate-400">
                    No notifications found.
                </div>
            @endforelse
        </section>

        <div>{{ $notifications->appends(request()->query())->links() }}</div>
    </section>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        async function markRead(id) {
            const res = await fetch(`/api/notifications/${id}/read`, {
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            });
            if (res.ok) document.getElementById(`notif-${id}`)?.classList.add('opacity-60');
        }

        async function dismiss(id) {
            const res = await fetch(`/api/notifications/${id}/dismiss`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            });
            if (res.ok) document.getElementById(`notif-${id}`)?.remove();
        }
    </script>
@endsection
