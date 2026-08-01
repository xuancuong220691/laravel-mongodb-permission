<?php

namespace CuongNX\LaravelMongoPermission\Filament\Traits;

use CuongNX\LaravelMongoPermission\Filament\MongoShieldPlugin;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

trait HasPageShield
{
    /**
     * Delegates page access to Gate::allows('page.{slug}').
     * Super-admin bypasses automatically via Gate::before() registered in MongoShieldPlugin.
     */
    public static function canAccess(): bool
    {
        try {
            $plugin = MongoShieldPlugin::get();
            $separator = $plugin->getSeparator();
        } catch (\Throwable) {
            $separator = '.';
        }

        $slug = Str::kebab(class_basename(static::class));

        return Gate::allows('page' . $separator . $slug);
    }
}
