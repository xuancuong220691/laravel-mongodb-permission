<?php

namespace CuongNX\LaravelMongoPermission\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

class MongoShieldPlugin implements Plugin
{
    private string $panelId = 'admin';

    private array $resourceActions = ['view', 'create', 'update', 'delete'];

    private string $separator = '.';

    private string $superAdminRole = 'super-admin';

    private bool $includePagePermissions = false;

    // ─── Factory ───────────────────────────────────────────────────────────────

    public static function make(): static
    {
        return new static();
    }

    public static function get(): static
    {
        return \Filament\Facades\Filament::getCurrentPanel()->getPlugin('mongo-shield');
    }

    // ─── Plugin contract ───────────────────────────────────────────────────────

    public function getId(): string
    {
        return 'mongo-shield';
    }

    public function register(Panel $panel): void {}

    public function boot(Panel $panel): void {}

    // ─── Fluent config ─────────────────────────────────────────────────────────

    /** Filament panel ID to scan resources from (default: 'admin'). */
    public function panelId(string $panelId): static
    {
        $this->panelId = $panelId;
        return $this;
    }

    /** CRUD-style actions to generate per resource (default: view/create/update/delete). */
    public function resourceActions(array $actions): static
    {
        $this->resourceActions = $actions;
        return $this;
    }

    /** Separator between resource slug and action (default: '.'). */
    public function separator(string $separator): static
    {
        $this->separator = $separator;
        return $this;
    }

    /** Role name that bypasses all permission checks (default: 'super-admin'). */
    public function superAdminRole(string $role): static
    {
        $this->superAdminRole = $role;
        return $this;
    }

    /** Also generate/display permissions for standalone Filament Pages. */
    public function withPagePermissions(bool $enabled = true): static
    {
        $this->includePagePermissions = $enabled;
        return $this;
    }

    // ─── Getters ───────────────────────────────────────────────────────────────

    public function getPanelId(): string { return $this->panelId; }

    public function getResourceActions(): array { return $this->resourceActions; }

    public function getSeparator(): string { return $this->separator; }

    public function getSuperAdminRole(): string { return $this->superAdminRole; }

    public function hasPagePermissions(): bool { return $this->includePagePermissions; }
}
