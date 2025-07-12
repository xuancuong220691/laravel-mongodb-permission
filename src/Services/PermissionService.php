<?php

namespace CuongNX\MongoPermission\Services;

use Illuminate\Support\Facades\File;
use CuongNX\MongoPermission\Models\Role;
use CuongNX\MongoPermission\Models\Permission;
use CuongNX\MongoPermission\Services\Contracts\PermissionServiceInterface;

class PermissionService implements PermissionServiceInterface
{
    public function createRoles(string $roles, string $guard): array
    {
        $items = $this->parseList($roles);
        $created = $skipped = [];

        foreach ($items as $name) {
            if (Role::where('name', $name)->where('guard_name', $guard)->exists()) {
                $skipped[] = $name;
                continue;
            }

            Role::create(['name' => $name, 'guard_name' => $guard]);
            $created[] = $name;
        }

        return compact('created', 'skipped');
    }

    public function deleteRoles(string $roles, string $guard): array
    {
        $items = $this->parseList($roles);
        $deleted = [];

        foreach ($items as $name) {
            $role = Role::where('name', $name)->where('guard_name', $guard)->first();
            if ($role) {
                $role->delete();
                $deleted[] = $name;
            }
        }

        return ['deleted' => $deleted];
    }

    public function createPermissions(string $permissions, string $guard): array
    {
        $items = $this->parseList($permissions);
        $created = $skipped = [];

        foreach ($items as $name) {
            if (Permission::where('name', $name)->where('guard_name', $guard)->exists()) {
                $skipped[] = $name;
                continue;
            }

            Permission::create(['name' => $name, 'guard_name' => $guard]);
            $created[] = $name;
        }

        return compact('created', 'skipped');
    }

    public function deletePermissions(string $permissions, string $guard): array
    {
        $items = $this->parseList($permissions);
        $deleted = [];

        foreach ($items as $name) {
            $perm = Permission::where('name', $name)->where('guard_name', $guard)->first();
            if ($perm) {
                $perm->delete();
                $deleted[] = $name;
            }
        }

        return ['deleted' => $deleted];
    }

    public function assignPermissions(string $roleName, string $permissions, string $guard): array
    {
        $role = Role::where('name', $roleName)->where('guard_name', $guard)->first();
        if (!$role) return ['failed' => ["Role [$roleName] không tồn tại"]];

        $items = $this->parseList($permissions);
        $assigned = $skipped = [];

        foreach ($items as $permName) {
            $perm = Permission::where('name', $permName)->where('guard_name', $guard)->first();
            if (!$perm) {
                $skipped[] = $permName;
                continue;
            }

            $perms = $role->permissions ?? [];
            if (!in_array($permName, $perms)) {
                $perms[] = $permName;
                $assigned[] = $permName;
            } else {
                $skipped[] = $permName;
            }

            $role->permissions = $perms;
            $role->save();
        }

        return compact('assigned', 'skipped');
    }

    public function listRoles(string $guard): array
    {
        return Role::where('guard_name', $guard)->get(['name', 'guard_name'])->toArray();
    }

    public function listPermissions(string $guard): array
    {
        return Permission::where('guard_name', $guard)->get(['name', 'guard_name'])->toArray();
    }

    public function reset(): void
    {
        Role::truncate();
        Permission::truncate();
    }

    public function exportToFile(string $path): void
    {
        $data = [
            'roles' => Role::all()->toArray(),
            'permissions' => Permission::all()->toArray(),
        ];

        File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function importFromFile(string $path, string $guard): array
    {
        $json = json_decode(File::get($path), true);
        $result = ['created' => [], 'skipped' => []];

        foreach ($json['roles'] ?? [] as $role) {
            $res = $this->createRoles($role['name'], $guard);
            $result['created'] = array_merge($result['created'], $res['created']);
            $result['skipped'] = array_merge($result['skipped'], $res['skipped']);
        }

        foreach ($json['permissions'] ?? [] as $perm) {
            $res = $this->createPermissions($perm['name'], $guard);
            $result['created'] = array_merge($result['created'], $res['created']);
            $result['skipped'] = array_merge($result['skipped'], $res['skipped']);
        }

        return $result;
    }

    public function syncRolePermissions(string $roleName, string $jsonPath, string $guard): array
    {
        $role = Role::where('name', $roleName)->where('guard_name', $guard)->first();
        if (!$role) return ['failed' => ["Role [$roleName] không tồn tại"]];

        $json = json_decode(File::get($jsonPath), true);
        $perms = $this->parseList(implode(',', $json['permissions'] ?? []));

        $role->permissions = $perms;
        $role->save();

        return ['synced' => $perms];
    }

    public function showRole(string $roleName, string $guard): array
    {
        $role = Role::where('name', $roleName)->where('guard_name', $guard)->first();
        if (!$role) return [];

        return [
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'permissions' => $role->permissions ?? [],
        ];
    }

    protected function parseList(string $input): array
    {
        return array_filter(array_map('trim', explode(',', $input)));
    }
}
