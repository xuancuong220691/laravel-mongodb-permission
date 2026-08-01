<?php

namespace CuongNX\LaravelMongoPermission\Console\Commands;

use CuongNX\LaravelMongoPermission\Filament\Support\ResourceDiscovery;
use CuongNX\LaravelMongoPermission\Models\Permission;
use Illuminate\Console\Command;

class MongoShieldGenerate extends Command
{
    protected $signature = 'mp:shield:generate
        {--panel=admin      : Filament panel ID để quét Resources}
        {--guard=admin      : Auth guard cho permissions}
        {--separator=.      : Ký tự ngăn cách giữa resource slug và action (mặc định: dấu chấm)}
        {--actions=view,create,update,delete : Danh sách actions, phân cách bằng dấu phẩy}
        {--pages            : Cũng sinh permissions cho standalone Pages}
        {--dry-run          : Chỉ hiển thị danh sách, không tạo vào DB}
        {--clean            : Xóa permissions trong DB không còn tồn tại trong panel nữa}
    ';

    protected $description = 'Tự động sinh Permissions từ Filament Resources & Pages cho MongoDB';

    public function handle(): int
    {
        $panelId   = $this->option('panel');
        $guard     = $this->option('guard');
        $separator = $this->option('separator');
        $actions   = array_values(array_filter(array_map('trim', explode(',', $this->option('actions')))));
        $withPages = (bool) $this->option('pages');
        $dryRun    = (bool) $this->option('dry-run');
        $clean     = (bool) $this->option('clean');

        if (!class_exists(\Filament\Facades\Filament::class)) {
            $this->error('filament/filament không được cài đặt. Chạy: composer require filament/filament');
            return self::FAILURE;
        }

        $this->info("🔍 Đang quét panel <fg=cyan>{$panelId}</> (guard: <fg=cyan>{$guard}</>)...");

        $groups = ResourceDiscovery::getResourceGroups($panelId, $actions, $separator);

        if (empty($groups)) {
            $this->error("Không tìm thấy Resource nào trong panel \"{$panelId}\".");
            $this->line('Lưu ý: panel phải được boot trước. Thử chạy sau khi app được serve.');
            return self::FAILURE;
        }

        $pagePerms = $withPages
            ? ResourceDiscovery::getPagePermissions($panelId, $separator)
            : [];

        // Display discovered resources
        $this->newLine();
        foreach ($groups as $groupLabel => $permissions) {
            $this->line("<fg=yellow>▸ {$groupLabel}</>");
            foreach ($permissions as $key => $label) {
                $this->line("    <fg=gray>{$key}</> → {$label}");
            }
        }

        if (!empty($pagePerms)) {
            $this->line('<fg=yellow>▸ Pages</>');
            foreach ($pagePerms as $key => $label) {
                $this->line("    <fg=gray>{$key}</> → {$label}");
            }
        }

        $allKeys = ResourceDiscovery::getAllPermissionKeys($panelId, $actions, $separator, $withPages);

        $this->newLine();
        $this->line('Tổng: <fg=cyan>' . count($allKeys) . '</> permissions từ <fg=cyan>' . count($groups) . '</> resources' . ($withPages && !empty($pagePerms) ? ' + ' . count($pagePerms) . ' pages' : '') . '.');

        if ($dryRun) {
            $this->newLine();
            $this->warn('Dry-run mode: không có thay đổi nào được ghi vào DB.');
            return self::SUCCESS;
        }

        if (!$this->confirm('Tiếp tục tạo permissions vào DB?', true)) {
            return self::SUCCESS;
        }

        // Create missing permissions
        $created = $skipped = 0;
        foreach ($allKeys as $key) {
            if (!Permission::where('name', $key)->where('guard_name', $guard)->exists()) {
                Permission::create(['name' => $key, 'guard_name' => $guard]);
                $this->line("  <fg=green>✓ created:</> {$key}");
                $created++;
            } else {
                $skipped++;
            }
        }

        // Optionally remove stale permissions
        $cleaned = 0;
        if ($clean) {
            $stale = Permission::where('guard_name', $guard)
                ->whereNotIn('name', $allKeys)
                ->get();

            foreach ($stale as $perm) {
                $this->line("  <fg=red>✗ removed:</> {$perm->name}");
                $perm->delete(); // fires cascade cleanup in ServiceProvider
                $cleaned++;
            }
        }

        $this->newLine();
        $this->info("✅ Hoàn tất: {$created} tạo mới · {$skipped} đã tồn tại" . ($clean ? " · {$cleaned} đã xóa" : '') . '.');

        return self::SUCCESS;
    }
}
