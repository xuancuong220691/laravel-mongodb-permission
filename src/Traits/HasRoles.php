<?php

namespace CuongNX\LaravelMongoPermission\Traits;

use CuongNX\LaravelMongoPermission\Models\Role;
use CuongNX\LaravelMongoPermission\Models\Permission;
use Illuminate\Support\Collection;
use MongoDB\BSON\ObjectId;

trait HasRoles
{
    // Cache per-request, keyed by "<model_id>:<type>:<name>"
    private static array $permissionCache = [];

    // -------------------------------------------------------------------------
    // Guard
    // -------------------------------------------------------------------------

    protected function getGuardName(): string
    {
        return property_exists($this, 'guard_name') && $this->guard_name
            ? $this->guard_name
            : config('auth.defaults.guard', 'web');
    }

    // -------------------------------------------------------------------------
    // Roles
    // -------------------------------------------------------------------------

    public function assignRole(string $roleName): void
    {
        $role = Role::where('name', $roleName)
            ->where('guard_name', $this->getGuardName())
            ->first();

        if (!$role) return;

        $ids = $this->role_ids ?? [];
        if (!in_array((string) $role->_id, $ids)) {
            $ids[] = (string) $role->_id;
            $this->role_ids = $ids;
            $this->save();
            $this->clearPermissionCache();
        }
    }

    public function syncRoles(array $roleNames): void
    {
        $roles = Role::whereIn('name', $roleNames)
            ->where('guard_name', $this->getGuardName())
            ->get();

        $this->role_ids = $roles->map(fn($r) => (string) $r->_id)->values()->toArray();
        $this->save();
        $this->clearPermissionCache();
    }

    /** Alias của removeRole() cho nhất quán với revokePermissionTo() */
    public function revokeRole(string $roleName): void
    {
        $this->removeRole($roleName);
    }

    public function removeRole(string $roleName): void
    {
        $role = Role::where('name', $roleName)
            ->where('guard_name', $this->getGuardName())
            ->first();

        if (!$role) return;

        $this->role_ids = array_values(
            array_filter($this->role_ids ?? [], fn($id) => $id !== (string) $role->_id)
        );
        $this->save();
        $this->clearPermissionCache();
    }

    public function hasRole(string $roleName): bool
    {
        if (empty($this->role_ids)) return false;

        $cacheKey = $this->getCacheKey('role', $roleName);
        if (array_key_exists($cacheKey, static::$permissionCache)) {
            return static::$permissionCache[$cacheKey];
        }

        $result = Role::whereIn('_id', $this->toObjectIds($this->role_ids))
            ->where('guard_name', $this->getGuardName())
            ->where('name', $roleName)
            ->exists();

        return static::$permissionCache[$cacheKey] = $result;
    }

    public function hasAnyRole(array $roleNames): bool
    {
        if (empty($this->role_ids) || empty($roleNames)) return false;

        return Role::whereIn('_id', $this->toObjectIds($this->role_ids))
            ->where('guard_name', $this->getGuardName())
            ->whereIn('name', $roleNames)
            ->exists();
    }

    public function hasAllRoles(array $roleNames): bool
    {
        if (empty($this->role_ids) || empty($roleNames)) return false;

        // Fix #8: dùng count() từ DB thay vì array_intersect trong PHP
        $found = Role::whereIn('_id', $this->toObjectIds($this->role_ids))
            ->where('guard_name', $this->getGuardName())
            ->whereIn('name', $roleNames)
            ->count();

        return $found === count($roleNames);
    }

    public function getRoleNames(): Collection
    {
        if (empty($this->role_ids)) {
            return collect();
        }

        return Role::whereIn('_id', $this->toObjectIds($this->role_ids))
            ->where('guard_name', $this->getGuardName())
            ->pluck('name');
    }

    // -------------------------------------------------------------------------
    // Direct permissions
    // -------------------------------------------------------------------------

    public function givePermissionTo(string $permissionName): void
    {
        $permission = Permission::where('name', $permissionName)
            ->where('guard_name', $this->getGuardName())
            ->first();

        if (!$permission) return;

        $ids = $this->permission_ids ?? [];
        if (!in_array((string) $permission->_id, $ids)) {
            $ids[] = (string) $permission->_id;
            $this->permission_ids = $ids;
            $this->save();
            $this->clearPermissionCache();
        }
    }

    public function revokePermissionTo(string $permissionName): void
    {
        $permission = Permission::where('name', $permissionName)
            ->where('guard_name', $this->getGuardName())
            ->first();

        if (!$permission) return;

        $this->permission_ids = array_values(
            array_filter($this->permission_ids ?? [], fn($id) => $id !== (string) $permission->_id)
        );
        $this->save();
        $this->clearPermissionCache();
    }

    /** Fix #6: sync toàn bộ direct permissions (thay thế cũ bằng mới) */
    public function syncPermissions(array $permissionNames): void
    {
        $permissions = Permission::whereIn('name', $permissionNames)
            ->where('guard_name', $this->getGuardName())
            ->get();

        $this->permission_ids = $permissions->map(fn($p) => (string) $p->_id)->values()->toArray();
        $this->save();
        $this->clearPermissionCache();
    }

    public function hasPermissionTo(string $permissionName): bool
    {
        $cacheKey = $this->getCacheKey('perm', $permissionName);
        if (array_key_exists($cacheKey, static::$permissionCache)) {
            return static::$permissionCache[$cacheKey];
        }

        // 1. Kiểm tra direct permission
        if (!empty($this->permission_ids)) {
            $direct = Permission::whereIn('_id', $this->toObjectIds($this->permission_ids))
                ->where('guard_name', $this->getGuardName())
                ->where('name', $permissionName)
                ->exists();

            if ($direct) {
                return static::$permissionCache[$cacheKey] = true;
            }
        }

        // 2. Kiểm tra qua roles
        if (!empty($this->role_ids)) {
            $roles = Role::whereIn('_id', $this->toObjectIds($this->role_ids))
                ->where('guard_name', $this->getGuardName())
                ->get(['permissions']);

            foreach ($roles as $role) {
                if (in_array($permissionName, $role->permissions ?? [])) {
                    return static::$permissionCache[$cacheKey] = true;
                }
            }
        }

        return static::$permissionCache[$cacheKey] = false;
    }

    public function hasAllPermissions(array $permissionNames): bool
    {
        foreach ($permissionNames as $permission) {
            if (!$this->hasPermissionTo($permission)) return false;
        }
        return true;
    }

    public function hasAnyPermission(array $permissionNames): bool
    {
        foreach ($permissionNames as $permission) {
            if ($this->hasPermissionTo($permission)) return true;
        }
        return false;
    }

    /** @return string[] Tất cả tên permissions (direct + via roles, unique) */
    public function getAllPermissions(): array
    {
        $direct = [];
        if (!empty($this->permission_ids)) {
            $direct = Permission::whereIn('_id', $this->toObjectIds($this->permission_ids))
                ->where('guard_name', $this->getGuardName())
                ->pluck('name')
                ->toArray();
        }

        $viaRoles = [];
        if (!empty($this->role_ids)) {
            $roles = Role::whereIn('_id', $this->toObjectIds($this->role_ids))
                ->where('guard_name', $this->getGuardName())
                ->get(['permissions']);

            foreach ($roles as $role) {
                $viaRoles = array_merge($viaRoles, $role->permissions ?? []);
            }
        }

        return array_values(array_unique(array_merge($direct, $viaRoles)));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected function toObjectIds(array $ids): array
    {
        $result = [];
        foreach ($ids as $id) {
            try {
                $result[] = new ObjectId((string) $id);
            } catch (\Throwable) {
                // ID không hợp lệ — bỏ qua để không crash query
            }
        }
        return $result;
    }

    protected function getCacheKey(string $type, string $name): string
    {
        return ($this->getKey() ?? spl_object_id($this)) . ':' . $type . ':' . $name;
    }

    protected function clearPermissionCache(): void
    {
        $prefix = ($this->getKey() ?? spl_object_id($this)) . ':';
        foreach (array_keys(static::$permissionCache) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset(static::$permissionCache[$key]);
            }
        }
    }
}