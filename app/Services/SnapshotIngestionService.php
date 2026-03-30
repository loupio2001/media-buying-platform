<?php

namespace App\Services;

use App\Events\SnapshotCreated;
use App\Models\Ad;
use App\Models\AdSet;
use App\Models\AdSnapshot;
use App\Models\PlatformConnection;
use App\Services\PlatformOAuth\PlatformConnectionTokenRefreshService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SnapshotIngestionService
{
    public function __construct(private PlatformConnectionTokenRefreshService $tokenRefreshService)
    {
    }

    public function upsertSnapshot(array $data): array
    {
        $data['pulled_at'] = now();
        $data['snapshot_date'] = $this->normalizeSnapshotDate($data['snapshot_date']);

        $snapshot = AdSnapshot::updateOrCreate(
            [
                'ad_id' => $data['ad_id'],
                'snapshot_date' => $data['snapshot_date'],
                'granularity' => $data['granularity'],
            ],
            collect($data)->except(['ad_id', 'snapshot_date', 'granularity'])->toArray()
        );

        $cpId = $this->resolveCampaignPlatformId((int) $data['ad_id']);
        if (($data['granularity'] ?? null) === 'daily' && $cpId) {
            SnapshotCreated::dispatch($snapshot, $cpId);
        }

        return ['snapshot' => $snapshot, 'campaign_platform_id' => $cpId];
    }

    public function upsertBatch(array $snapshots): array
    {
        $ids = [];
        $cpIds = collect();
        $lastSnapshot = null;
        $campaignPlatformIdsByAdId = $this->resolveCampaignPlatformIds(
            collect($snapshots)
                ->filter(fn (array $payload): bool => ($payload['granularity'] ?? null) === 'daily')
                ->pluck('ad_id')
                ->map(fn (mixed $adId): int => (int) $adId)
                ->unique()
                ->values()
                ->all()
        );

        DB::transaction(function () use ($snapshots, $campaignPlatformIdsByAdId, &$ids, &$cpIds, &$lastSnapshot) {
            foreach ($snapshots as $payload) {
                $payload['pulled_at'] = now();
                $payload['snapshot_date'] = $this->normalizeSnapshotDate($payload['snapshot_date']);

                $snapshot = AdSnapshot::updateOrCreate(
                    [
                        'ad_id' => $payload['ad_id'],
                        'snapshot_date' => $payload['snapshot_date'],
                        'granularity' => $payload['granularity'],
                    ],
                    collect($payload)->except(['ad_id', 'snapshot_date', 'granularity'])->toArray()
                );

                $lastSnapshot = $snapshot;
                $ids[] = $snapshot->id;

                if (($payload['granularity'] ?? null) === 'daily') {
                    $cpId = $campaignPlatformIdsByAdId[(int) $payload['ad_id']] ?? null;
                    if ($cpId) {
                        $cpIds->push($cpId);
                    }
                }
            }
        });

        if ($lastSnapshot) {
            $cpIds->unique()->each(function (int $cpId) use ($lastSnapshot): void {
                SnapshotCreated::dispatch($lastSnapshot, $cpId);
            });
        }

        return ['ids' => $ids, 'campaign_platform_ids' => $cpIds->unique()->values()];
    }

    public function upsertAdSet(array $data): AdSet
    {
        return AdSet::updateOrCreate(
            [
                'campaign_platform_id' => $data['campaign_platform_id'],
                'external_id' => $data['external_id'],
            ],
            collect($data)->except(['campaign_platform_id', 'external_id'])->toArray()
        );
    }

    public function upsertAd(array $data): Ad
    {
        return Ad::updateOrCreate(
            [
                'ad_set_id' => $data['ad_set_id'],
                'external_id' => $data['external_id'],
            ],
            collect($data)->except(['ad_set_id', 'external_id'])->toArray()
        );
    }

    public function updateConnectionSyncStatus(int $id, bool $success, ?string $errorMsg = null): PlatformConnection
    {
        $connection = PlatformConnection::findOrFail($id);

        if ($success) {
            $connection->recordSuccess();
        } else {
            $connection->recordError($errorMsg ?? 'Unknown error');
        }

        return $connection->refresh();
    }

    public function getConnectionCredentials(int $id): array
    {
        $connection = PlatformConnection::findOrFail($id);

        return [
            'id' => $connection->id,
            'account_id' => $connection->account_id,
            'auth_type' => $connection->auth_type,
            'access_token' => $connection->access_token,
            'refresh_token' => $connection->refresh_token,
            'token_expires_at' => optional($connection->token_expires_at)?->toIso8601String(),
            'api_key' => $connection->api_key,
            'extra_credentials' => $connection->extra_credentials,
            'scopes' => $connection->scopes,
        ];
    }

    public function refreshConnectionToken(int $id, bool $force = false): array
    {
        $connection = PlatformConnection::query()->with('platform')->findOrFail($id);
        $result = $this->tokenRefreshService->refreshIfNeeded($connection, $force);

        return [
            'id' => $connection->id,
            ...$result,
        ];
    }

    private function resolveCampaignPlatformId(int $adId): ?int
    {
        return $this->resolveCampaignPlatformIds([$adId])[$adId] ?? null;
    }

    /**
     * @return array<int, int>
     */
    private function resolveCampaignPlatformIds(array $adIds): array
    {
        if ($adIds === []) {
            return [];
        }

        return Ad::query()
            ->join('ad_sets', 'ad_sets.id', '=', 'ads.ad_set_id')
            ->whereIn('ads.id', $adIds)
            ->pluck('ad_sets.campaign_platform_id', 'ads.id')
            ->mapWithKeys(fn (mixed $campaignPlatformId, mixed $adId): array => [(int) $adId => (int) $campaignPlatformId])
            ->all();
    }

    private function normalizeSnapshotDate(mixed $value): string
    {
        return Carbon::parse($value)
            ->setTimezone((string) config('app.timezone', 'Africa/Casablanca'))
            ->toDateString();
    }
}
