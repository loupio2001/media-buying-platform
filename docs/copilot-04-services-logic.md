# Copilot Instructions — Part 4: Services, Observers & Business Logic

**Project:** Havas Media Buying Platform  
**Reference:** `havas-data-model-v3.1.md`  
**This file covers:** Observers, services, event/listener architecture, scheduled commands

---

## Event/Listener Architecture

When the Python scheduler pushes snapshot data to Laravel, the following chain executes:

```
POST /internal/v1/snapshots
    → SnapshotController validates + upserts AdSnapshot
    → Fires SnapshotCreated event
        → Listener: CheckBenchmarks (compares vs category_benchmarks + kpi_targets)
        → Listener: CheckPacing (compares spend vs budget trajectory)
        → Listener: UpdateSyncTimestamp (updates campaign_platform.last_sync_at)
```

---

## 1. Event: `SnapshotCreated`

**File:** `app/Events/SnapshotCreated.php`

```php
<?php

namespace App\Events;

use App\Models\AdSnapshot;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SnapshotCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public AdSnapshot $snapshot,
        public int $campaignPlatformId,
    ) {}
}
```

---

## 2. Register Events

**File:** `app/Providers/EventServiceProvider.php` (or in `AppServiceProvider` for Laravel 11)

```php
use App\Events\SnapshotCreated;
use App\Listeners\{CheckBenchmarks, CheckPacing, UpdateSyncTimestamp};

// In boot() or $listen array:
protected $listen = [
    SnapshotCreated::class => [
        CheckBenchmarks::class,
        CheckPacing::class,
        UpdateSyncTimestamp::class,
    ],
];
```

---

## 3. Service: `BenchmarkChecker`

**File:** `app/Services/BenchmarkChecker.php`

Compares platform-level metrics against BOTH category benchmarks AND campaign kpi_targets.

```php
<?php

namespace App\Services;

use App\Models\{Campaign, CampaignPlatform, CategoryBenchmark};
use Illuminate\Support\Facades\DB;

class BenchmarkChecker
{
    /**
     * Metrics we check and how they behave:
     * 'lower_is_better' = true → cpm, cpc, cpa, cpl (below min = GOOD, above max = BAD)
     * 'lower_is_better' = false → ctr, vtr (below min = BAD, above max = GOOD)
     */
    private const METRIC_DIRECTION = [
        'ctr'       => false, // higher is better
        'vtr'       => false,
        'cpm'       => true,  // lower is better
        'cpc'       => true,
        'cpa'       => true,
        'cpl'       => true,
        'frequency' => true,  // lower is better (over-frequency wastes budget)
    ];

    /**
     * Check a campaign platform's current performance against benchmarks and targets.
     * Returns array of flags.
     */
    public function check(CampaignPlatform $cp): array
    {
        $flags = [];

        // Get current totals from view
        $totals = DB::table('v_campaign_platform_totals')
            ->where('campaign_platform_id', $cp->id)
            ->first();

        if (!$totals) return [];

        // Get campaign for kpi_targets and category
        $campaign = $cp->campaign()->with('client.category')->first();
        $categoryId = $campaign->client->category_id;
        $kpiTargets = $campaign->kpi_targets ?? [];

        // Get benchmarks for this category + platform
        $benchmarks = CategoryBenchmark::where('category_id', $categoryId)
            ->where('platform_id', $cp->platform_id)
            ->get()
            ->keyBy('metric');

        // Map view columns to metric names
        $metricMapping = [
            'ctr'       => 'calc_ctr',
            'cpm'       => 'calc_cpm',
            'cpc'       => 'calc_cpc',
            'cpa'       => 'calc_cpa',
            'cpl'       => 'calc_cpl',
            'vtr'       => 'calc_vtr',
            'frequency' => 'calc_frequency',
        ];

        foreach ($metricMapping as $metric => $viewColumn) {
            $currentValue = $totals->$viewColumn ?? null;
            if ($currentValue === null) continue;

            $lowerIsBetter = self::METRIC_DIRECTION[$metric] ?? false;
            $benchmark = $benchmarks->get($metric);
            $target = $kpiTargets[$metric] ?? null;

            // Check against category benchmark
            if ($benchmark) {
                $flag = $this->evaluateBenchmark(
                    $metric, (float) $currentValue, $benchmark, $lowerIsBetter
                );
                if ($flag) $flags[] = $flag;
            }

            // Check against campaign kpi_target
            if ($target && isset($target['target'])) {
                $flag = $this->evaluateTarget(
                    $metric, (float) $currentValue, (float) $target['target'], $lowerIsBetter
                );
                if ($flag) $flags[] = $flag;
            }
        }

        return $flags;
    }

    private function evaluateBenchmark(
        string $metric, float $value, CategoryBenchmark $benchmark, bool $lowerIsBetter
    ): ?array {
        $status = $benchmark->evaluate($value);
        if ($status === 'within') return null;

        // Determine if this is good or bad
        $isUnderperforming = ($status === 'below' && !$lowerIsBetter)
                          || ($status === 'above' && $lowerIsBetter);

        if (!$isUnderperforming) return null; // Overperforming — no alert needed

        $deviation = $benchmark->deviationPct($value);
        $severity = abs($deviation) > 30 ? 'critical' : 'warning';

        return [
            'source'        => 'benchmark',
            'metric'        => $metric,
            'value'         => $value,
            'benchmark_min' => (float) $benchmark->min_value,
            'benchmark_max' => (float) $benchmark->max_value,
            'status'        => 'underperforming',
            'severity'      => $severity,
            'deviation_pct' => $deviation,
        ];
    }

    private function evaluateTarget(
        string $metric, float $value, float $target, bool $lowerIsBetter
    ): ?array {
        $isUnderperforming = $lowerIsBetter ? ($value > $target) : ($value < $target);

        if (!$isUnderperforming) return null;

        $deviation = round(($value - $target) / $target * 100, 2);
        $severity = abs($deviation) > 30 ? 'critical' : 'warning';

        return [
            'source'        => 'kpi_target',
            'metric'        => $metric,
            'value'         => $value,
            'target'        => $target,
            'status'        => 'underperforming',
            'severity'      => $severity,
            'deviation_pct' => $deviation,
        ];
    }
}
```

---

## 4. Service: `PacingChecker`

**File:** `app/Services/PacingChecker.php`

```php
<?php

namespace App\Services;

use App\Models\CampaignPlatform;
use Illuminate\Support\Facades\DB;

class PacingChecker
{
    /**
     * Check if spend is pacing correctly based on campaign's pacing_strategy.
     * Returns null if OK, or a flag array if overspending/underspending.
     */
    public function check(CampaignPlatform $cp): ?array
    {
        $totals = DB::table('v_campaign_platform_totals')
            ->where('campaign_platform_id', $cp->id)
            ->first();

        if (!$totals || $totals->budget <= 0) return null;

        $campaign = $cp->campaign;
        $totalDays = max(1, $campaign->totalDays());
        $daysElapsed = max(1, $campaign->daysElapsed());
        $daysRemaining = $campaign->daysRemaining();

        $spentPct = (float) $totals->budget_pct_used;
        $timePct = round($daysElapsed / $totalDays * 100, 2);

        // Expected spend % based on pacing strategy
        $expectedPct = match ($campaign->pacing_strategy->value) {
            'even'         => $timePct,
            'front_loaded' => min(100, $timePct * 1.4), // Expect 40% more early
            'back_loaded'  => max(0, $timePct * 0.6),   // Expect 40% less early
            'custom'       => $timePct, // Fall back to even
        };

        $deviation = $spentPct - $expectedPct;

        // Only flag overspending
        if ($deviation <= 15) return null;

        $projectedOverspend = round(
            ($totals->total_spend / $daysElapsed * $totalDays) - $totals->budget, 2
        );

        return [
            'campaign_platform_id' => $cp->id,
            'campaign_id'          => $campaign->id,
            'platform_id'          => $cp->platform_id,
            'budget'               => (float) $totals->budget,
            'spent'                => (float) $totals->total_spend,
            'budget_pct_used'      => $spentPct,
            'expected_pct'         => $expectedPct,
            'deviation'            => round($deviation, 2),
            'days_elapsed'         => $daysElapsed,
            'days_remaining'       => $daysRemaining,
            'projected_overspend'  => max(0, $projectedOverspend),
            'pacing_strategy'      => $campaign->pacing_strategy->value,
            'severity'             => $deviation > 30 ? 'critical' : 'warning',
        ];
    }
}
```

---

## 5. Service: `NotificationService`

**File:** `app/Services/NotificationService.php`

```php
<?php

namespace App\Services;

use App\Models\{Notification, User};

class NotificationService
{
    /**
     * Create a notification for all users who want this type.
     */
    public function notifyAll(
        string $type,
        string $severity,
        string $title,
        string $message,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $meta = null,
        ?string $actionUrl = null,
        bool $isActionable = false,
    ): void {
        $users = User::where('is_active', true)->get();

        foreach ($users as $user) {
            if (!$user->wantsNotification($type)) continue;

            Notification::create([
                'user_id'       => $user->id,
                'type'          => $type,
                'severity'      => $severity,
                'title'         => $title,
                'message'       => $message,
                'entity_type'   => $entityType,
                'entity_id'     => $entityId,
                'meta'          => $meta,
                'action_url'    => $actionUrl,
                'is_actionable' => $isActionable,
                'created_at'    => now(),
            ]);
        }
    }

    /**
     * Create a notification for a specific user.
     */
    public function notifyUser(int $userId, string $type, string $severity, string $title, string $message, ?array $meta = null): void
    {
        Notification::create([
            'user_id'    => $userId,
            'type'       => $type,
            'severity'   => $severity,
            'title'      => $title,
            'message'    => $message,
            'meta'       => $meta,
            'created_at' => now(),
        ]);
    }
}
```

---

## 6. Listener: `CheckBenchmarks`

**File:** `app/Listeners/CheckBenchmarks.php`

```php
<?php

namespace App\Listeners;

use App\Events\SnapshotCreated;
use App\Models\CampaignPlatform;
use App\Services\{BenchmarkChecker, NotificationService};

class CheckBenchmarks
{
    public function __construct(
        private BenchmarkChecker $checker,
        private NotificationService $notifier,
    ) {}

    public function handle(SnapshotCreated $event): void
    {
        $cp = CampaignPlatform::with('campaign.client.category', 'platform')
            ->find($event->campaignPlatformId);

        if (!$cp) return;

        $flags = $this->checker->check($cp);

        foreach ($flags as $flag) {
            $platformName = $cp->platform->name;
            $campaignName = $cp->campaign->name;
            $metric = strtoupper($flag['metric']);

            $this->notifier->notifyAll(
                type: 'performance_flag',
                severity: $flag['severity'],
                title: "{$metric} underperforming on {$platformName}",
                message: "{$campaignName}: {$metric} is at {$flag['value']} " .
                         ($flag['source'] === 'benchmark'
                             ? "(benchmark: {$flag['benchmark_min']}–{$flag['benchmark_max']})"
                             : "(target: {$flag['target']})") .
                         " — {$flag['deviation_pct']}% deviation",
                entityType: 'campaigns',
                entityId: $cp->campaign_id,
                meta: array_merge($flag, [
                    'campaign_id'   => $cp->campaign_id,
                    'platform_slug' => $cp->platform->slug,
                ]),
                actionUrl: "/campaigns/{$cp->campaign_id}",
                isActionable: $flag['severity'] === 'critical',
            );
        }
    }
}
```

---

## 7. Listener: `CheckPacing`

**File:** `app/Listeners/CheckPacing.php`

```php
<?php

namespace App\Listeners;

use App\Events\SnapshotCreated;
use App\Models\CampaignPlatform;
use App\Services\{PacingChecker, NotificationService};

class CheckPacing
{
    public function __construct(
        private PacingChecker $checker,
        private NotificationService $notifier,
    ) {}

    public function handle(SnapshotCreated $event): void
    {
        $cp = CampaignPlatform::with('campaign', 'platform')
            ->find($event->campaignPlatformId);

        if (!$cp) return;

        $flag = $this->checker->check($cp);

        if (!$flag) return;

        $platformName = $cp->platform->name;
        $campaignName = $cp->campaign->name;

        $this->notifier->notifyAll(
            type: 'budget_warning',
            severity: $flag['severity'],
            title: "Budget pacing alert: {$platformName}",
            message: "{$campaignName}: {$flag['budget_pct_used']}% budget used with " .
                     "{$flag['days_remaining']} days remaining. " .
                     "Projected overspend: {$flag['projected_overspend']} MAD",
            entityType: 'campaigns',
            entityId: $cp->campaign_id,
            meta: $flag,
            actionUrl: "/campaigns/{$cp->campaign_id}",
            isActionable: true,
        );
    }
}
```

---

## 8. Listener: `UpdateSyncTimestamp`

**File:** `app/Listeners/UpdateSyncTimestamp.php`

```php
<?php

namespace App\Listeners;

use App\Events\SnapshotCreated;
use App\Models\CampaignPlatform;

class UpdateSyncTimestamp
{
    public function handle(SnapshotCreated $event): void
    {
        CampaignPlatform::where('id', $event->campaignPlatformId)
            ->update(['last_sync_at' => now()]);
    }
}
```

---

## 9. Internal API Controller: `SnapshotController`

**File:** `app/Http/Controllers/Internal/SnapshotController.php`

```php
<?php

namespace App\Http\Controllers\Internal;

use App\Events\SnapshotCreated;
use App\Http\Controllers\Controller;
use App\Models\{Ad, AdSet, AdSnapshot, CampaignPlatform};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SnapshotController extends Controller
{
    /**
     * Upsert a single ad snapshot.
     * Called by Python scheduler for each ad data point.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ad_id'          => 'required|exists:ads,id',
            'snapshot_date'  => 'required|date',
            'granularity'    => 'required|in:daily,cumulative',
            'impressions'    => 'integer|min:0',
            'clicks'         => 'integer|min:0',
            'spend'          => 'numeric|min:0',
            'source'         => 'in:api,manual',
            // All other numeric fields are optional
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $data['pulled_at'] = now();

        // UPSERT: ON CONFLICT (ad_id, snapshot_date, granularity) DO UPDATE
        $snapshot = AdSnapshot::updateOrCreate(
            [
                'ad_id'         => $data['ad_id'],
                'snapshot_date' => $data['snapshot_date'],
                'granularity'   => $data['granularity'],
            ],
            collect($data)->except(['ad_id', 'snapshot_date', 'granularity'])->toArray()
        );

        // Get campaign_platform_id for event
        $ad = Ad::with('adSet.campaignPlatform')->find($data['ad_id']);
        $cpId = $ad->adSet->campaignPlatform->id;

        // Fire event for benchmark/pacing checks
        // Only fire on daily granularity to avoid double-checking
        if ($data['granularity'] === 'daily') {
            SnapshotCreated::dispatch($snapshot, $cpId);
        }

        return response()->json(['id' => $snapshot->id, 'status' => 'ok'], 200);
    }

    /**
     * Batch upsert — for efficiency, Python sends multiple snapshots at once.
     */
    public function storeBatch(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'snapshots'                 => 'required|array|min:1|max:500',
            'snapshots.*.ad_id'         => 'required|exists:ads,id',
            'snapshots.*.snapshot_date' => 'required|date',
            'snapshots.*.granularity'   => 'required|in:daily,cumulative',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $results = [];
        $campaignPlatformIds = collect();

        DB::transaction(function () use ($request, &$results, &$campaignPlatformIds) {
            foreach ($request->input('snapshots') as $data) {
                $data['pulled_at'] = now();

                $snapshot = AdSnapshot::updateOrCreate(
                    [
                        'ad_id'         => $data['ad_id'],
                        'snapshot_date' => $data['snapshot_date'],
                        'granularity'   => $data['granularity'],
                    ],
                    collect($data)->except(['ad_id', 'snapshot_date', 'granularity'])->toArray()
                );

                $results[] = $snapshot->id;

                if ($data['granularity'] === 'daily') {
                    $ad = Ad::with('adSet.campaignPlatform')->find($data['ad_id']);
                    $campaignPlatformIds->push($ad->adSet->campaignPlatform->id);
                }
            }
        });

        // Fire events per unique campaign_platform (not per snapshot)
        foreach ($campaignPlatformIds->unique() as $cpId) {
            // Use the last snapshot as representative
            $lastSnapshot = AdSnapshot::find(end($results));
            if ($lastSnapshot) {
                SnapshotCreated::dispatch($lastSnapshot, $cpId);
            }
        }

        return response()->json([
            'count' => count($results),
            'ids'   => $results,
            'status' => 'ok',
        ], 200);
    }

    /**
     * Upsert ad set — Python creates/updates ad sets during data pull.
     */
    public function upsertAdSet(Request $request): JsonResponse
    {
        $data = $request->validate([
            'campaign_platform_id' => 'required|exists:campaign_platforms,id',
            'external_id'          => 'required|string|max:100',
            'name'                 => 'required|string|max:255',
            'status'               => 'in:active,paused,deleted,archived',
            'objective'            => 'nullable|string|max:100',
            'targeting_summary'    => 'nullable|string',
            'budget'               => 'nullable|numeric',
            'bid_strategy'         => 'nullable|string|max:100',
        ]);

        $adSet = AdSet::updateOrCreate(
            [
                'campaign_platform_id' => $data['campaign_platform_id'],
                'external_id'          => $data['external_id'],
            ],
            collect($data)->except(['campaign_platform_id', 'external_id'])->toArray()
        );

        return response()->json(['id' => $adSet->id, 'status' => 'ok'], 200);
    }

    /**
     * Upsert ad — Python creates/updates ads during data pull.
     */
    public function upsertAd(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ad_set_id'       => 'required|exists:ad_sets,id',
            'external_id'     => 'required|string|max:100',
            'name'            => 'required|string|max:255',
            'format'          => 'nullable|string|max:50',
            'status'          => 'in:active,paused,deleted,archived',
            'headline'        => 'nullable|string',
            'body'            => 'nullable|string',
            'cta'             => 'nullable|string|max:50',
            'destination_url' => 'nullable|string|max:500',
            'creative_url'    => 'nullable|string|max:500',
        ]);

        $ad = Ad::updateOrCreate(
            [
                'ad_set_id'   => $data['ad_set_id'],
                'external_id' => $data['external_id'],
            ],
            collect($data)->except(['ad_set_id', 'external_id'])->toArray()
        );

        return response()->json(['id' => $ad->id, 'status' => 'ok'], 200);
    }

    /**
     * Update platform connection sync status — called after pull completes.
     */
    public function updateSyncStatus(int $id, Request $request): JsonResponse
    {
        $data = $request->validate([
            'success'   => 'required|boolean',
            'error_msg' => 'nullable|string',
        ]);

        $connection = \App\Models\PlatformConnection::findOrFail($id);

        if ($data['success']) {
            $connection->recordSuccess();
        } else {
            $connection->recordError($data['error_msg'] ?? 'Unknown error');
        }

        return response()->json(['status' => 'ok'], 200);
    }
}
```

---

## 10. Scheduled Commands

### `app/Console/Commands/CleanupNotifications.php`

```php
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

        // Also delete expired notifications
        $expired = Notification::whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->delete();

        $this->info("Deleted {$count} dismissed + {$expired} expired notifications.");
        return self::SUCCESS;
    }
}
```

### `app/Console/Commands/CleanupRawResponses.php`

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupRawResponses extends Command
{
    protected $signature = 'snapshots:cleanup-raw-responses {--months=6}';
    protected $description = 'NULL out raw_response on old snapshots to save storage';

    public function handle(): int
    {
        $months = (int) $this->option('months');
        $cutoff = now()->subMonths($months)->format('Y-m-d');

        $count = DB::table('ad_snapshots')
            ->where('snapshot_date', '<', $cutoff)
            ->whereNotNull('raw_response')
            ->update(['raw_response' => null]);

        $this->info("Cleared raw_response on {$count} snapshots older than {$months} months.");
        return self::SUCCESS;
    }
}
```

---

## 11. Service: `ReportGenerator`

**File:** `app/Services/ReportGenerator.php`

Aggregates data for report creation. Called when a user initiates a report.

```php
<?php

namespace App\Services;

use App\Models\{Campaign, CampaignPlatform, Report, ReportPlatformSection};
use Illuminate\Support\Facades\DB;

class ReportGenerator
{
    /**
     * Generate a report with per-platform sections populated from snapshot data.
     * AI commentary is NOT generated here — that's done by the Python AI layer.
     */
    public function generate(Campaign $campaign, string $type, string $periodStart, string $periodEnd, int $createdBy): Report
    {
        $report = Report::create([
            'campaign_id'  => $campaign->id,
            'type'         => $type,
            'period_start' => $periodStart,
            'period_end'   => $periodEnd,
            'title'        => "{$campaign->name} — " . ucfirst($type) . " Report",
            'status'       => 'draft',
            'created_by'   => $createdBy,
        ]);

        // Create one section per active platform
        $platforms = $campaign->campaignPlatforms()->with('platform')->where('is_active', true)->get();

        foreach ($platforms as $cp) {
            $metrics = $this->aggregateMetrics($cp, $periodStart, $periodEnd);
            $benchmarkStatus = $this->evaluateOverallPerformance($cp, $metrics);

            ReportPlatformSection::create(array_merge($metrics, [
                'report_id'                => $report->id,
                'platform_id'              => $cp->platform_id,
                'budget'                   => $cp->budget,
                'performance_vs_benchmark' => $benchmarkStatus,
            ]));
        }

        return $report;
    }

    private function aggregateMetrics(CampaignPlatform $cp, string $start, string $end): array
    {
        $result = DB::table('ad_snapshots AS s')
            ->join('ads AS a', 'a.id', '=', 's.ad_id')
            ->join('ad_sets AS aset', 'aset.id', '=', 'a.ad_set_id')
            ->where('aset.campaign_platform_id', $cp->id)
            ->where('s.granularity', 'daily')
            ->whereBetween('s.snapshot_date', [$start, $end])
            ->where('a.is_tracked', true)
            ->where('aset.is_tracked', true)
            ->selectRaw("
                SUM(s.spend)::numeric AS spend,
                SUM(s.impressions) AS impressions,
                SUM(s.reach) AS reach,
                SUM(s.clicks) AS clicks,
                SUM(s.link_clicks) AS link_clicks,
                SUM(s.conversions) AS conversions,
                SUM(s.leads) AS leads,
                SUM(s.video_views) AS video_views,
                SUM(s.video_completions) AS video_completions,
                SUM(s.engagement) AS engagement,
                CASE WHEN SUM(s.impressions) > 0
                     THEN ROUND(SUM(s.clicks)::numeric / SUM(s.impressions) * 100, 4)
                     ELSE 0 END AS ctr,
                CASE WHEN SUM(s.impressions) > 0
                     THEN ROUND(SUM(s.spend) / SUM(s.impressions) * 1000, 4)
                     ELSE 0 END AS cpm,
                CASE WHEN SUM(s.clicks) > 0
                     THEN ROUND(SUM(s.spend) / SUM(s.clicks), 4)
                     ELSE 0 END AS cpc,
                CASE WHEN SUM(s.conversions) > 0
                     THEN ROUND(SUM(s.spend) / SUM(s.conversions), 4)
                     ELSE NULL END AS cpa,
                CASE WHEN SUM(s.leads) > 0
                     THEN ROUND(SUM(s.spend) / SUM(s.leads), 4)
                     ELSE NULL END AS cpl,
                CASE WHEN SUM(s.impressions) > 0 AND SUM(s.video_views) > 0
                     THEN ROUND(SUM(s.video_views)::numeric / SUM(s.impressions) * 100, 4)
                     ELSE NULL END AS vtr,
                CASE WHEN SUM(s.reach) > 0
                     THEN ROUND(SUM(s.impressions)::numeric / SUM(s.reach), 4)
                     ELSE NULL END AS frequency
            ")
            ->first();

        return (array) $result;
    }

    private function evaluateOverallPerformance(CampaignPlatform $cp, array $metrics): string
    {
        $checker = app(BenchmarkChecker::class);
        $flags = $checker->check($cp);

        $criticalCount = collect($flags)->where('severity', 'critical')->count();
        $warningCount = collect($flags)->where('severity', 'warning')->count();

        if ($criticalCount > 0) return 'underperforming';
        if ($warningCount > 2) return 'underperforming';
        if ($warningCount === 0) return 'overperforming';
        return 'on_track';
    }
}
```

---

## Next Steps

Proceed to **Part 5:** Python data collector architecture and AI integration.
