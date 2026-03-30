<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('partitions:create-monthly --past-months=2 --future-months=4')->dailyAt('00:10');
Schedule::command('reports:generate-ai-comments --pending')->dailyAt('01:30');
Schedule::command('notifications:cleanup')->dailyAt('03:00');
Schedule::command('snapshots:cleanup-raw-responses')->weeklyOn(0, '04:00');
Schedule::command('activity-log:archive')->monthlyOn(1, '05:00');

