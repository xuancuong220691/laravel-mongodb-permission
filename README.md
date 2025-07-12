# MongoPermission – Role & Permission for MongoDB in Laravel

![Laravel](https://img.shields.io/badge/Laravel-10.x-orange)
![MongoDB](https://img.shields.io/badge/MongoDB-Compatible-green)
![License](https://img.shields.io/github/license/xuancuong220691/laravel-mongodb-permission)

MongoPermission is a library that extends Spatie's **Role & Permission** system to support **MongoDB**, with multi-guard support, easy-to-use syntax, and flexible expansion for Laravel applications.

---

## 🧾 Version Information

| Item                          | Requirement                                                                    |
| ----------------------------- | ------------------------------------------------------------------------------ |
| **Library Version**           | `v1.0.0` *(or your actual tag)*                                                |
| **Supported Laravel Version** | `^10.0`                                                                        |
| **MongoDB Laravel Driver**    | [`mongodb/laravel-mongodb`](https://github.com/mongodb/laravel-mongodb) `^3.9` |
| **MongoDB PHP Extension**     | `mongodb` PHP extension `>=1.13`                                               |
| **MongoDB Server**            | `>=4.0`                                                                        |
| **PHP Version**               | `>=8.0`                                                                        |

> 💡 Make sure to install MongoDB Laravel package:

```bash
composer require mongodb/laravel-mongodb
```

---

## ✅ Features

* Role and Permission support with MongoDB
* Multi-guard support: `web`, `admin`, etc.
* Middleware: `role`, `permission` (supports multiple roles/permissions)
* Extended Blade directives: `@role`, `@permission`, `@hasanyrole`, `@hasallroles`
* Powerful CLI command: `php artisan mp:manage`
* Easily extendable UI or integration into admin systems

---

## ⚙️ Installation

```bash
composer require cuongnx/laravel-mongodb-permission
```

---

## 🔧 MongoDB Configuration

**`.env`:**

```env
DB_CONNECTION=mongodb
DB_DATABASE=your_database
```

**`config/auth.php`:**

```php
'guards' => [
    'admin' => [
        'driver' => 'session',
        'provider' => 'admins',
    ],
],

'providers' => [
    'admins' => [
        'driver' => 'eloquent',
        'model' => App\Models\Admin::class,
    ],
],
```

---

## 🧩 Model Setup

```php
use CuongNX\MongoPermission\Traits\HasRoles;

class Admin extends Authenticatable
{
    use HasRoles;

    protected $guard_name = 'admin';
}
```

---

## 🛡 Middleware Usage

```php
Route::middleware(['auth:admin', 'role:admin|mod'])->group(function () {
    Route::get('/admin/dashboard', fn() => 'Welcome Admin');
});
```

* Middleware supports multiple roles/permissions:

```php
->middleware('role:admin|mod')
->middleware('permission:edit-users|delete-posts')
```

---

## 🎨 Blade Directives

```blade
@role('admin')
    <p>You are an Admin</p>
@endrole

@permission('edit-users')
    <p>You can edit users</p>
@endpermission

@hasanyrole('admin|mod')
    <p>You have at least one role</p>
@endhasanyrole

@hasallroles('admin|mod')
    <p>You have all the roles</p>
@endhasallroles
```

> Guard can be passed explicitly: `@role('admin', 'admin')`

---

## 🧠 CLI Usage

```bash
php artisan mp:manage --create-role=admin,mod --guard=admin
```

### 🎯 Available Options:

| Option                       | Description                                                 |               |
| ---------------------------- | ----------------------------------------------------------- | ------------- |
| `--create-role=`             | Create one or more roles                                    |               |
| `--delete-role=`             | Delete one or more roles                                    |               |
| `--create-permission=`       | Create one or more permissions                              |               |
| `--delete-permission=`       | Delete one or more permissions                              |               |
| `--assign-permission=`       | Assign permissions to a role. Format: \`role\:permission1   | permission2\` |
| `--list-roles`               | List all roles                                              |               |
| `--list-permissions`         | List all permissions                                        |               |
| `--guard=`                   | Guard name (default: `web`)                                 |               |
| `--reset`                    | Remove all roles and permissions                            |               |
| `--export=path/to/file.json` | Export all roles & permissions to a JSON file               |               |
| `--import=path/to/file.json` | Import from a JSON file                                     |               |
| `--sync-role-permissions=`   | Sync permissions for role from JSON file (`role:path.json`) |               |
| `--show-role=role`           | View detailed information about a role                      |               |

📌 **Example**:

```bash
php artisan mp:manage --create-role=admin,mod --create-permission=edit,delete --assign-permission=admin:edit|delete --guard=admin
```

---

## 📦 Export / Import JSON

```bash
php artisan mp:manage --export=storage/permissions.json
php artisan mp:manage --import=storage/permissions.json
php artisan mp:manage --sync-role-permissions=admin:storage/admin-perms.json
```

---

## 📂 Library Structure

```
src/
├── Console/
│   └── Commands/MongoPermissionManager.php
├── Middleware/
│   ├── RoleMiddleware.php
│   └── PermissionMiddleware.php
├── Models/
│   ├── Role.php
│   └── Permission.php
├── Services/
│   ├── Contracts/
│   │   └── PermissionServiceInterface.php
│   └── PermissionService.php
├── Support/
│   └── BladeDirectivesRegistrar.php
├── Traits/
│   └── HasRoles.php
└── Providers/
    └── MongoPermissionServiceProvider.php
```

---

## 💖 Donate

If you find this package useful, feel free to support the development:

### ☕ Coffee & Support

* [https://coff.ee/xuancuong2f](https://coff.ee/xuancuong2f)
* [https://paypal.me/cuongnx91](https://paypal.me/cuongnx91)

### 🏦 Bank (VIETQR)

![QR Code Techcombank](https://img.vietqr.io/image/970407-1368686856-print.png?accountName=Nguyen%20Xuan%20Cuong)


* **Account Holder**: NGUYEN XUAN CUONG
* **Account Number**: `1368686856`
* **Bank**: Techcombank

---

## 📬 Contact

* Email: [xuancuong220691@gmail.com](mailto:xuancuong220691@gmail.com)

---

## 🪪 License

MIT License © [Cuong Nguyen](mailto:xuancuong220691@gmail.com)
