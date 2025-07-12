<?php

namespace CuongNX\MongoPermission\Models;

use MongoDB\Laravel\Eloquent\Model;

class Role extends Model
{
    protected $collection = 'roles';

    protected $fillable = ['name', 'guard_name', 'permissions'];

    protected $casts = [
        'permissions' => 'array', // Mảng tên hoặc ID quyền
    ];
}
