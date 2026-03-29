<?php

namespace App\Console\Commands;

use App\Models\Notification;
use Illuminate\Console\Command;

class CleanupNotifications extends Command
{
    protected $signature = 'notifications:cleanup {--days=90}';
    protected $description = 'Delete old dismissed notifications';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $count = Notification::where('is_dismissed', true)
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $expired = Notification::whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->delete();

        $this->info("Deleted {$count} dismissed + {$expired} expired notifications.");

        return self::SUCCESS;
    }
}
