<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AdminRoleSeeder extends Seeder
{
    /**
     * Seed a single administrator role.
     */
    public function run(): void
    {
        $role = Role::updateOrCreate(
            [
                'name' => 'ADMINISTRADOR',
                'guard_name' => 'api',
            ],
            [
                'route' => '/home',
            ]
        );

        // Give administrator role every currently seeded permission.
        $role->syncPermissions(Permission::pluck('name')->all());
    }
}
