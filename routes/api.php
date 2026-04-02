<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BriefController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\CampaignPlatformController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PlatformController;
use App\Http\Controllers\Api\PlatformConnectionController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Internal\CampaignAiController;
use App\Http\Controllers\Internal\ReportPlatformSectionController;
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
        Route::patch(
            '/report-platform-sections/{reportPlatformSection}/ai-comments',
            [ReportPlatformSectionController::class, 'updateAiComments']
        );
        Route::get(
            '/report-platform-sections/{reportPlatformSection}/ai-context',
            [ReportPlatformSectionController::class, 'showAiContext']
        );
        Route::get('/campaigns/{campaign}/ai-context', [CampaignAiController::class, 'showAiContext']);
        Route::patch('/campaigns/{campaign}/ai-comments', [CampaignAiController::class, 'updateAiComments']);
        Route::patch('/platform-connections/{id}/sync-status', [SnapshotController::class, 'updateSyncStatus']);
        Route::get('/platform-connections/{id}/credentials', [SnapshotController::class, 'credentials']);
        Route::post('/platform-connections/{id}/refresh-token', [SnapshotController::class, 'refreshToken']);
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
        Route::post(
            '/platform-connections/{platformConnection}/test-health',
            [PlatformConnectionController::class, 'testHealth']
        )->name('platform-connections.test-health');
        Route::apiResource('platform-connections', PlatformConnectionController::class);
        Route::apiResource('clients', ClientController::class);
        Route::apiResource('campaigns', CampaignController::class);
        Route::apiResource('campaign-platforms', CampaignPlatformController::class);
        Route::apiResource('briefs', BriefController::class);
        Route::apiResource('reports', ReportController::class);
        Route::patch(
            '/report-platform-sections/{reportPlatformSection}/ai-comments',
            [ReportController::class, 'updatePlatformSectionAiComments']
        )->name('report-platform-sections.ai-comments.update');
        Route::post(
            '/reports/{report}/ai-comments/regenerate',
            [ReportController::class, 'regenerateAiComments']
        )->name('reports.ai-comments.regenerate');

        Route::get('/campaigns/{id}/dashboard', [CampaignController::class, 'dashboard']);
        Route::get('/campaigns/{id}/ads', [CampaignController::class, 'ads']);
        Route::get('/campaigns/{id}/ad-sets', [CampaignController::class, 'adSets']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'read']);
        Route::patch('/notifications/{notification}/dismiss', [NotificationController::class, 'dismiss']);
    });
