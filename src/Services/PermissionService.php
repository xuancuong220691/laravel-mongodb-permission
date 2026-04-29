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

        // Fix #3: load tất cả permissions cần thiết trong 1 query thay vì N queries
        $existingNames = Permission::where('guard_name', $guard)
            ->whereIn('name', $items)
            ->pluck('name')
            ->flip() // ['name' => index] để O(1) lookup
            ->toArray();

        $assigned = $skipped = [];
        $perms = $role->permissions ?? [];

        foreach ($items as $permName) {
            if (!isset($existingNames[$permName])) {
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

        $role->permissions = $perms;
        $role->save();

        return compact('assigned', 'skipped');
    }

    // Fix #7: xóa bớt một số permissions khỏi role (không sync toàn bộ)
    public function revokePermissions(string $roleName, string $permissions, string $guard): array
    {
        $role = Role::where('name', $roleName)->where('guard_name', $guard)->first();
        if (!$role) return ['failed' => ["Role [$roleName] không tồn tại"]];

        $items = $this->parseList($permissions);
        $revoked = $skipped = [];
        $perms = $role->permissions ?? [];

        foreach ($items as $permName) {
            if (in_array($permName, $perms)) {
                $perms = array_values(array_filter($perms, fn($p) => $p !== $permName));
                $revoked[] = $permName;
            } else {
                $skipped[] = $permName;
            }
        }

        $role->permissions = $perms;
        $role->save();

        return compact('revoked', 'skipped');
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

    // Fix #4: thêm tham số guard tùy chọn, tránh xóa nhầm guard khác
    public function reset(?string $guard = null): void
    {
        if ($guard) {
            Role::where('guard_name', $guard)->each(fn($r) => $r->delete());
            Permission::where('guard_name', $guard)->each(fn($p) => $p->delete());
        } else {
            // Truncate xóa toàn bộ mọi guard, không fire model events
            Role::truncate();
            Permission::truncate();
        }
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
     *
     * @return array{created: string[], skipped: string[]}
     * @throws \InvalidArgumentException nếu file không tồn tại hoặc JSON không hợp lệ
     */
    public function importFromFile(string $path, string $guard): array
    {
        // Fix #2: validate file tồn tại và JSON hợp lệ
        if (!File::exists($path)) {
            throw new \InvalidArgumentException("File không tồn tại: $path");
        }

        $json = json_decode(File::get($path), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException("JSON không hợp lệ: " . json_last_error_msg());
        }

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

        // Fix #2: validate JSON trong syncRolePermissions
        if (!File::exists($jsonPath)) {
            throw new \InvalidArgumentException("File không tồn tại: $jsonPath");
        }

        $json = json_decode(File::get($jsonPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException("JSON không hợp lệ: " . json_last_error_msg());
        }

        $requestedPerms = $json['permissions'] ?? [];

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