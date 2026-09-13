<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class PosPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $group = Group::updateOrCreate(
            ['name' => 'POS'],
            [
                'icon' => 'point_of_sale',
                'label' => 'Punto de venta',
                'order' => 2,
            ]
        );

        $permissions = [
            ['name' => 'products.list', 'label' => 'Listar productos', 'subgroup' => 'Productos', 'route' => '/product', 'icon' => 'inventory_2', 'order' => 1],
            ['name' => 'products.view', 'label' => 'Ver productos', 'subgroup' => 'Productos', 'route' => '/product', 'icon' => 'inventory_2', 'order' => 1],
            ['name' => 'products.create', 'label' => 'Crear productos', 'subgroup' => 'Productos', 'route' => '/product', 'icon' => 'inventory_2', 'order' => 1],
            ['name' => 'products.edit', 'label' => 'Editar productos', 'subgroup' => 'Productos', 'route' => '/product', 'icon' => 'inventory_2', 'order' => 1],
            ['name' => 'products.delete', 'label' => 'Eliminar productos', 'subgroup' => 'Productos', 'route' => '/product', 'icon' => 'inventory_2', 'order' => 1],
            ['name' => 'products.allPlaces', 'label' => 'Productos de todas las sedes', 'subgroup' => 'Productos', 'route' => '/product', 'icon' => 'inventory_2', 'order' => 1],
            ['name' => 'purchases.list', 'label' => 'Listar compras', 'subgroup' => 'Compras', 'route' => '/purchase', 'icon' => 'local_shipping', 'order' => 2],
            ['name' => 'purchases.create', 'label' => 'Registrar compras', 'subgroup' => 'Compras', 'route' => '/purchase', 'icon' => 'local_shipping', 'order' => 2],
            ['name' => 'purchases.allPlaces', 'label' => 'Compras de todas las sedes', 'subgroup' => 'Compras', 'route' => '/purchase', 'icon' => 'local_shipping', 'order' => 2],
            ['name' => 'sales.list', 'label' => 'Listar ventas', 'subgroup' => 'Ventas', 'route' => '/sale', 'icon' => 'receipt_long', 'order' => 3],
            ['name' => 'sales.create', 'label' => 'Cobrar en caja', 'subgroup' => 'Caja', 'route' => '/caja', 'icon' => 'point_of_sale', 'order' => 4],
            ['name' => 'sales.allPlaces', 'label' => 'Ventas de todas las sedes', 'subgroup' => 'Ventas', 'route' => '/sale', 'icon' => 'receipt_long', 'order' => 3],
        ];

        foreach ($permissions as $row) {
            Permission::updateOrCreate(
                ['name' => $row['name'], 'guard_name' => 'api'],
                [
                    'icon' => $row['icon'],
                    'label' => $row['label'],
                    'order' => $row['order'],
                    'subgroup' => $row['subgroup'],
                    'group_id' => $group->id,
                    'route' => $row['route'],
                ]
            );
        }

        $caja = \App\Models\Role::updateOrCreate(
            ['name' => 'CAJA', 'guard_name' => 'api'],
            ['route' => '/caja']
        );
        $caja->syncPermissions([
            'sales.create',
            'products.list',
            'products.view',
        ]);
    }
}
