<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    protected $fillable = [
        'name', 'guard_name', 'label', 'subgroup', 'group_id', 'route', 'icon', 'order',
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }
}
