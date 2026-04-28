<?php

namespace CuongNX\LaravelMongoPermission\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use CuongNX\LaravelMongoPermission\Models\Role;
use CuongNX\LaravelMongoPermission\Models\Permission;
use CuongNX\LaravelMongoPermission\Middleware\RoleMiddleware;
use CuongNX\LaravelMongoPermission\Middleware\PermissionMiddleware;
use CuongNX\LaravelMongoPermission\Support\BladeDirectivesRegistrar;

class MongoPermissionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../../config/mongo-permission.php' => config_path('mongo-permission.php'),
        ], 'mongo-permission');

        // Middleware
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('role', RoleMiddleware::class);
        $router->aliasMiddleware('permission', PermissionMiddleware::class);

        // Blade directives
        BladeDirectivesRegistrar::register();

        // Cascade cleanup khi xóa Role
        Role::deleting(function (Role $role) {
            // 1. Xóa role khỏi permissions[] của các role khác (nếu có)
            //    (không áp dụng trong thiết kế hiện tại vì roles không lồng nhau)

            // 2. Xóa role_id khỏi tất cả user documents được khai báo trong config
            foreach (config('mongo-permission.models', []) as $modelClass) {
                if (!class_exists($modelClass)) continue;

                $modelClass::where('role_ids', (string) $role->_id)
                    ->each(function ($user) use ($role) {
                        $user->role_ids = array_values(
                            array_filter($user->role_ids ?? [], fn($id) => $id !== (string) $role->_id)
                        );
                        $user->saveQuietly();
                    });
            }
        });

        // Cascade cleanup khi xóa Permission
        Permission::deleting(function (Permission $permission) {
            // 1. Xóa permission name khỏi permissions[] trong tất cả roles
            Role::where('permissions', $permission->name)
                ->each(function (Role $role) use ($permission) {
                    $role->permissions = array_values(
                        array_filter($role->permissions ?? [], fn($p) => $p !== $permission->name)
                    );
                    $role->saveQuietly();
                });

            // 2. Xóa permission_id khỏi tất cả user documents
            foreach (config('mongo-permission.models', []) as $modelClass) {
                if (!class_exists($modelClass)) continue;

                $modelClass::where('permission_ids', (string) $permission->_id)
                    ->each(function ($user) use ($permission) {
                        $user->permission_ids = array_values(
                            array_filter($user->permission_ids ?? [], fn($id) => $id !== (string) $permission->_id)
                        );
                        $user->saveQuietly();
                    });
            }
        });
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/mongo-permission.php',
            'mongo-permission',
        );

        $this->commands([
            \CuongNX\LaravelMongoPermission\Console\Commands\MongoPermissionManager::class,
        ]);

        $this->app->bind(
            \CuongNX\LaravelMongoPermission\Services\Contracts\PermissionServiceInterface::class,
            \CuongNX\LaravelMongoPermission\Services\PermissionService::class,
        );
    }
}