<?php

namespace App\Http\Middleware;

use Closure;
use BackedEnum;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();

        $roleValue = $user?->role instanceof BackedEnum ? $user->role->value : $user?->role;

        if (!$user || !in_array($roleValue, $roles, true)) {
            abort(403, 'Insufficient permissions');
        }

        return $next($request);
    }
}
