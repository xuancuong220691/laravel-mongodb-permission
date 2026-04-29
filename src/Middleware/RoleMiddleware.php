<?php

namespace CuongNX\LaravelMongoPermission\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    public function handle($request, Closure $next, $roles, $guard = null)
    {
        $guard = $guard ?? config('auth.defaults.guard');
        $user = Auth::guard($guard)->user();

        if (!$user) {
            abort(403);
        }

        $roleList = explode('|', $roles);

        foreach ($roleList as $role) {
            if ($user->hasRole(trim($role))) {
                return $next($request);
            }
        }

        abort(403);
    }
}