<?php

namespace Tests\Unit;

use App\Http\Controllers\Internal\SnapshotController;
use App\Http\Requests\Internal\RefreshConnectionTokenRequest;
use App\Http\Requests\Internal\StoreBatchSnapshotsRequest;
use App\Http\Requests\Internal\StoreSnapshotRequest;
use App\Http\Requests\Internal\UpdateSyncStatusRequest;
use App\Http\Requests\Internal\UpsertAdRequest;
use App\Http\Requests\Internal\UpsertAdSetRequest;
use ReflectionMethod;
use Tests\TestCase;

class SnapshotControllerSignatureTest extends TestCase
{
    public function test_snapshot_controller_uses_form_requests_on_internal_endpoints(): void
    {
        $expected = [
            'store' => StoreSnapshotRequest::class,
            'storeBatch' => StoreBatchSnapshotsRequest::class,
            'upsertAdSet' => UpsertAdSetRequest::class,
            'upsertAd' => UpsertAdRequest::class,
            'updateSyncStatus' => UpdateSyncStatusRequest::class,
            'refreshToken' => RefreshConnectionTokenRequest::class,
        ];

        foreach ($expected as $method => $requestClass) {
            $reflection = new ReflectionMethod(SnapshotController::class, $method);
            $parameters = $reflection->getParameters();

            $this->assertNotEmpty($parameters, "Method {$method} should have at least one parameter.");

            $parameterTypes = array_map(
                static fn ($parameter) => $parameter->getType()?->getName(),
                $parameters
            );

            $this->assertContains(
                $requestClass,
                $parameterTypes,
                "Method {$method} should type-hint {$requestClass}."
            );
        }
    }
}
