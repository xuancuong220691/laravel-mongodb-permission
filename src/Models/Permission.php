<?php

namespace CuongNX\MongoPermission\Models;

use MongoDB\Laravel\Eloquent\Model;

class Permission extends Model
{
    protected $collection = 'permissions';

    protected $fillable = ['name', 'guard_name'];
}
