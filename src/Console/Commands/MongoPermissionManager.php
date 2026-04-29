<?php

namespace CuongNX\LaravelMongoPermission\Console\Commands;

use Illuminate\Console\Command;
use CuongNX\LaravelMongoPermission\Services\Contracts\PermissionServiceInterface;

class MongoPermissionManager extends Command
{
    protected $signature = 'mp:manage
        {--create-role= : Tạo 1 hoặc nhiều role, phân cách bằng dấu phẩy}
        {--delete-role= : Xoá 1 hoặc nhiều role, phân cách bằng dấu phẩy}
        {--create-permission= : Tạo 1 hoặc nhiều permission, phân cách bằng dấu phẩy}
        {--delete-permission= : Xoá 1 hoặc nhiều permission, phân cách bằng dấu phẩy}
        {--assign-permission= : Gán permission cho role, cú pháp role:perm1,perm2}
        {--revoke-permission= : Gỡ permission khỏi role, cú pháp role:perm1,perm2}
        {--guard=web : Guard đang dùng}
        {--list-roles : Liệt kê các roles}
        {--list-permissions : Liệt kê các permissions}
        {--reset : Xoá toàn bộ roles và permissions của guard hiện tại}
        {--reset-all : Xoá toàn bộ roles và permissions của mọi guard}
        {--export= : Xuất roles & permissions ra file JSON}
        {--import= : Nhập roles & permissions từ file JSON}
        {--sync-role-permissions= : Đồng bộ permission cho role, cú pháp role:path/to/file.json}
        {--show-role= : Xem chi tiết 1 role}
    ';

    protected $description = 'Quản lý roles & permissions cho MongoDB';

    public function handle(PermissionServiceInterface $permissionService): int
    {
        $guard = $this->option('guard') ?? 'web';

        if ($this->option('create-role')) {
            $result = $permissionService->createRoles($this->option('create-role'), $guard);
            $this->displayResult('Tạo role', $result);
        }

        if ($this->option('delete-role')) {
            $result = $permissionService->deleteRoles($this->option('delete-role'), $guard);
            $this->displayResult('Xoá role', $result);
        }

        if ($this->option('create-permission')) {
            $result = $permissionService->createPermissions($this->option('create-permission'), $guard);
            $this->displayResult('Tạo permission', $result);
        }

        if ($this->option('delete-permission')) {
            $result = $permissionService->deletePermissions($this->option('delete-permission'), $guard);
            $this->displayResult('Xoá permission', $result);
        }

        // Fix #1: validate format trước khi destructure
        if ($assign = $this->option('assign-permission')) {
            if (!str_contains($assign, ':')) {
                $this->error('Sai cú pháp. Đúng: --assign-permission=role:perm1,perm2');
                return self::FAILURE;
            }
            [$role, $perms] = explode(':', $assign, 2);
            $result = $permissionService->assignPermissions(trim($role), $perms, $guard);
            $this->displayResult("Gán permission cho role [$role]", $result);
        }

        // Fix #7: command cho revokePermissions
        if ($revoke = $this->option('revoke-permission')) {
            if (!str_contains($revoke, ':')) {
                $this->error('Sai cú pháp. Đúng: --revoke-permission=role:perm1,perm2');
                return self::FAILURE;
            }
            [$role, $perms] = explode(':', $revoke, 2);
            $result = $permissionService->revokePermissions(trim($role), $perms, $guard);
            $this->displayResult("Gỡ permission khỏi role [$role]", $result);
        }

        if ($this->option('list-roles')) {
            $roles = $permissionService->listRoles($guard);
            $this->info("Danh sách roles (guard: $guard):");
            foreach ($roles as $role) {
                $perms = implode(', ', $role['permissions'] ?? []);
                $this->line("  - {$role['name']}" . ($perms ? " [$perms]" : ''));
            }
        }

        if ($this->option('list-permissions')) {
            $perms = $permissionService->listPermissions($guard);
            $this->info("Danh sách permissions (guard: $guard):");
            foreach ($perms as $perm) {
                $this->line("  - {$perm['name']}");
            }
        }

        // Fix #4: --reset chỉ xóa guard hiện tại, --reset-all xóa toàn bộ
        if ($this->option('reset')) {
            if ($this->confirm("Xoá toàn bộ roles & permissions của guard [$guard]?")) {
                $permissionService->reset($guard);
                $this->warn("Đã xoá roles & permissions của guard [$guard].");
            }
        }

        if ($this->option('reset-all')) {
            if ($this->confirm('CẢNH BÁO: Xoá toàn bộ roles & permissions của MỌI guard?')) {
                $permissionService->reset();
                $this->warn('Đã xoá toàn bộ roles & permissions.');
            }
        }

        if ($exportPath = $this->option('export')) {
            $permissionService->exportToFile($exportPath, $guard);
            $this->info("Đã xuất file: $exportPath");
        }

        if ($importPath = $this->option('import')) {
            try {
                $result = $permissionService->importFromFile($importPath, $guard);
                $this->displayResult("Nhập từ $importPath", $result);
            } catch (\InvalidArgumentException $e) {
                $this->error($e->getMessage());
                return self::FAILURE;
            }
        }

        // Fix #1: validate format trước khi destructure
        if ($sync = $this->option('sync-role-permissions')) {
            if (!str_contains($sync, ':')) {
                $this->error('Sai cú pháp. Đúng: --sync-role-permissions=role:path/to/file.json');
                return self::FAILURE;
            }
            [$role, $file] = explode(':', $sync, 2);
            try {
                $result = $permissionService->syncRolePermissions(trim($role), $file, $guard);
                $this->displayResult("Đồng bộ permission cho role [$role]", $result);
            } catch (\InvalidArgumentException $e) {
                $this->error($e->getMessage());
                return self::FAILURE;
            }
        }

        if ($show = $this->option('show-role')) {
            $data = $permissionService->showRole($show, $guard);
            if (empty($data)) {
                $this->error("Role [$show] không tồn tại.");
                return self::FAILURE;
            }
            $this->info("Thông tin role [$show] (guard: {$data['guard_name']}):");
            $this->line('  Permissions: ' . (
                !empty($data['permissions'])
                    ? implode(', ', $data['permissions'])
                    : '(chưa có)'
            ));
        }

        return self::SUCCESS;
    }

    protected function displayResult(string $action, array $result): void
    {
        if (!empty($result['created'])) {
            $this->info("$action thành công: " . implode(', ', $result['created']));
        }
        if (!empty($result['synced'])) {
            $this->info("$action: " . implode(', ', $result['synced']));
        }
        if (!empty($result['deleted'])) {
            $this->info("$action: " . implode(', ', $result['deleted']));
        }
        if (!empty($result['assigned'])) {
            $this->info("$action: " . implode(', ', $result['assigned']));
        }
        if (!empty($result['revoked'])) {
            $this->info("$action: " . implode(', ', $result['revoked']));
        }
        if (!empty($result['skipped'])) {
            $this->warn("$action bị bỏ qua (đã tồn tại hoặc không hợp lệ): " . implode(', ', $result['skipped']));
        }
        if (!empty($result['failed'])) {
            $this->error("$action thất bại: " . implode(', ', $result['failed']));
        }
    }
}