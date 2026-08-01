<?php

namespace CuongNX\LaravelMongoPermission\Filament\Support;

use Illuminate\Support\Str;

class ResourceDiscovery
{
    /**
     * Returns grouped permission options for all Resources in a Filament panel.
     *
     * Shape:
     *   [
     *     'Người dùng' => ['users.view' => 'Xem', 'users.create' => 'Tạo', ...],
     *     'Quản trị viên' => ['admins.view' => 'Xem', ...],
     *   ]
     */
    public static function getResourceGroups(
        string $panelId,
        array $actions,
        string $separator
    ): array {
        $resources = static::getPanelResources($panelId);
        $groups    = [];

        foreach ($resources as $resourceClass) {
            try {
                $modelClass = $resourceClass::getModel();
                $slug       = static::modelSlug($modelClass);
                $label      = $resourceClass::getPluralModelLabel()
                    ?: Str::headline(Str::plural(class_basename($modelClass)));

                $perms = [];
                foreach ($actions as $action) {
                    $perms[$slug . $separator . $action] = static::actionLabel($action);
                }

                $groups[$label] = $perms;
            } catch (\Throwable) {
                continue;
            }
        }

        return $groups;
    }

    /**
     * Returns permission options for standalone Pages (not Resource sub-pages).
     *
     * Shape: ['page.lvcoin-adjustment' => 'Điều chỉnh LVcoin', ...]
     */
    public static function getPagePermissions(string $panelId, string $separator): array
    {
        $pages = static::getPanelPages($panelId);
        $perms = [];

        foreach ($pages as $pageClass) {
            // Skip built-in Filament pages (Dashboard, etc.)
            if (str_starts_with($pageClass, 'Filament\\')) {
                continue;
            }

            try {
                $slug  = Str::kebab(class_basename($pageClass));
                $label = method_exists($pageClass, 'getNavigationLabel')
                    ? ($pageClass::getNavigationLabel() ?: Str::headline(class_basename($pageClass)))
                    : Str::headline(class_basename($pageClass));

                $perms['page' . $separator . $slug] = $label;
            } catch (\Throwable) {
                continue;
            }
        }

        return $perms;
    }

    /**
     * Returns permission options for Widgets.
     *
     * Shape: ['widget.lvcoin-stats-widget' => 'Lvcoin Stats Widget', ...]
     */
    public static function getWidgetPermissions(string $panelId, string $separator): array
    {
        $widgets = static::getPanelWidgets($panelId);
        $perms   = [];

        foreach ($widgets as $widgetClass) {
            if (str_starts_with($widgetClass, 'Filament\\')) {
                continue;
            }

            try {
                $slug  = Str::kebab(class_basename($widgetClass));
                $label = Str::headline(class_basename($widgetClass));

                $perms['widget' . $separator . $slug] = $label;
            } catch (\Throwable) {
                continue;
            }
        }

        return $perms;
    }

    /**
     * All permission keys (resources + optional pages/widgets) as a flat unique list.
     * Used by the mp:shield:generate artisan command.
     */
    public static function getAllPermissionKeys(
        string $panelId,
        array $actions,
        string $separator,
        bool $includePages = false,
        bool $includeWidgets = false
    ): array {
        $groups = static::getResourceGroups($panelId, $actions, $separator);

        $keys = array_merge(...array_map('array_keys', array_values($groups) ?: [[]]));

        if ($includePages) {
            $keys = array_merge($keys, array_keys(static::getPagePermissions($panelId, $separator)));
        }

        if ($includeWidgets) {
            $keys = array_merge($keys, array_keys(static::getWidgetPermissions($panelId, $separator)));
        }

        return array_values(array_unique($keys));
    }

    /**
     * Derive a kebab-plural slug from a model FQCN.
     * App\Models\OAuthClient → 'oauth-clients'
     */
    public static function modelSlug(string $modelClass): string
    {
        return Str::plural(Str::kebab(class_basename($modelClass)));
    }

    // ─── Internal helpers ──────────────────────────────────────────────────────

    private static function getPanelResources(string $panelId): array
    {
        if (!class_exists(\Filament\Facades\Filament::class)) {
            return [];
        }

        try {
            return \Filament\Facades\Filament::getPanel($panelId)->getResources();
        } catch (\Throwable) {
            return [];
        }
    }

    private static function getPanelPages(string $panelId): array
    {
        if (!class_exists(\Filament\Facades\Filament::class)) {
            return [];
        }

        try {
            return \Filament\Facades\Filament::getPanel($panelId)->getPages();
        } catch (\Throwable) {
            return [];
        }
    }

    private static function getPanelWidgets(string $panelId): array
    {
        if (!class_exists(\Filament\Facades\Filament::class)) {
            return [];
        }

        try {
            return \Filament\Facades\Filament::getPanel($panelId)->getWidgets();
        } catch (\Throwable) {
            return [];
        }
    }

    private static function actionLabel(string $action): string
    {
        return match ($action) {
            'view'   => 'Xem',
            'create' => 'Tạo',
            'update' => 'Sửa',
            'delete' => 'Xóa',
            default  => Str::headline($action),
        };
    }
}
