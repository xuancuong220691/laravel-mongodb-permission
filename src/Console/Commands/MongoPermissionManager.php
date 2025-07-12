<?php

namespace CuongNX\MongoPermission\Console\Commands;

use Illuminate\Console\Command;
use CuongNX\MongoPermission\Services\Contracts\PermissionServiceInterface;

class MongoPermissionManager extends Command
{
    protected $signature = 'mp:manage
        {--create-role= : Tạo 1 hoặc nhiều role, phân cách bằng dấu phẩy}
        {--delete-role= : Xoá 1 hoặc nhiều role, phân cách bằng dấu phẩy}
        {--create-permission= : Tạo 1 hoặc nhiều permission, phân cách bằng dấu phẩy}
        {--delete-permission= : Xoá 1 hoặc nhiều permission, phân cách bằng dấu phẩy}
        {--assign-permission= : Gán permission cho role, cú pháp role:perm1,perm2}
        {--guard=web : Guard đang dùng}
        {--list-roles : Liệt kê các roles}
        {--list-permissions : Liệt kê các permissions}
        {--reset : Xoá toàn bộ roles và permissions}
        {--export= : Xuất roles & permissions ra file JSON}
        {--import= : Nhập roles & permissions từ file JSON}
        {--sync-role-permissions= : Đồng bộ permission cho role, cú pháp role:path/to/file.json}
        {--show-role= : Xem chi tiết 1 role}
    ';

    protected $description = 'Quản lý roles & permissions cho MongoDB';

    public function handle(PermissionServiceInterface $permissionService): void
    {
        $guard = $this->option('guard') ?? 'web';

        if ($this->option('create-role')) {
            $roles = $this->option('create-role');
            $result = $permissionService->createRoles($roles, $guard);
            $this->displayResult('Tạo role', $result);
        }

        if ($this->option('delete-role')) {
            $roles = $this->option('delete-role');
            $result = $permissionService->deleteRoles($roles, $guard);
            $this->displayResult('Xoá role', $result);
        }

        if ($this->option('create-permission')) {
            $permissions = $this->option('create-permission');
            $result = $permissionService->createPermissions($permissions, $guard);
            $this->displayResult('Tạo permission', $result);
        }

        if ($this->option('delete-permission')) {
            $permissions = $this->option('delete-permission');
            $result = $permissionService->deletePermissions($permissions, $guard);
            $this->displayResult('Xoá permission', $result);
        }

        if ($assign = $this->option('assign-permission')) {
            [$role, $perms] = explode(':', $assign);
            $result = $permissionService->assignPermissions($role, $perms, $guard);
            $this->displayResult("Gán permission cho role [$role]", $result);
        }

        if ($this->option('list-roles')) {
            $roles = $permissionService->listRoles($guard);
            $this->info("📋 Danh sách roles:");
            foreach ($roles as $role) {
                $this->line("- {$role['name']} ({$role['guard_name']})");
            }
        }

        if ($this->option('list-permissions')) {
            $perms = $permissionService->listPermissions($guard);
            $this->info("📋 Danh sách permissions:");
            foreach ($perms as $perm) {
                $this->line("- {$perm['name']} ({$perm['guard_name']})");
            }
        }

        if ($this->option('reset')) {
            $permissionService->reset();
            $this->warn('🗑️ Đã xoá toàn bộ roles và permissions!');
        }

        if ($exportPath = $this->option('export')) {
            $permissionService->exportToFile($exportPath);
            $this->info("Đã xuất file: $exportPath");
        }

        if ($importPath = $this->option('import')) {
            $result = $permissionService->importFromFile($importPath, $guard);
            $this->displayResult("📥 Nhập từ $importPath", $result);
        }

        if ($sync = $this->option('sync-role-permissions')) {
            [$role, $file] = explode(':', $sync);
            $result = $permissionService->syncRolePermissions($role, $file, $guard);
            $this->displayResult("🔄 Đồng bộ permission cho role [$role]", $result);
        }

        if ($show = $this->option('show-role')) {
            $data = $permissionService->showRole($show, $guard);
            $this->info("ℹ️ Thông tin role [$show]:");
            $this->line("- 🛡️ Guard: {$data['guard_name']}");
            $this->line("- 🔑 Permissions: " . implode(', ', $data['permissions'] ?? []));
        }
    }

    protected function displayResult(string $action, array $result): void
    {
        if (!empty($result['created'])) {
            $this->info("✅ $action thành công: " . implode(', ', $result['created']));
        }
        if (!empty($result['skipped'])) {
            $this->warn("⚠️ $action bị trùng (bỏ qua): " . implode(', ', $result['skipped']));
        }
        if (!empty($result['failed'])) {
            $this->error("❌ $action thất bại: " . implode(', ', $result['failed']));
        }
    }
}
