<?php

namespace CuongNX\LaravelMongoPermission\Services;

use Illuminate\Support\Facades\File;
use CuongNX\LaravelMongoPermission\Models\Role;
use CuongNX\LaravelMongoPermission\Models\Permission;
use CuongNX\LaravelMongoPermission\Services\Contracts\PermissionServiceInterface;

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
                $role->delete(); // fires Role::deleting event → cascade cleanup
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
                $perm->delete(); // fires Permission::deleting event → cascade cleanup
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
        $perms = $role->permissions ?? [];

        foreach ($items as $permName) {
            if (!Permission::where('name', $permName)->where('guard_name', $guard)->exists()) {
                $skipped[] = $permName;
                continue;
            }

            if (!in_array($permName, $perms)) {
                $perms[] = $permName;
                $assigned[] = $permName;
            } else {
                $skipped[] = $permName;
            }
        }

        // Lưu 1 lần sau vòng lặp
        $role->permissions = $perms;
        $role->save();

        return compact('assigned', 'skipped');
    }

    public function listRoles(string $guard): array
    {
        return Role::where('guard_name', $guard)
            ->get(['name', 'guard_name', 'permissions'])
            ->toArray();
    }

    public function listPermissions(string $guard): array
    {
        return Permission::where('guard_name', $guard)
            ->get(['name', 'guard_name'])
            ->toArray();
    }

    public function reset(): void
    {
        // truncate() không fire model events → dùng khi muốn xóa nhanh toàn bộ
        Role::truncate();
        Permission::truncate();
    }

    /**
     * Xuất roles & permissions ra file JSON.
     * Nếu truyền $guard, chỉ xuất dữ liệu của guard đó.
     * Tự tạo thư mục nếu chưa tồn tại.
     */
    public function exportToFile(string $path, ?string $guard = null): void
    {
        $rolesQuery = $guard ? Role::where('guard_name', $guard) : Role::query();
        $permsQuery = $guard ? Permission::where('guard_name', $guard) : Permission::query();

        $data = [
            'roles'       => $rolesQuery->get()->toArray(),
            'permissions' => $permsQuery->get()->toArray(),
        ];

        $dir = dirname($path);
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Nhập roles & permissions từ file JSON.
     * Tự động validate permissions trước khi gán vào role.
     */
    public function importFromFile(string $path, string $guard): array
    {
        $json = json_decode(File::get($path), true);
        $result = ['created' => [], 'skipped' => []];

        // 1. Import permissions trước (phải tồn tại trước khi gán cho role)
        foreach ($json['permissions'] ?? [] as $perm) {
            $name = is_array($perm) ? ($perm['name'] ?? '') : $perm;
            if (!$name) continue;

            $res = $this->createPermissions($name, $guard);
            $result['created'] = array_merge($result['created'], $res['created']);
            $result['skipped'] = array_merge($result['skipped'], $res['skipped']);
        }

        // 2. Import roles kèm permissions[]
        foreach ($json['roles'] ?? [] as $roleData) {
            $name = is_array($roleData) ? ($roleData['name'] ?? '') : $roleData;
            if (!$name) continue;

            $res = $this->createRoles($name, $guard);
            $result['created'] = array_merge($result['created'], $res['created']);
            $result['skipped'] = array_merge($result['skipped'], $res['skipped']);

            // Restore permissions — chỉ gán những permission thực sự tồn tại trong DB
            if (!empty($roleData['permissions'])) {
                $validPerms = Permission::where('guard_name', $guard)
                    ->whereIn('name', (array) $roleData['permissions'])
                    ->pluck('name')
                    ->toArray();

                if (!empty($validPerms)) {
                    $role = Role::where('name', $name)->where('guard_name', $guard)->first();
                    if ($role) {
                        $role->permissions = $validPerms;
                        $role->save();
                    }
                }
            }
        }

        return $result;
    }

    public function syncRolePermissions(string $roleName, string $jsonPath, string $guard): array
    {
        $role = Role::where('name', $roleName)->where('guard_name', $guard)->first();
        if (!$role) return ['failed' => ["Role [$roleName] không tồn tại"]];

        $json = json_decode(File::get($jsonPath), true);
        $requestedPerms = $json['permissions'] ?? [];

        // Chỉ gán permissions thực sự tồn tại trong DB
        $validPerms = Permission::where('guard_name', $guard)
            ->whereIn('name', $requestedPerms)
            ->pluck('name')
            ->toArray();

        $role->permissions = $validPerms;
        $role->save();

        return ['synced' => $validPerms];
    }

    public function showRole(string $roleName, string $guard): array
    {
        $role = Role::where('name', $roleName)->where('guard_name', $guard)->first();
        if (!$role) return [];

        return [
            'name'        => $role->name,
            'guard_name'  => $role->guard_name,
            'permissions' => $role->permissions ?? [],
        ];
    }

    protected function parseList(string $input): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $input))));
    }
}