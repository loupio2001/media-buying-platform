<?php

namespace App\Services\Web;

use App\Models\Platform;
use App\Models\PlatformConnection;
use Illuminate\Support\Collection;

class PlatformConnectionSettingsService
{
    public function platformsWithConnections(): Collection
    {
        return Platform::query()
            ->active()
            ->ordered()
            ->with([
                'connections' => fn ($query) => $query
                    ->with('creator:id,name')
                    ->orderByDesc('updated_at'),
            ])
            ->get();
    }

    public function healthLabel(PlatformConnection $connection): string
    {
        if (! $connection->is_connected) {
            return 'Disconnected';
        }

        if ($connection->isTokenExpired()) {
            return 'Token expired';
        }

        if ($connection->error_count >= 5) {
            return 'Failing';
        }

        if ($connection->error_count > 0 || filled($connection->last_error)) {
            return 'Warning';
        }

        return 'Healthy';
    }
}