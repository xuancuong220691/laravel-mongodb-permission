<?php

namespace CuongNX\MongoPermission\Services\Contracts;

interface PermissionServiceInterface
{
    public function createRoles(string $roles, string $guard): array;
    public function deleteRoles(string $roles, string $guard): array;

    public function createPermissions(string $permissions, string $guard): array;
    public function deletePermissions(string $permissions, string $guard): array;

    public function assignPermissions(string $role, string $permissions, string $guard): array;

    public function listRoles(string $guard): array;
    public function listPermissions(string $guard): array;

    public function reset(): void;

    public function exportToFile(string $path): void;
    public function importFromFile(string $path, string $guard): array;

    public function syncRolePermissions(string $role, string $jsonPath, string $guard): array;

    public function showRole(string $role, string $guard): array;
}
