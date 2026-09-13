<?php

namespace App\Repositories;

use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReportRepository
{
    public function all($userId)
    {
        $rolePermissions = DB::table('model_has_roles')
            ->join('role_has_permissions', 'model_has_roles.role_id', '=', 'role_has_permissions.role_id')
            ->join('permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
            ->where('model_has_roles.model_id', $userId)
            ->where('model_has_roles.model_type', \App\Models\User::class)
            ->pluck('permissions.name')
            ->toArray();

        // Combinar todos los permisos del usuario
        $allPermissions = array_unique($rolePermissions);

        // Filtrar los reportes por esos permisos
        return Report::select('id', 'name', 'order', 'background', 'color', 'permission')
            ->where('order', '>', 0)
            ->whereIn('permission', $allPermissions)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function findById($id)
    {
        return Report::select('id', 'name', 'sql', 'order')
                ->where('id', $id)
                ->first();
    }

    public function execute($sql)
    {
        DB::statement("SET time_zone = '-05:00'");
        $rows = DB::select($sql);
        return $rows;
    }
}
