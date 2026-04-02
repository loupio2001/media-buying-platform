<div x-data="notificationBell()" @keydown.escape.window="closePanel()" x-ref="root" class="relative">
    <button @click.stop="togglePanel()" class="relative rounded-md border border-slate-700 px-3 py-1.5 hover:border-orange-300/60">
        🔔
        <span x-show="unreadCount > 0" x-text="unreadCount"
            class="absolute -right-1.5 -top-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-orange-500 text-[10px] font-bold text-slate-950">
        </span>
    </button>

    <div x-show="open" x-cloak @click.stop
        class="absolute right-0 top-full z-50 mt-2 w-80 rounded-xl border border-slate-700 bg-slate-900 shadow-xl">
        <div class="flex items-center justify-between px-4 py-3 border-b border-slate-800">
            <p class="text-sm font-semibold text-slate-200">Notifications</p>
            <button @click="markAllRead()" class="text-xs text-slate-400 hover:text-orange-300">Mark all read</button>
        </div>

        <ul class="max-h-64 overflow-y-auto divide-y divide-slate-800">
            <template x-for="notif in notifications" :key="notif.id">
                <li class="px-4 py-3 hover:bg-slate-800/40">
                    <p class="text-sm font-medium text-slate-100" x-text="notif.title"></p>
                    <p class="mt-0.5 text-xs text-slate-400" x-text="notif.message"></p>
                    <div class="mt-1 flex items-center gap-2">
                        <span class="text-xs text-slate-500" x-text="notif.type?.replace('_', ' ')"></span>
                    </div>
                </li>
            </template>
            <li x-show="notifications.length === 0" class="px-4 py-4 text-sm text-center text-slate-500">
                No unread notifications
            </li>
        </ul>

        <div class="border-t border-slate-800 px-4 py-2">
            <a href="{{ route('web.notifications.index') }}" @click="closePanel()" class="text-xs text-orange-300 hover:text-orange-200">
                View all notifications →
            </a>
        </div>
    </div>
</div>

<script>
    function notificationBell() {
        return {
            open: false,
            unreadCount: {{ (int) (auth()->user()?->unreadNotificationCount() ?? 0) }},
            notifications: [],
            loading: false,
            loaded: false,
            togglePanel() {
                if (!this.open && !this.loaded) {
                    this.loadNotifications();
                }
                this.open = !this.open;
            },
            closePanel() {
                this.open = false;
            },
            async loadNotifications() {
                if (this.loading || this.loaded) {
                    return;
                }

                this.loading = true;

                try {
                    const res = await fetch('/api/notifications?per_page=5', {
                        headers: { 'Accept': 'application/json' },
                    });
                    if (res.ok) {
                        const json = await res.json();
                        this.notifications = json.data?.data ?? json.data ?? [];
                        this.unreadCount = json.meta?.unread_count ?? this.notifications.filter(n => !n.is_read).length;
                    }
                } catch (e) {
                } finally {
                    this.loaded = true;
                    this.loading = false;
                }
            },
            async fetchNotifications() {
                try {
                    const res = await fetch('/api/notifications?per_page=5', {
                        headers: { 'Accept': 'application/json' },
                    });
                    if (res.ok) {
                        const json = await res.json();
                        this.notifications = json.data?.data ?? json.data ?? [];
                        this.unreadCount = json.meta?.unread_count ?? this.notifications.filter(n => !n.is_read).length;
                    }
                } catch (e) {}
            },
            async markAllRead() {
                try {
                    await fetch('/api/notifications/read-all', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                    });
                    this.unreadCount = 0;
                    this.notifications = this.notifications.map(n => ({ ...n, is_read: true }));
                    this.closePanel();
                } catch (e) {}
            },
        };
    }
</script>
