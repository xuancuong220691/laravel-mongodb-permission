<?php

namespace CuongNX\MongoPermission\Traits;

use CuongNX\MongoPermission\Models\Role;
use CuongNX\MongoPermission\Models\Permission;
use MongoDB\BSON\ObjectId;

trait HasRoles
{
    public function assignRole(string $roleName)
    {
        $role = Role::where('name', $roleName)->first();

        if (!$role) return;

        $ids = $this->role_ids ?? [];
        if (!in_array((string)$role->_id, $ids)) {
            $ids[] = (string)$role->_id;
            $this->role_ids = $ids;
            $this->save();
        }
    }

    public function hasRole(string $roleName): bool
    {
        return Role::whereIn('_id', $this->toObjectIds($this->role_ids ?? []))
            ->where('name', $roleName)
            ->exists();
    }

    public function givePermissionTo(string $permissionName)
    {
        $permission = Permission::where('name', $permissionName)->first();

        if (!$permission) return;

        $ids = $this->permission_ids ?? [];
        if (!in_array((string)$permission->_id, $ids)) {
            $ids[] = (string)$permission->_id;
            $this->permission_ids = $ids;
            $this->save();
        }
    }

    public function hasPermissionTo(string $permissionName): bool
    {
        $direct = Permission::whereIn('_id', $this->toObjectIds($this->permission_ids ?? []))
            ->where('name', $permissionName)
            ->exists();

        if ($direct) return true;

        $roleIds = $this->toObjectIds($this->role_ids ?? []);
        $roles = Role::whereIn('_id', $roleIds)->get();

        foreach ($roles as $role) {
            if (in_array($permissionName, $role->permissions ?? [])) {
                return true;
            }
        }

        return false;
    }

    protected function toObjectIds(array $ids): array
    {
        return array_map(fn($id) => new ObjectId($id), $ids);
    }

    public function getRoleNames(): \Illuminate\Support\Collection
    {
        return Role::whereIn('_id', $this->toObjectIds($this->role_ids ?? []))
            ->pluck('name');
    }
}
