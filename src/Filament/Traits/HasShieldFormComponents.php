<?php

namespace CuongNX\LaravelMongoPermission\Filament\Traits;

use CuongNX\LaravelMongoPermission\Filament\MongoShieldPlugin;
use CuongNX\LaravelMongoPermission\Filament\Support\ResourceDiscovery;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

trait HasShieldFormComponents
{
    /**
     * Main entry point — returns the complete permission form block with tabs.
     *
     * Usage in RoleResource::form():
     *   static::getShieldFormComponents()
     */
    public static function getShieldFormComponents(): Component
    {
        $plugin = MongoShieldPlugin::get();

        $resourceGroups = ResourceDiscovery::getResourceGroups(
            $plugin->getPanelId(),
            $plugin->getResourceActions(),
            $plugin->getSeparator(),
        );

        $pagePerms = $plugin->hasPagePermissions()
            ? ResourceDiscovery::getPagePermissions($plugin->getPanelId(), $plugin->getSeparator())
            : [];

        $widgetPerms = $plugin->hasWidgetPermissions()
            ? ResourceDiscovery::getWidgetPermissions($plugin->getPanelId(), $plugin->getSeparator())
            : [];

        // Build synthetic field names for every group
        $resourceGroupKeys = static::buildGroupKeys($resourceGroups);
        $pageFieldName     = '__shield_pages';
        $widgetFieldName   = '__shield_widgets';

        // All field names combined — used to merge into Hidden('permissions')
        $allKeys = array_values($resourceGroupKeys);
        if (!empty($pagePerms))   $allKeys[] = $pageFieldName;
        if (!empty($widgetPerms)) $allKeys[] = $widgetFieldName;

        // Build tabs
        $tabs = [static::getResourceTab($resourceGroups, $resourceGroupKeys, $allKeys, $plugin)];

        if (!empty($pagePerms)) {
            $tabs[] = static::getPageTab($pagePerms, $pageFieldName, $allKeys);
        }

        if (!empty($widgetPerms)) {
            $tabs[] = static::getWidgetTab($widgetPerms, $widgetFieldName, $allKeys);
        }

        return Section::make('Quyền hạn')
            ->description('Chọn các quyền mà vai trò này được phép thực hiện')
            ->columnSpanFull()
            ->schema([
                static::getSelectAllComponent($resourceGroups, $resourceGroupKeys, $pagePerms, $widgetPerms, $pageFieldName, $widgetFieldName),
                Hidden::make('permissions')->default([]),
                Tabs::make('shield_tabs')->tabs($tabs)->columnSpanFull(),
            ]);
    }

    // ─── Tabs ──────────────────────────────────────────────────────────────────

    protected static function getResourceTab(
        array $groups,
        array $groupKeys,
        array $allKeys,
        MongoShieldPlugin $plugin
    ): Tab {
        $totalPerms = array_sum(array_map('count', $groups));
        $colCount   = count($plugin->getResourceActions());
        $sections   = [];

        foreach ($groups as $groupLabel => $permissions) {
            $fieldName  = $groupKeys[$groupLabel];
            $sections[] = Section::make($groupLabel)
                ->compact()
                ->collapsible()
                ->schema([
                    CheckboxList::make($fieldName)
                        ->hiddenLabel()
                        ->options($permissions)
                        ->columns($colCount)
                        ->gridDirection('row')
                        ->bulkToggleable()
                        ->live()
                        ->afterStateHydrated(function (CheckboxList $component, $record) use ($permissions) {
                            $all = $record?->permissions ?? [];
                            $component->state(
                                array_values(array_intersect($all, array_keys($permissions)))
                            );
                        })
                        ->afterStateUpdated(function (Get $get, Set $set) use ($allKeys) {
                            static::mergeShieldPermissions($get, $set, $allKeys);
                        })
                        ->dehydrated(false),
                ]);
        }

        return Tab::make('Tài nguyên')
            ->badge($totalPerms)
            ->schema($sections);
    }

    protected static function getPageTab(
        array $pagePerms,
        string $fieldName,
        array $allKeys
    ): Tab {
        return Tab::make('Trang')
            ->badge(count($pagePerms))
            ->schema([
                CheckboxList::make($fieldName)
                    ->hiddenLabel()
                    ->options($pagePerms)
                    ->columns(3)
                    ->gridDirection('row')
                    ->bulkToggleable()
                    ->live()
                    ->afterStateHydrated(function (CheckboxList $component, $record) use ($pagePerms) {
                        $all = $record?->permissions ?? [];
                        $component->state(
                            array_values(array_intersect($all, array_keys($pagePerms)))
                        );
                    })
                    ->afterStateUpdated(function (Get $get, Set $set) use ($allKeys) {
                        static::mergeShieldPermissions($get, $set, $allKeys);
                    })
                    ->dehydrated(false),
            ]);
    }

    protected static function getWidgetTab(
        array $widgetPerms,
        string $fieldName,
        array $allKeys
    ): Tab {
        return Tab::make('Widget')
            ->badge(count($widgetPerms))
            ->schema([
                CheckboxList::make($fieldName)
                    ->hiddenLabel()
                    ->options($widgetPerms)
                    ->columns(3)
                    ->gridDirection('row')
                    ->bulkToggleable()
                    ->live()
                    ->afterStateHydrated(function (CheckboxList $component, $record) use ($widgetPerms) {
                        $all = $record?->permissions ?? [];
                        $component->state(
                            array_values(array_intersect($all, array_keys($widgetPerms)))
                        );
                    })
                    ->afterStateUpdated(function (Get $get, Set $set) use ($allKeys) {
                        static::mergeShieldPermissions($get, $set, $allKeys);
                    })
                    ->dehydrated(false),
            ]);
    }

    // ─── Select-all toggle ─────────────────────────────────────────────────────

    protected static function getSelectAllComponent(
        array $resourceGroups,
        array $resourceGroupKeys,
        array $pagePerms,
        array $widgetPerms,
        string $pageFieldName,
        string $widgetFieldName
    ): Component {
        $allKeys = array_values($resourceGroupKeys);
        if (!empty($pagePerms))   $allKeys[] = $pageFieldName;
        if (!empty($widgetPerms)) $allKeys[] = $widgetFieldName;

        $allExpectedKeys = array_merge(
            ...array_map('array_keys', array_values($resourceGroups) ?: [[]]),
            array_keys($pagePerms),
            array_keys($widgetPerms),
        );

        return Toggle::make('__shield_select_all')
            ->label('Chọn tất cả quyền')
            ->live()
            ->dehydrated(false)
            ->afterStateHydrated(function (Toggle $component, $record) use ($allExpectedKeys) {
                if (!$record) {
                    $component->state(false);
                    return;
                }
                $all = $record->permissions ?? [];
                $component->state(!empty($allExpectedKeys) && empty(array_diff($allExpectedKeys, $all)));
            })
            ->afterStateUpdated(function (bool $state, Set $set) use ($resourceGroups, $resourceGroupKeys, $pagePerms, $widgetPerms, $pageFieldName, $widgetFieldName, $allKeys) {
                if ($state) {
                    $merged = [];
                    foreach ($resourceGroups as $label => $permissions) {
                        $set($resourceGroupKeys[$label], array_keys($permissions));
                        $merged = array_merge($merged, array_keys($permissions));
                    }
                    if (!empty($pagePerms)) {
                        $set($pageFieldName, array_keys($pagePerms));
                        $merged = array_merge($merged, array_keys($pagePerms));
                    }
                    if (!empty($widgetPerms)) {
                        $set($widgetFieldName, array_keys($widgetPerms));
                        $merged = array_merge($merged, array_keys($widgetPerms));
                    }
                    $set('permissions', array_values(array_unique($merged)));
                } else {
                    foreach ($allKeys as $fieldName) {
                        $set($fieldName, []);
                    }
                    $set('permissions', []);
                }
            });
    }

    // ─── Aggregate helper ──────────────────────────────────────────────────────

    protected static function mergeShieldPermissions(Get $get, Set $set, array $fieldNames): void
    {
        $merged = [];
        foreach ($fieldNames as $fieldName) {
            $merged = array_merge($merged, (array) ($get($fieldName) ?? []));
        }
        $set('permissions', array_values(array_unique($merged)));
    }

    // ─── Internal ─────────────────────────────────────────────────────────────

    protected static function buildGroupKeys(array $groups): array
    {
        $map = [];
        foreach (array_keys($groups) as $label) {
            $map[$label] = '__shield_' . Str::snake(Str::ascii($label));
        }
        return $map;
    }
}
