<?php

namespace CuongNX\LaravelMongoPermission\Support;

use Illuminate\Support\Facades\Blade;

class BladeDirectivesRegistrar
{
    public static function register(): void
    {
        // @role('role-name') hoặc @role('role-name', 'guard-name')
        Blade::if('role', function (string $role, ?string $guard = null) {
            $guard = $guard ?? config('auth.defaults.guard');
            $user = auth()->guard($guard)->user();
            return $user && $user->hasRole($role);
        });

        // @permission('permission-name') hoặc @permission('permission-name', 'guard-name')
        Blade::if('permission', function (string $permission, ?string $guard = null) {
            $guard = $guard ?? config('auth.defaults.guard');
            $user = auth()->guard($guard)->user();
            return $user && $user->hasPermissionTo($permission);
        });

        // @anyrole('role1', 'role2', ...) — dùng guard mặc định
        // Không tự detect guard để tránh nhầm lẫn.
        // Muốn chỉ định guard, hãy dùng @anyrolefor('guard', 'role1', 'role2')
        Blade::if('anyrole', function (string ...$roles) {
            $guard = config('auth.defaults.guard');
            $user = auth()->guard($guard)->user();
            if (!$user) return false;

            foreach ($roles as $role) {
                if ($user->hasRole($role)) return true;
            }
            return false;
        });

        // @anyrolefor('guard-name', 'role1', 'role2', ...)
        Blade::if('anyrolefor', function (string $guard, string ...$roles) {
            $user = auth()->guard($guard)->user();
            if (!$user) return false;

            foreach ($roles as $role) {
                if ($user->hasRole($role)) return true;
            }
            return false;
        });

        // @anypermission('perm1', 'perm2', ...) — dùng guard mặc định
        Blade::if('anypermission', function (string ...$permissions) {
            $guard = config('auth.defaults.guard');
            $user = auth()->guard($guard)->user();
            if (!$user) return false;

            foreach ($permissions as $permission) {
                if ($user->hasPermissionTo($permission)) return true;
            }
            return false;
        });

        // @anypermissionfor('guard-name', 'perm1', 'perm2', ...)
        Blade::if('anypermissionfor', function (string $guard, string ...$permissions) {
            $user = auth()->guard($guard)->user();
            if (!$user) return false;

            foreach ($permissions as $permission) {
                if ($user->hasPermissionTo($permission)) return true;
            }
            return false;
        });
    }
}