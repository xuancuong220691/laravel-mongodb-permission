<?php

namespace CuongNX\LaravelMongoPermission\Console\Commands;

use CuongNX\LaravelMongoPermission\Filament\Support\ResourceDiscovery;
use CuongNX\LaravelMongoPermission\Models\Permission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MongoShieldGenerate extends Command
{
    protected $signature = 'mp:shield:generate
        {--panel=admin      : Filament panel ID để quét Resources}
        {--guard=admin      : Auth guard cho permissions}
        {--separator=.      : Ký tự ngăn cách giữa resource slug và action (mặc định: dấu chấm)}
        {--actions=view,create,update,delete : Danh sách actions, phân cách bằng dấu phẩy}
        {--pages            : Cũng sinh permissions cho standalone Pages}
        {--policies         : Sinh Policy files vào app/Policies/ (cho phép bỏ canAccess thủ công)}
        {--dry-run          : Chỉ hiển thị danh sách, không tạo vào DB hay file system}
        {--clean            : Xóa permissions trong DB không còn tồn tại trong panel nữa}
    ';

    protected $description = 'Tự động sinh Permissions (+ Policy files) từ Filament Resources & Pages cho MongoDB';

    public function handle(): int
    {
        $panelId      = $this->option('panel');
        $guard        = $this->option('guard');
        $separator    = $this->option('separator');
        $actions      = array_values(array_filter(array_map('trim', explode(',', $this->option('actions')))));
        $withPages    = (bool) $this->option('pages');
        $withPolicies = (bool) $this->option('policies');
        $dryRun       = (bool) $this->option('dry-run');
        $clean        = (bool) $this->option('clean');

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

        // ── Hiển thị danh sách discover ───────────────────────────────────────
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
        $this->line(
            'Tổng: <fg=cyan>' . count($allKeys) . '</> permissions từ <fg=cyan>' . count($groups) . '</> resources'
            . ($withPages && !empty($pagePerms) ? ' + ' . count($pagePerms) . ' pages' : '')
            . '.'
        );

        if ($dryRun) {
            $this->newLine();
            $this->warn('Dry-run mode — không có thay đổi nào được ghi.');

            if ($withPolicies) {
                $this->newLine();
                $this->line('Policy files sẽ được tạo tại:');
                foreach ($this->resolvePolicyTargets($panelId) as $modelClass => $policyPath) {
                    $exists = File::exists($policyPath) ? '<fg=yellow>[exists]</>' : '<fg=green>[new]</>';
                    $this->line("  {$exists} {$policyPath}");
                }
            }

            return self::SUCCESS;
        }

        if (!$this->confirm('Tiếp tục tạo permissions vào DB?', true)) {
            return self::SUCCESS;
        }

        // ── Tạo permissions ───────────────────────────────────────────────────
        $this->newLine();
        $this->line('<fg=cyan>── Permissions ──</>');

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

        // ── Clean stale permissions ───────────────────────────────────────────
        $cleaned = 0;
        if ($clean) {
            $this->newLine();
            $this->line('<fg=cyan>── Clean stale permissions ──</>');
            $stale = Permission::where('guard_name', $guard)
                ->whereNotIn('name', $allKeys)
                ->get();

            foreach ($stale as $perm) {
                $this->line("  <fg=red>✗ removed:</> {$perm->name}");
                $perm->delete();
                $cleaned++;
            }

            if ($cleaned === 0) {
                $this->line('  (không có stale permissions)');
            }
        }

        // ── Sinh Policy files ─────────────────────────────────────────────────
        $policiesCreated = $policiesSkipped = 0;
        if ($withPolicies) {
            $this->newLine();
            $this->line('<fg=cyan>── Policy files ──</>');

            $stub = File::get(__DIR__ . '/../../Filament/Stubs/policy.stub');

            foreach ($this->resolvePolicyTargets($panelId) as $modelClass => $policyPath) {
                if (File::exists($policyPath)) {
                    $this->line("  <fg=yellow>~ skipped (exists):</> {$policyPath}");
                    $policiesSkipped++;
                    continue;
                }

                $modelName = class_basename($modelClass);
                $slug      = ResourceDiscovery::modelSlug($modelClass);
                $content   = str_replace(
                    ['{{ModelName}}', '{{slug}}'],
                    [$modelName,      $slug],
                    $stub
                );

                File::ensureDirectoryExists(dirname($policyPath));
                File::put($policyPath, $content);
                $this->line("  <fg=green>✓ created:</> {$policyPath}");
                $policiesCreated++;
            }
        }

        // ── Summary ───────────────────────────────────────────────────────────
        $this->newLine();
        $this->info(
            '✅ Permissions: ' . $created . ' tạo mới · ' . $skipped . ' đã tồn tại'
            . ($clean ? ' · ' . $cleaned . ' đã xóa' : '')
            . ($withPolicies ? ' | Policies: ' . $policiesCreated . ' tạo mới · ' . $policiesSkipped . ' bỏ qua' : '')
            . '.'
        );

        if ($withPolicies && $policiesCreated > 0) {
            $this->newLine();
            $this->line('<fg=gray>Policy files đã được tạo. Laravel tự auto-discover khi model và policy cùng namespace convention.</>');
            $this->line('<fg=gray>Nếu cần đăng ký thủ công, thêm vào AppServiceProvider::$policies hoặc AuthServiceProvider.</>');
        }

        return self::SUCCESS;
    }

    /**
     * Map model class → absolute path of Policy file to generate.
     * App\Models\User → app_path('Policies/UserPolicy.php')
     */
    private function resolvePolicyTargets(string $panelId): array
    {
        $targets = [];

        try {
            $resources = \Filament\Facades\Filament::getPanel($panelId)->getResources();
        } catch (\Throwable) {
            return [];
        }

        foreach ($resources as $resourceClass) {
            try {
                $modelClass  = $resourceClass::getModel();
                $modelName   = class_basename($modelClass);
                $policyClass = $modelName . 'Policy';
                $policyPath  = app_path('Policies/' . $policyClass . '.php');
                $targets[$modelClass] = $policyPath;
            } catch (\Throwable) {
                continue;
            }
        }

        return $targets;
    }
}
