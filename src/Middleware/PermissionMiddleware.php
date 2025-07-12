<?php

namespace CuongNX\MongoPermission\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class PermissionMiddleware
{
    public function handle($request, Closure $next, $permissions, $guard = null)
    {
        $guard = $guard ?? config('auth.defaults.guard');
        $user = Auth::guard($guard)->user();

        if (!$user) {
            abort(403, 'Unauthorized. No user found in guard [' . $guard . ']');
        }

        $permissionList = explode('|', $permissions);

        foreach ($permissionList as $permission) {
            if ($user->hasPermissionTo(trim($permission))) {
                return $next($request);
            }
        }

        abort(403, 'Unauthorized. Required permission: ' . $permissions);
    }
}
