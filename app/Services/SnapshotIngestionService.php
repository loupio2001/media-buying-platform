<?php

namespace App\Services;

use App\Events\SnapshotCreated;
use App\Models\Ad;
use App\Models\AdSet;
use App\Models\AdSnapshot;
use App\Models\PlatformConnection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SnapshotIngestionService
{
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

        DB::transaction(function () use ($snapshots, &$ids, &$cpIds, &$lastSnapshot) {
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
                    $cpId = $this->resolveCampaignPlatformId((int) $payload['ad_id']);
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
            'api_key' => $connection->api_key,
            'extra_credentials' => $connection->extra_credentials,
            'scopes' => $connection->scopes,
        ];
    }

    private function resolveCampaignPlatformId(int $adId): ?int
    {
        $ad = Ad::with('adSet.campaignPlatform')->find($adId);

        return $ad?->adSet?->campaignPlatform?->id;
    }

    private function normalizeSnapshotDate(mixed $value): string
    {
        return Carbon::parse($value)
            ->setTimezone((string) config('app.timezone', 'Africa/Casablanca'))
            ->toDateString();
    }
}
