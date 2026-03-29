<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class InternalApiAuth
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->header('X-Internal-Token');
        if (!$token || !hash_equals(config('services.internal_api_token'), $token)) {
            return response()->json([
                'data' => null,
                'meta' => ['error' => 'Unauthorized'],
            ], 401);
        }

        return $next($request);
    }
}