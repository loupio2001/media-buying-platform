<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('partitions:create-monthly')->monthlyOn(1, '00:00');
Schedule::command('notifications:cleanup')->dailyAt('03:00');
Schedule::command('snapshots:cleanup-raw-responses')->weeklyOn(0, '04:00');
Schedule::command('activity-log:archive')->monthlyOn(1, '05:00');

