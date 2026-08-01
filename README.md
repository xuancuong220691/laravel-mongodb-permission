# laravel-mongodb-permission

[![Packagist](https://img.shields.io/packagist/v/cuongnx/laravel-mongodb-permission)](https://packagist.org/packages/cuongnx/laravel-mongodb-permission)
[![Laravel](https://img.shields.io/badge/Laravel-11%20%7C%2012-orange)](https://laravel.com)
[![MongoDB](https://img.shields.io/badge/MongoDB-5.4+-green)](https://www.mongodb.com)
[![License](https://img.shields.io/badge/license-MIT-blue)](LICENSE)

Role & Permission system cho Laravel + MongoDB. Hỗ trợ đa guard, không cần SQL, lưu trữ hoàn toàn trên MongoDB. Tích hợp sẵn với **Filament v3/v4/v5** qua `MongoShieldPlugin`.

---

## Mục lục

- [Yêu cầu](#yêu-cầu)
- [Cài đặt](#cài-đặt)
- [Cấu trúc dữ liệu MongoDB](#cấu-trúc-dữ-liệu-mongodb)
- [Setup Model](#setup-model)
- [HasRoles API](#hasroles-api)
- [Middleware](#middleware)
- [Blade Directives](#blade-directives)
- [Artisan — mp:manage](#artisan--mpmanage)
- [PermissionService (DI)](#permissionservice-dependency-injection)
- [Cascade Cleanup](#cascade-cleanup)
- [Shield — Filament Integration](#shield--filament-integration)
  - [1. Đăng ký Plugin](#1-đăng-ký-plugin)
  - [2. Sinh Permissions tự động](#2-sinh-permissions-tự-động)
  - [3. Form UI cho RoleResource](#3-form-ui-cho-roleresource)
  - [4. Phân quyền cho Resources — Policy + Gate](#4-phân-quyền-cho-resources--policy--gatebefore)
  - [5. Phân quyền cho Pages — HasPageShield](#5-phân-quyền-cho-pages--haspageshield)
  - [6. Phân quyền cho Widgets — HasWidgetShield](#6-phân-quyền-cho-widgets--haswidgetshield)
  - [7. Permission naming convention](#7-permission-naming-convention)
- [License](#license)

---

## Yêu cầu

| | Phiên bản |
|---|---|
| PHP | `^8.1` |
| Laravel | `^11.0 \|\| ^12.0` |
| mongodb/laravel-mongodb | `^5.4` |
| filament/filament *(optional)* | `^3.0 \|\| ^4.0 \|\| ^5.0` |

---

## Cài đặt

```bash
composer require cuongnx/laravel-mongodb-permission
```

Service provider được tự động đăng ký qua Laravel package discovery.

Publish config:

```bash
php artisan vendor:publish --tag=mongo-permission
```

---

## Cấu hình

`config/mongo-permission.php` — khai báo các Model sử dụng `HasRoles` để thư viện tự động cascade cleanup khi xóa role/permission:

```php
return [
    'models' => [
        App\Models\Admin::class,
        App\Models\User::class,
    ],
];
```

---

## Cấu trúc dữ liệu MongoDB

```
Collection: roles
{ _id, name: "moderator", guard_name: "admin", permissions: ["users.view", "users.update"] }

Collection: permissions
{ _id, name: "users.view", guard_name: "admin" }

Collection: admins  (hoặc bất kỳ model nào dùng HasRoles)
{ ..., role_ids: ["<ObjectId>"], permission_ids: [] }
```

- `role.permissions` — lưu tên permission dạng string array (không dùng ObjectId)
- `admin.role_ids` — ObjectId string của các roles được gán
- `admin.permission_ids` — ObjectId string của các direct permissions (hiếm dùng)

---

## Setup Model

Gắn trait `HasRoles` vào model và khai báo `$guard_name`:

```php
use CuongNX\LaravelMongoPermission\Traits\HasRoles;

class Admin extends Authenticatable
{
    use HasRoles;

    protected $guard_name = 'admin';

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }
}
```

> `$guard_name` quyết định thư viện tìm role/permission theo guard nào. Nếu bỏ qua, mặc định dùng `config('auth.defaults.guard')`.

---

## HasRoles API

### Roles

```php
$admin->assignRole('moderator');                   // gán (bỏ qua nếu đã có)
$admin->removeRole('moderator');                   // gỡ
$admin->revokeRole('moderator');                   // alias của removeRole()
$admin->syncRoles(['moderator', 'editor']);         // thay toàn bộ

$admin->hasRole('moderator');                      // bool — cache per-request
$admin->hasAnyRole(['admin', 'editor']);            // bool — ít nhất 1
$admin->hasAllRoles(['admin', 'editor']);           // bool — phải có đủ

$admin->getRoleNames();                            // Collection<string>
```

### Permissions

```php
$admin->givePermissionTo('users.create');          // direct permission
$admin->revokePermissionTo('users.create');
$admin->syncPermissions(['users.view', 'users.update']);

$admin->hasPermissionTo('users.view');             // bool — direct OR via role, cache per-request
$admin->hasAnyPermission(['users.view', 'users.delete']);
$admin->hasAllPermissions(['users.view', 'users.update']);

$admin->getAllPermissions();                        // string[] — direct + via roles, unique
```

> **Cache:** Kết quả `hasRole`/`hasPermissionTo` được cache theo key `<model_id>:<type>:<name>` trong suốt vòng đời request. Tự xóa khi gọi bất kỳ method mutation nào.

---

## Middleware

```php
// Kiểm tra role — OR bằng dấu |
Route::middleware('role:super-admin')->...
Route::middleware('role:super-admin|moderator')->...

// Kiểm tra permission
Route::middleware('permission:users.view')->...
Route::middleware('permission:users.view|users.create')->...

// Chỉ định guard tường minh (tham số thứ 2)
Route::middleware('role:super-admin,admin')->...
Route::middleware('permission:users.view,admin')->...
```

---

## Blade Directives

```blade
@role('super-admin')
    Chỉ super-admin thấy
@endrole

@role('super-admin', 'admin')       {{-- với guard cụ thể --}}
    ...
@endrole

@permission('users.view')
    ...
@endpermission

@permission('users.view', 'admin')
    ...
@endpermission

@anyrole('super-admin', 'moderator')            {{-- default guard --}}
    ...
@endanyrole

@anyrolefor('admin', 'super-admin', 'moderator') {{-- guard tường minh --}}
    ...
@endanyrolefor

@anypermission('users.view', 'users.create')
    ...
@endanypermission

@anypermissionfor('admin', 'users.view', 'users.create')
    ...
@endanypermissionfor
```

---

## Artisan — mp:manage

```bash
php artisan mp:manage [options] [--guard=web]
```

### Roles

```bash
php artisan mp:manage --create-role=super-admin,moderator --guard=admin
php artisan mp:manage --delete-role=moderator --guard=admin
php artisan mp:manage --list-roles --guard=admin
php artisan mp:manage --show-role=moderator --guard=admin
```

### Permissions

```bash
php artisan mp:manage --create-permission=users.view,users.create,users.update,users.delete --guard=admin
php artisan mp:manage --delete-permission=users.delete --guard=admin
php artisan mp:manage --list-permissions --guard=admin
```

### Gán / Gỡ

```bash
# cú pháp: role:perm1,perm2
php artisan mp:manage --assign-permission=moderator:users.view,users.update --guard=admin
php artisan mp:manage --revoke-permission=moderator:users.update --guard=admin
```

### Export / Import

```bash
php artisan mp:manage --export=storage/permissions.json --guard=admin
php artisan mp:manage --import=storage/permissions.json --guard=admin
```

Format file JSON:

```json
{
    "permissions": [
        { "name": "users.view", "guard_name": "admin" }
    ],
    "roles": [
        { "name": "moderator", "guard_name": "admin", "permissions": ["users.view", "users.update"] }
    ]
}
```

### Sync permissions từ file

```bash
# cú pháp: role:path/to/file.json
php artisan mp:manage --sync-role-permissions=moderator:storage/moderator.json --guard=admin
```

### Reset

```bash
php artisan mp:manage --reset --guard=admin    # xóa guard này (fire events → cascade)
php artisan mp:manage --reset-all               # truncate toàn bộ mọi guard (không fire events)
```

---

## PermissionService (Dependency Injection)

```php
use CuongNX\LaravelMongoPermission\Services\Contracts\PermissionServiceInterface;

class RoleController extends Controller
{
    public function __construct(private PermissionServiceInterface $permissions) {}

    public function store()
    {
        $this->permissions->createRoles('moderator,editor', 'admin');
        $this->permissions->assignPermissions('moderator', 'users.view,users.update', 'admin');
    }
}
```

| Method | Mô tả |
|---|---|
| `createRoles(string, string)` | Tạo roles, bỏ qua nếu đã tồn tại |
| `deleteRoles(string, string)` | Xóa roles (cascade cleanup) |
| `createPermissions(string, string)` | Tạo permissions |
| `deletePermissions(string, string)` | Xóa permissions (cascade cleanup) |
| `assignPermissions(string $role, string $perms, string $guard)` | Gán permissions vào role |
| `revokePermissions(string $role, string $perms, string $guard)` | Gỡ bớt permissions khỏi role |
| `listRoles(string)` | Danh sách roles |
| `listPermissions(string)` | Danh sách permissions |
| `showRole(string, string)` | Chi tiết 1 role |
| `exportToFile(string $path, ?string $guard)` | Xuất JSON |
| `importFromFile(string $path, string $guard)` | Nhập JSON |
| `syncRolePermissions(string $role, string $jsonPath, string $guard)` | Sync từ file |
| `reset(?string $guard)` | Xóa guard (events) hoặc truncate toàn bộ |

Kết quả array có keys: `created`, `skipped`, `deleted`, `assigned`, `revoked`, `synced`, `failed`.

---

## Cascade Cleanup

Khi **xóa Role**: tự động xóa `role_ids` tương ứng khỏi tất cả user documents trong `config('mongo-permission.models')`.

Khi **xóa Permission**: tự động xóa permission name khỏi `permissions[]` của tất cả Role documents, và xóa `permission_ids` khỏi user documents.

> `reset($guard)` xóa từng document → fire model events → cascade cleanup chạy bình thường.  
> `reset()` không tham số dùng `truncate()` — nhanh hơn nhưng **không** fire events và xóa **toàn bộ mọi guard**.

---

## Shield — Filament Integration

> **Yêu cầu:** `filament/filament ^3.0|^4.0|^5.0`

Shield tích hợp thư viện với Filament admin panel, tương tự `bezhansalleh/filament-shield` nhưng dành cho MongoDB:

- **Auto-generate permissions** từ Resources, Pages, Widgets đăng ký trong panel
- **Form UI dạng Tabs** cho RoleResource (Tài nguyên / Trang / Widget)
- **`HasPageShield` / `HasWidgetShield` traits** — Page và Widget tự kiểm tra quyền qua Gate
- **`Gate::before()` cho super-admin** — bypass toàn bộ checks, không cần gán permission
- **Policy generator** — tự sinh Laravel Policy files từ stub
- **Artisan `mp:shield:generate`** — đồng bộ permissions vào MongoDB

### 1. Đăng ký Plugin

```php
// app/Providers/Filament/AdminPanelProvider.php
use CuongNX\LaravelMongoPermission\Filament\MongoShieldPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            MongoShieldPlugin::make()
                ->panelId('admin')
                ->superAdminRole('super-admin')
                ->withPagePermissions()
                ->withWidgetPermissions(),
        ]);
}
```

#### Tùy chọn plugin

| Method | Mặc định | Mô tả |
|---|---|---|
| `->panelId(string)` | `'admin'` | Filament panel ID để quét Resources |
| `->resourceActions(array)` | `['view','create','update','delete']` | Actions sinh per-resource |
| `->separator(string)` | `'.'` | Ký tự ngăn cách (e.g. `users.view`) |
| `->superAdminRole(string)` | `'super-admin'` | Role bypass tất cả permission checks qua `Gate::before()` |
| `->withPagePermissions()` | `false` | Sinh thêm permissions cho standalone Pages |
| `->withWidgetPermissions()` | `false` | Sinh thêm permissions cho Widgets |

### 2. Sinh Permissions tự động

```bash
# Resources + Pages + Widgets, xóa stale, sinh Policy files
php artisan mp:shield:generate \
    --panel=admin --guard=admin \
    --pages --widgets --policies --clean

# Xem trước, không ghi
php artisan mp:shield:generate --dry-run

# Chỉ Resources
php artisan mp:shield:generate --panel=admin --guard=admin
```

Ví dụ output:

```
▸ Người dùng
    users.view → Xem
    users.create → Tạo
    users.update → Sửa
    users.delete → Xóa
▸ Pages
    page.lvcoin-adjustment → Điều chỉnh LVcoin
▸ Widgets
    widget.stats-overview → Stats Overview

Tổng: 42 permissions từ 10 resources + 1 pages + 1 widgets.
✅ Permissions: 42 tạo mới · 0 đã tồn tại | Policies: 10 tạo mới · 0 bỏ qua.
```

### 3. Form UI cho RoleResource

Dùng trait `HasShieldFormComponents` trong `RoleResource`:

```php
use CuongNX\LaravelMongoPermission\Filament\Traits\HasShieldFormComponents;

class RoleResource extends Resource
{
    use HasShieldFormComponents;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Tên vai trò')->required(),
            Select::make('guard_name')
                ->options(['admin' => 'Admin Panel'])
                ->default('admin'),
            static::getShieldFormComponents(),
        ]);
    }
}
```

Form hiển thị dạng **Tabs**:
- **Tài nguyên** — collapsible Section per resource, checkboxes Xem/Tạo/Sửa/Xóa, badge = tổng permissions
- **Trang** — CheckboxList phẳng tất cả Pages, badge = số Pages
- **Widget** — CheckboxList phẳng tất cả Widgets, badge = số Widgets
- Toggle **"Chọn tất cả"** ở trên đầu — check/uncheck toàn bộ 3 tab cùng lúc

**Cơ chế hoạt động:**
- Mỗi group/tab dùng `CheckboxList` synthetic (`__shield_*`) với `dehydrated(false)`
- `afterStateHydrated` — filter `role.permissions` cho từng group khi load
- `afterStateUpdated` — merge tất cả groups vào `Hidden('permissions')`
- Filament lưu `Hidden('permissions')` vào `role.permissions` — không cần override gì thêm

### 4. Phân quyền cho Resources — Policy + Gate::before

Cách khuyến nghị: dùng **Policy + `Gate::before()`** thay vì `canAccess()` thủ công.

**Bước 1:** Chạy `--policies` để sinh Policy files:

```bash
php artisan mp:shield:generate --policies
```

Policy được sinh tự động vào `app/Policies/UserPolicy.php`:

```php
class UserPolicy
{
    public function viewAny(AuthUser $user): bool {
        return method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo('users.view');
    }
    public function view(AuthUser $user, User $model): bool { ... }
    public function create(AuthUser $user): bool { ... }
    public function update(AuthUser $user, User $model): bool { ... }
    public function delete(AuthUser $user, User $model): bool { ... }
}
```

**Bước 2:** Xóa `canAccess()` khỏi Resource — Filament tự gọi `Gate::allows('viewAny', $model)` → Policy:

```php
// Không cần canAccess() — Policy xử lý tự động
class UserResource extends Resource
{
    protected static ?string $model = User::class;
    // ...
}
```

**Super-admin bypass:** `MongoShieldPlugin` đăng ký `Gate::before()` khi boot — super-admin tự động pass mọi check mà không cần gán permission.

### 5. Phân quyền cho Pages — `HasPageShield`

Thay vì viết `canAccess()` thủ công, dùng trait:

```php
use CuongNX\LaravelMongoPermission\Filament\Traits\HasPageShield;

class LvcoinAdjustment extends Page
{
    use HasPageShield;
    // canAccess() tự động: Gate::allows('page.lvcoin-adjustment')
    // Super-admin bypass qua Gate::before()
}
```

Trait tự động derive permission name từ class name: `LvcoinAdjustment` → `page.lvcoin-adjustment`.

### 6. Phân quyền cho Widgets — `HasWidgetShield`

```php
use CuongNX\LaravelMongoPermission\Filament\Traits\HasWidgetShield;

class StatsOverviewWidget extends Widget
{
    use HasWidgetShield;
    // canView() tự động: Gate::allows('widget.stats-overview-widget')
}
```

### 7. Permission naming convention

| Loại | Class | Permission |
|---|---|---|
| Resource | `App\Models\User` | `users.view`, `users.create`, `users.update`, `users.delete` |
| Resource | `App\Models\OAuthClient` | `oauth-clients.view`, ... |
| Page | `LvcoinAdjustment` | `page.lvcoin-adjustment` |
| Widget | `StatsOverviewWidget` | `widget.stats-overview-widget` |

- Resource slug: `Str::plural(Str::kebab(class_basename($modelClass)))`
- Page/Widget slug: `Str::kebab(class_basename($class))`

---

## License

MIT © [Cuong Nguyen](mailto:xuancuong220691@gmail.com)
