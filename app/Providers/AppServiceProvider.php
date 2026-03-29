<?php

namespace App\Providers;

use App\Events\SnapshotCreated;
use App\Listeners\CheckBenchmarks;
use App\Listeners\CheckPacing;
use App\Listeners\UpdateSyncTimestamp;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(SnapshotCreated::class, CheckBenchmarks::class);
        Event::listen(SnapshotCreated::class, CheckPacing::class);
        Event::listen(SnapshotCreated::class, UpdateSyncTimestamp::class);
    }
}
