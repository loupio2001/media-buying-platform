<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Http\Middleware\CheckRole;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CheckRoleMiddlewareTest extends TestCase
{
    public function test_admin_is_authorized_for_admin_manager_routes(): void
    {
        $middleware = new CheckRole();
        $request = Request::create('/api/platforms', 'GET');
        $request->setUserResolver(fn () => (object) ['role' => UserRole::Admin]);

        $nextCalled = false;

        $response = $middleware->handle(
            $request,
            function ($req) use (&$nextCalled) {
                $nextCalled = true;

                return response()->json(['ok' => true], 200);
            },
            'admin',
            'manager'
        );

        $this->assertTrue($nextCalled);
        $this->assertSame(200, $response->status());
    }

    public function test_manager_is_authorized_for_admin_manager_routes(): void
    {
        $middleware = new CheckRole();
        $request = Request::create('/api/platforms', 'GET');
        $request->setUserResolver(fn () => (object) ['role' => UserRole::Manager]);

        $nextCalled = false;

        $response = $middleware->handle(
            $request,
            function ($req) use (&$nextCalled) {
                $nextCalled = true;

                return response()->json(['ok' => true], 200);
            },
            'admin',
            'manager'
        );

        $this->assertTrue($nextCalled);
        $this->assertSame(200, $response->status());
    }

    public function test_viewer_is_denied_for_admin_manager_routes(): void
    {
        $middleware = new CheckRole();
        $request = Request::create('/api/platforms', 'GET');
        $request->setUserResolver(fn () => (object) ['role' => UserRole::Viewer]);

        try {
            $middleware->handle(
                $request,
                fn ($req) => response()->json(['ok' => true], 200),
                'admin',
                'manager'
            );

            $this->fail('Expected HttpException with status code 403.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }
}
