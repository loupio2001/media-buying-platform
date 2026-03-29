<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BriefController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\CampaignPlatformController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PlatformController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Internal\SnapshotController;
use Illuminate\Support\Facades\Route;

// Internal API — Python → Laravel
Route::prefix('internal/v1')
    ->middleware('internal.auth')
    ->group(function () {
        Route::post('/snapshots', [SnapshotController::class, 'store']);
        Route::post('/snapshots/batch', [SnapshotController::class, 'storeBatch']);
        Route::post('/ad-sets/upsert', [SnapshotController::class, 'upsertAdSet']);
        Route::post('/ads/upsert', [SnapshotController::class, 'upsertAd']);
        Route::patch('/platform-connections/{id}/sync-status', [SnapshotController::class, 'updateSyncStatus']);
        Route::get('/platform-connections/{id}/credentials', [SnapshotController::class, 'credentials']);
    });

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::middleware(['auth:sanctum', 'role:admin,manager'])
    ->group(function () {
        Route::apiResource('platforms', PlatformController::class);
        Route::apiResource('clients', ClientController::class);
        Route::apiResource('campaigns', CampaignController::class);
        Route::apiResource('campaign-platforms', CampaignPlatformController::class);
        Route::apiResource('briefs', BriefController::class);
        Route::apiResource('reports', ReportController::class);

        Route::get('/campaigns/{id}/dashboard', [CampaignController::class, 'dashboard']);
        Route::get('/campaigns/{id}/ads', [CampaignController::class, 'ads']);
        Route::get('/campaigns/{id}/ad-sets', [CampaignController::class, 'adSets']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'read']);
        Route::patch('/notifications/{notification}/dismiss', [NotificationController::class, 'dismiss']);
    });