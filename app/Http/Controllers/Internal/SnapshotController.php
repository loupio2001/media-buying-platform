<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\StoreBatchSnapshotsRequest;
use App\Http\Requests\Internal\StoreSnapshotRequest;
use App\Http\Requests\Internal\UpdateSyncStatusRequest;
use App\Http\Requests\Internal\UpsertAdRequest;
use App\Http\Requests\Internal\UpsertAdSetRequest;
use App\Services\SnapshotIngestionService;
use Illuminate\Http\JsonResponse;

class SnapshotController extends Controller
{
    public function __construct(private SnapshotIngestionService $service)
    {
    }

    public function store(StoreSnapshotRequest $request): JsonResponse
    {
        $result = $this->service->upsertSnapshot($request->validated());

        return response()->json([
            'data' => [
                'id' => $result['snapshot']->id,
            ],
            'meta' => [
                'status' => 'ok',
            ],
        ], 200);
    }

    public function storeBatch(StoreBatchSnapshotsRequest $request): JsonResponse
    {
        $result = $this->service->upsertBatch($request->validated('snapshots'));

        return response()->json([
            'data' => [
                'ids' => $result['ids'],
            ],
            'meta' => [
                'count' => count($result['ids']),
                'status' => 'ok',
            ],
        ], 200);
    }

    public function upsertAdSet(UpsertAdSetRequest $request): JsonResponse
    {
        $adSet = $this->service->upsertAdSet($request->validated());

        return response()->json([
            'data' => ['id' => $adSet->id],
            'meta' => ['status' => 'ok'],
        ], 200);
    }

    public function upsertAd(UpsertAdRequest $request): JsonResponse
    {
        $ad = $this->service->upsertAd($request->validated());

        return response()->json([
            'data' => ['id' => $ad->id],
            'meta' => ['status' => 'ok'],
        ], 200);
    }

    public function updateSyncStatus(int $id, UpdateSyncStatusRequest $request): JsonResponse
    {
        $this->service->updateConnectionSyncStatus(
            $id,
            (bool) $request->validated('success'),
            $request->validated('error_msg')
        );

        return response()->json([
            'data' => ['id' => $id],
            'meta' => ['status' => 'ok'],
        ], 200);
    }

    public function credentials(int $id): JsonResponse
    {
        $credentials = $this->service->getConnectionCredentials($id);

        return response()->json([
            'data' => $credentials,
            'meta' => ['status' => 'ok'],
        ], 200);
    }
}

