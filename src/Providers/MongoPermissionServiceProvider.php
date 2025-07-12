<?php

namespace CuongNX\MongoPermission\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use CuongNX\MongoPermission\Middleware\RoleMiddleware;
use CuongNX\MongoPermission\Middleware\PermissionMiddleware;
use CuongNX\MongoPermission\Support\BladeDirectivesRegistrar;

class MongoPermissionServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('role', RoleMiddleware::class);
        $router->aliasMiddleware('permission', PermissionMiddleware::class);

        BladeDirectivesRegistrar::register();
    }

    public function register()
    {
        $this->commands([
            \CuongNX\MongoPermission\Console\Commands\MongoPermissionManager::class,
        ]);

        $this->app->bind(
            \CuongNX\MongoPermission\Services\Contracts\PermissionServiceInterface::class,
            \CuongNX\MongoPermission\Services\PermissionService::class
        );
    }
}
