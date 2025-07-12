<?php

namespace CuongNX\MongoPermission\Support;

use Illuminate\Support\Facades\Blade;

class BladeDirectivesRegistrar
{
    public static function register(): void
    {
        Blade::if('role', function ($role, $guard = null) {
            $guard = $guard ?? config('auth.defaults.guard');
            $user = auth()->guard($guard)->user();
            return $user && $user->hasRole($role);
        });

        Blade::if('permission', function ($permission, $guard = null) {
            $guard = $guard ?? config('auth.defaults.guard');
            $user = auth()->guard($guard)->user();
            return $user && $user->hasPermissionTo($permission);
        });

        Blade::if('anyrole', function ($guardOrFirstRole, ...$otherRoles) {
            if (!is_string($guardOrFirstRole) || str_contains($guardOrFirstRole, '|')) {
                // Không có guard, role là tham số đầu tiên
                $roles = array_merge([$guardOrFirstRole], $otherRoles);
                $guard = config('auth.defaults.guard');
            } else {
                $guard = $guardOrFirstRole;
                $roles = $otherRoles;
            }

            $user = auth()->guard($guard)->user();
            if (!$user) return false;

            foreach ($roles as $role) {
                if ($user->hasRole($role)) return true;
            }
            return false;
        });

        Blade::if('anypermission', function ($guardOrFirstPermission, ...$otherPermissions) {
            if (!is_string($guardOrFirstPermission) || str_contains($guardOrFirstPermission, '|')) {
                $permissions = array_merge([$guardOrFirstPermission], $otherPermissions);
                $guard = config('auth.defaults.guard');
            } else {
                $guard = $guardOrFirstPermission;
                $permissions = $otherPermissions;
            }

            $user = auth()->guard($guard)->user();
            if (!$user) return false;

            foreach ($permissions as $permission) {
                if ($user->hasPermissionTo($permission)) return true;
            }
            return false;
        });
    }
}
