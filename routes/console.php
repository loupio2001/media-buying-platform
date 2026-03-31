<?php

use Illuminate\Support\Facades\Schedule;

// Daily jobs
Schedule::command('reports:generate-ai-comments --pending')->dailyAt('01:30');
Schedule::command('notifications:cleanup')->dailyAt('02:00');

// Weekly jobs (Sunday)
Schedule::command('snapshots:cleanup-raw-responses')->weeklyOn(0, '03:00');
Schedule::command('activity-log:archive')->weeklyOn(0, '04:00');

// Monthly jobs (1st of the month)
Schedule::command('partitions:create-monthly --past-months=2 --future-months=4')->monthlyOn(1, '00:05');

