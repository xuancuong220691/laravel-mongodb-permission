# laravel-mongodb-permission

[![Laravel](https://img.shields.io/badge/Laravel-11%20%7C%2012-orange)](https://laravel.com)
[![MongoDB](https://img.shields.io/badge/MongoDB-5.4+-green)](https://www.mongodb.com)
[![License](https://img.shields.io/badge/license-MIT-blue)](LICENSE)

Role & Permission system cho Laravel + MongoDB. Hỗ trợ đa guard, không cần SQL, lưu trữ hoàn toàn trên MongoDB.

---

## Yêu cầu

| | Phiên bản |
|---|---|
| PHP | `^8.1` |
| Laravel | `^11.0 \|\| ^12.0` |
| mongodb/laravel-mongodb | `^5.4` |

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

`config/mongo-permission.php` — khai báo các Model sử dụng `HasRoles` để thư viện tự động cleanup khi xóa role/permission:

```php
return [
    'models' => [
        App\Models\Admin::class,
        App\Models\User::class,
    ],
];
```

---

## Setup Model

Gắn trait `HasRoles` vào model và khai báo `$guard_name`:

```php
use CuongNX\LaravelMongoPermission\Traits\HasRoles;

class Admin extends Authenticatable
{
    use HasRoles;

    protected $guard_name = 'admin'; // phải khai báo đúng guard
}
```

> **Lưu ý:** `$guard_name` quyết định thư viện tìm role/permission theo guard nào. Nếu bỏ qua, mặc định dùng `config('auth.defaults.guard')`.

---

## Cấu trúc dữ liệu MongoDB

```
Collection: roles
{ _id, name: "super-admin", guard_name: "admin", permissions: ["manage-users", "view-logs"] }

Collection: permissions
{ _id, name: "manage-users", guard_name: "admin" }

Collection: admins (hoặc bất kỳ model nào dùng HasRoles)
{ ..., role_ids: ["<ObjectId>", ...], permission_ids: ["<ObjectId>", ...] }
```

- Role lưu danh sách permission **theo tên** (string array) — không dùng ObjectId
- User lưu danh sách role/permission **theo ObjectId** (string)

---

## HasRoles API

### Roles

```php
// Gán role (bỏ qua nếu đã có)
$admin->assignRole('moderator');

// Gỡ role
$admin->removeRole('moderator');
$admin->revokeRole('moderator');  // alias của removeRole()

// Thay toàn bộ roles (xóa cũ, set mới)
$admin->syncRoles(['super-admin', 'moderator']);

// Kiểm tra
$admin->hasRole('moderator');              // bool — có cache
$admin->hasAnyRole(['admin', 'editor']);   // bool — có ít nhất 1
$admin->hasAllRoles(['admin', 'editor']);  // bool — phải có đủ tất cả

// Lấy danh sách tên
$admin->getRoleNames(); // Illuminate\Support\Collection
```

### Direct Permissions

```php
// Cấp permission trực tiếp cho user
$admin->givePermissionTo('edit-users');

// Thu hồi từng permission
$admin->revokePermissionTo('edit-users');

// Thay toàn bộ direct permissions (xóa cũ, set mới)
$admin->syncPermissions(['edit-users', 'view-logs']);

// Kiểm tra (direct permission HOẶC qua role — có cache)
$admin->hasPermissionTo('edit-users');                    // bool
$admin->hasAnyPermission(['edit-users', 'delete-posts']); // bool — có ít nhất 1
$admin->hasAllPermissions(['edit-users', 'view-logs']);   // bool — phải có đủ tất cả

// Lấy toàn bộ tên permissions (direct + via roles, unique)
$admin->getAllPermissions(); // string[]
```

> **Cache:** Kết quả `hasRole` và `hasPermissionTo` được cache trong static array theo key `<model_id>:<type>:<name>` trong suốt vòng đời request. Cache tự xóa khi gọi bất kỳ method mutation nào.

---

## Middleware

Middleware `role` và `permission` được đăng ký tự động:

```php
// Kiểm tra role — dùng OR với dấu |
Route::middleware('role:super-admin')->...
Route::middleware('role:super-admin|moderator')->...

// Kiểm tra permission
Route::middleware('permission:edit-users')->...
Route::middleware('permission:edit-users|delete-posts')->...

// Chỉ định guard tường minh
Route::middleware('role:super-admin,admin')->...
Route::middleware('permission:edit-users,admin')->...
```

---

## Blade Directives

```blade
{{-- Kiểm tra role (dùng default guard) --}}
@role('super-admin')
    Chỉ super-admin thấy
@endrole

{{-- Kiểm tra role với guard cụ thể --}}
@role('super-admin', 'admin')
    ...
@endrole

{{-- Kiểm tra permission --}}
@permission('edit-users')
    ...
@endpermission

@permission('edit-users', 'admin')
    ...
@endpermission

{{-- Có ít nhất 1 role (dùng default guard) --}}
@anyrole('super-admin', 'moderator', 'editor')
    ...
@endanyrole

{{-- Có ít nhất 1 role với guard cụ thể --}}
@anyrolefor('admin', 'super-admin', 'moderator')
    ...
@endanyrolefor

{{-- Có ít nhất 1 permission (dùng default guard) --}}
@anypermission('edit-users', 'delete-posts', 'view-logs')
    ...
@endanypermission

{{-- Có ít nhất 1 permission với guard cụ thể --}}
@anypermissionfor('admin', 'edit-users', 'delete-posts')
    ...
@endanypermissionfor
```

> **Lưu ý `@anyrole` vs `@anyrolefor`:** `@anyrole('r1', 'r2')` — tất cả tham số đều là tên role, dùng default guard. `@anyrolefor('guard', 'r1', 'r2')` — tham số đầu là guard, phần còn lại là role names. Tương tự với `@anypermission`/`@anypermissionfor`.

---

## Artisan Command

```bash
php artisan mp:manage [options] [--guard=web]
```

### Roles

```bash
# Tạo roles (nhiều role phân cách bằng dấu phẩy)
php artisan mp:manage --create-role=super-admin,moderator --guard=admin

# Xóa roles
php artisan mp:manage --delete-role=moderator --guard=admin

# Liệt kê
php artisan mp:manage --list-roles --guard=admin

# Xem chi tiết 1 role
php artisan mp:manage --show-role=super-admin --guard=admin
```

### Permissions

```bash
# Tạo permissions
php artisan mp:manage --create-permission=manage-users,view-logs,edit-posts --guard=admin

# Xóa permissions
php artisan mp:manage --delete-permission=edit-posts --guard=admin

# Liệt kê
php artisan mp:manage --list-permissions --guard=admin
```

### Gán / Gỡ permissions của role

```bash
# Thêm permissions vào role — cú pháp: role:perm1,perm2
php artisan mp:manage --assign-permission=super-admin:manage-users,view-logs --guard=admin

# Gỡ bớt permissions khỏi role (không ảnh hưởng permissions còn lại)
php artisan mp:manage --revoke-permission=super-admin:view-logs --guard=admin
```

### Export / Import

```bash
# Xuất toàn bộ ra JSON (tự tạo thư mục nếu chưa có)
php artisan mp:manage --export=storage/permissions.json

# Xuất theo guard cụ thể
php artisan mp:manage --export=storage/admin-permissions.json --guard=admin

# Nhập từ JSON (tự validate permissions trước khi gán vào role)
php artisan mp:manage --import=storage/permissions.json --guard=admin
```

Định dạng file JSON:

```json
{
    "permissions": [
        { "name": "manage-users", "guard_name": "admin" },
        { "name": "view-logs", "guard_name": "admin" }
    ],
    "roles": [
        { "name": "super-admin", "guard_name": "admin", "permissions": ["manage-users", "view-logs"] },
        { "name": "moderator", "guard_name": "admin", "permissions": ["view-logs"] }
    ]
}
```

### Sync permissions từ file

```bash
# Thay toàn bộ permissions của 1 role bằng nội dung từ file JSON
# Cú pháp: role:path/to/file.json
php artisan mp:manage --sync-role-permissions=super-admin:storage/super-admin.json --guard=admin
```

File JSON chỉ cần có key `permissions`:

```json
{
    "permissions": ["manage-users", "view-logs", "edit-posts"]
}
```

### Reset

```bash
# Xóa roles & permissions của guard hiện tại (fire model events → cascade cleanup)
php artisan mp:manage --reset --guard=admin

# Xóa toàn bộ mọi guard bằng truncate (nhanh, không fire events)
php artisan mp:manage --reset-all
```

---

## PermissionService (Dependency Injection)

Inject `PermissionServiceInterface` để dùng trong code:

```php
use CuongNX\LaravelMongoPermission\Services\Contracts\PermissionServiceInterface;

class RoleController extends Controller
{
    public function __construct(private PermissionServiceInterface $permissions) {}

    public function store(Request $request)
    {
        $this->permissions->createRoles('admin,editor', 'web');
        $this->permissions->assignPermissions('admin', 'edit-posts,delete-posts', 'web');
    }
}
```

| Method | Trả về | Mô tả |
|---|---|---|
| `createRoles(string $roles, string $guard)` | `array` | Tạo roles, bỏ qua nếu đã tồn tại |
| `deleteRoles(string $roles, string $guard)` | `array` | Xóa roles (fire model events → cascade cleanup) |
| `createPermissions(string $perms, string $guard)` | `array` | Tạo permissions |
| `deletePermissions(string $perms, string $guard)` | `array` | Xóa permissions (cascade cleanup) |
| `assignPermissions(string $role, string $perms, string $guard)` | `array` | Gán permissions vào role |
| `revokePermissions(string $role, string $perms, string $guard)` | `array` | Gỡ bớt permissions khỏi role |
| `listRoles(string $guard)` | `array` | Danh sách roles |
| `listPermissions(string $guard)` | `array` | Danh sách permissions |
| `showRole(string $name, string $guard)` | `array` | Chi tiết 1 role |
| `exportToFile(string $path, ?string $guard)` | `void` | Xuất JSON |
| `importFromFile(string $path, string $guard)` | `array` | Nhập JSON |
| `syncRolePermissions(string $role, string $jsonPath, string $guard)` | `array` | Sync permissions từ file |
| `reset(?string $guard)` | `void` | Xóa theo guard (fire events) hoặc truncate toàn bộ nếu không truyền guard |

Kết quả trả về `array` có các key: `created`, `skipped`, `deleted`, `synced`, `failed`.

---

## Cascade Cleanup

Khi **xóa Role**, thư viện tự động:
- Xóa `role_ids` tương ứng khỏi tất cả user documents trong `config('mongo-permission.models')`

Khi **xóa Permission**, thư viện tự động:
- Xóa permission name khỏi `permissions[]` của tất cả Role documents
- Xóa `permission_ids` tương ứng khỏi tất cả user documents

> `reset($guard)` xóa từng document và fire model events → cascade cleanup chạy bình thường. `reset()` không tham số dùng `truncate()` — nhanh hơn nhưng **không** fire events và xóa **toàn bộ mọi guard**.

---

## Sử dụng với Filament

Ví dụ kiểm tra quyền trong Filament Resource:

```php
public static function canAccess(): bool
{
    $user = Filament::auth()->user();
    return $user?->isSuperAdmin() || $user?->hasPermissionTo('manage-admins');
}
```

Ví dụ gán role sau khi tạo user:

```php
protected function afterCreate(): void
{
    if ($role = $this->data['role'] ?? null) {
        $this->record->assignRole($role);
    }
}
```

Ví dụ sync role khi edit user:

```php
protected function afterSave(): void
{
    $this->record->role_ids = [];
    $this->record->save();

    if ($role = $this->data['role'] ?? null) {
        $this->record->assignRole($role);
    }
}
```

---

## License

MIT © [Cuong Nguyen](mailto:xuancuong220691@gmail.com)