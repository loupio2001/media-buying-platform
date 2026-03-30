<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RouteSecurityTest extends TestCase
{
    public function test_internal_routes_are_protected_by_internal_auth_middleware(): void
    {
        $internalUris = [
            'api/internal/v1/snapshots',
            'api/internal/v1/snapshots/batch',
            'api/internal/v1/ad-sets/upsert',
            'api/internal/v1/ads/upsert',
            'api/internal/v1/report-platform-sections/{reportPlatformSection}/ai-comments',
            'api/internal/v1/report-platform-sections/{reportPlatformSection}/ai-context',
            'api/internal/v1/platform-connections/{id}/sync-status',
            'api/internal/v1/platform-connections/{id}/credentials',
            'api/internal/v1/platform-connections/{id}/refresh-token',
        ];

        foreach ($internalUris as $uri) {
            $route = collect(Route::getRoutes())->first(fn ($item) => $item->uri() === $uri);

            $this->assertNotNull($route, "Route {$uri} should exist.");
            $this->assertContains('internal.auth', $route->middleware(), "Route {$uri} must use internal.auth middleware.");
        }
    }

    public function test_external_routes_are_protected_by_sanctum_and_role_middleware(): void
    {
        $namedRoutes = [
            'platforms.index',
            'platform-connections.index',
            'platform-connections.test-health',
            'clients.index',
            'campaigns.index',
            'campaign-platforms.index',
            'briefs.index',
            'reports.index',
            'report-platform-sections.ai-comments.update',
            'reports.ai-comments.regenerate',
        ];

        foreach ($namedRoutes as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "Route {$name} should exist.");
            $this->assertContains('auth:sanctum', $route->middleware(), "Route {$name} must use auth:sanctum.");
            $this->assertContains('role:admin,manager', $route->middleware(), "Route {$name} must use role middleware.");
        }
    }
}
