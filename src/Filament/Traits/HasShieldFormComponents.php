<?php

namespace CuongNX\LaravelMongoPermission\Filament\Traits;

use CuongNX\LaravelMongoPermission\Filament\MongoShieldPlugin;
use CuongNX\LaravelMongoPermission\Filament\Support\ResourceDiscovery;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

trait HasShieldFormComponents
{
    /**
     * Main entry point — returns the complete permission form block.
     *
     * Usage in RoleResource::form():
     *   static::getShieldFormComponents()
     */
    public static function getShieldFormComponents(): Component
    {
        $plugin  = MongoShieldPlugin::get();
        $groups  = ResourceDiscovery::getResourceGroups(
            $plugin->getPanelId(),
            $plugin->getResourceActions(),
            $plugin->getSeparator(),
        );

        if ($plugin->hasPagePermissions()) {
            $pagePerms = ResourceDiscovery::getPagePermissions(
                $plugin->getPanelId(),
                $plugin->getSeparator(),
            );
            if (!empty($pagePerms)) {
                $groups['Trang (Pages)'] = $pagePerms;
            }
        }

        // Map each group label → unique synthetic field name
        $groupKeys = static::buildGroupKeys($groups);

        // One Section per resource group
        $resourceSections = static::buildResourceSections($groups, $groupKeys, $plugin);

        return Section::make('Quyền hạn')
            ->description('Chọn các quyền mà vai trò này được phép thực hiện')
            ->columnSpanFull()
            ->schema([
                // Master toggle — check / uncheck all at once
                static::getSelectAllComponent($groups, $groupKeys),
                // Hidden field — aggregates all group states, this is what Filament saves to the model
                Hidden::make('permissions')->default([]),
                // Per-resource collapsible sections
                ...$resourceSections,
            ]);
    }

    // ─── Group sections ────────────────────────────────────────────────────────

    /**
     * Build one collapsible Section per resource group.
     * Each section contains a CheckboxList for its permission subset.
     */
    protected static function buildResourceSections(
        array $groups,
        array $groupKeys,
        MongoShieldPlugin $plugin
    ): array {
        $sections   = [];
        $allKeys    = array_values($groupKeys);
        $colCount   = count($plugin->getResourceActions());

        foreach ($groups as $groupLabel => $permissions) {
            $fieldName = $groupKeys[$groupLabel];

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
                            // Pre-check only the permissions belonging to this group
                            $all = $record?->permissions ?? [];
                            $component->state(
                                array_values(array_intersect($all, array_keys($permissions)))
                            );
                        })
                        ->afterStateUpdated(function (Get $get, Set $set) use ($allKeys) {
                            static::mergeShieldPermissions($get, $set, $allKeys);
                        })
                        ->dehydrated(false), // Not saved individually — only Hidden('permissions') is
                ]);
        }

        return $sections;
    }

    // ─── Select-all toggle ─────────────────────────────────────────────────────

    protected static function getSelectAllComponent(array $groups, array $groupKeys): Component
    {
        $allKeys = array_values($groupKeys);

        return Toggle::make('__shield_select_all')
            ->label('Chọn tất cả quyền')
            ->live()
            ->dehydrated(false)
            ->afterStateHydrated(function (Toggle $component, $record) use ($groups) {
                if (!$record) {
                    $component->state(false);
                    return;
                }
                $all         = $record->permissions ?? [];
                $allExpected = array_merge(...array_map('array_keys', array_values($groups) ?: [[]]));
                $component->state(!empty($allExpected) && empty(array_diff($allExpected, $all)));
            })
            ->afterStateUpdated(function (bool $state, Set $set) use ($groups, $groupKeys, $allKeys) {
                if ($state) {
                    $merged = [];
                    foreach ($groups as $label => $permissions) {
                        $set($groupKeys[$label], array_keys($permissions));
                        $merged = array_merge($merged, array_keys($permissions));
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

    /**
     * Collect checked values from all group CheckboxLists and write to Hidden('permissions').
     * Called from each CheckboxList's afterStateUpdated.
     */
    protected static function mergeShieldPermissions(Get $get, Set $set, array $fieldNames): void
    {
        $merged = [];
        foreach ($fieldNames as $fieldName) {
            $merged = array_merge($merged, (array) ($get($fieldName) ?? []));
        }
        $set('permissions', array_values(array_unique($merged)));
    }

    // ─── Internal ─────────────────────────────────────────────────────────────

    /**
     * Build a sanitized field-name map.
     * 'Người dùng' → '__shield_nguoi_dung', 'OAuth Clients' → '__shield_o_auth_clients'
     */
    protected static function buildGroupKeys(array $groups): array
    {
        $map = [];
        foreach (array_keys($groups) as $label) {
            $map[$label] = '__shield_' . Str::snake(Str::ascii($label));
        }
        return $map;
    }
}
