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
            ['name' => 'pos-dashboard.view', 'label' => 'Ver dashboard', 'subgroup' => 'Dashboard', 'route' => '/pos-dashboard', 'icon' => 'dashboard', 'order' => 0],
            ['name' => 'pos-dashboard.allPlaces', 'label' => 'Dashboard de todas las sedes', 'subgroup' => 'Dashboard', 'route' => '/pos-dashboard', 'icon' => 'dashboard', 'order' => 0],
            ['name' => 'products.list', 'label' => 'Listar productos', 'subgroup' => 'Productos', 'route' => '/product', 'icon' => 'inventory_2', 'order' => 1],
            ['name' => 'products.view', 'label' => 'Ver productos', 'subgroup' => 'Productos', 'route' => '/product', 'icon' => 'inventory_2', 'order' => 1],
            ['name' => 'products.create', 'label' => 'Crear productos', 'subgroup' => 'Productos', 'route' => '/product', 'icon' => 'inventory_2', 'order' => 1],
            ['name' => 'products.edit', 'label' => 'Editar productos', 'subgroup' => 'Productos', 'route' => '/product', 'icon' => 'inventory_2', 'order' => 1],
            ['name' => 'products.delete', 'label' => 'Eliminar productos', 'subgroup' => 'Productos', 'route' => '/product', 'icon' => 'inventory_2', 'order' => 1],
            ['name' => 'products.allPlaces', 'label' => 'Productos de todas las sedes', 'subgroup' => 'Productos', 'route' => '/product', 'icon' => 'inventory_2', 'order' => 1],
            ['name' => 'product-categories.list', 'label' => 'Listar categorías', 'subgroup' => 'Categorías', 'route' => '/product-category', 'icon' => 'category', 'order' => 1],
            ['name' => 'product-categories.create', 'label' => 'Crear categorías', 'subgroup' => 'Categorías', 'route' => '/product-category', 'icon' => 'category', 'order' => 1],
            ['name' => 'product-categories.edit', 'label' => 'Editar categorías', 'subgroup' => 'Categorías', 'route' => '/product-category', 'icon' => 'category', 'order' => 1],
            ['name' => 'product-categories.delete', 'label' => 'Eliminar categorías', 'subgroup' => 'Categorías', 'route' => '/product-category', 'icon' => 'category', 'order' => 1],
            ['name' => 'product-categories.allPlaces', 'label' => 'Categorías de todas las sedes', 'subgroup' => 'Categorías', 'route' => '/product-category', 'icon' => 'category', 'order' => 1],
            ['name' => 'dining-tables.attend', 'label' => 'Atender mesas', 'subgroup' => 'Atender mesas', 'route' => '/tables', 'icon' => 'table_restaurant', 'order' => 5],
            ['name' => 'dining-tables.list', 'label' => 'Listar mesas', 'subgroup' => 'Mesas', 'route' => '/dining-table', 'icon' => 'table_bar', 'order' => 1],
            ['name' => 'dining-tables.create', 'label' => 'Crear mesas', 'subgroup' => 'Mesas', 'route' => '/dining-table', 'icon' => 'table_bar', 'order' => 1],
            ['name' => 'dining-tables.edit', 'label' => 'Editar mesas', 'subgroup' => 'Mesas', 'route' => '/dining-table', 'icon' => 'table_bar', 'order' => 1],
            ['name' => 'dining-tables.delete', 'label' => 'Eliminar mesas', 'subgroup' => 'Mesas', 'route' => '/dining-table', 'icon' => 'table_bar', 'order' => 1],
            ['name' => 'dining-tables.allPlaces', 'label' => 'Mesas de todas las sedes', 'subgroup' => 'Mesas', 'route' => '/dining-table', 'icon' => 'table_bar', 'order' => 1],
            ['name' => 'dining-zones.list', 'label' => 'Listar zonas', 'subgroup' => 'Zonas', 'route' => '/dining-zone', 'icon' => 'map', 'order' => 1],
            ['name' => 'dining-zones.create', 'label' => 'Crear zonas', 'subgroup' => 'Zonas', 'route' => '/dining-zone', 'icon' => 'map', 'order' => 1],
            ['name' => 'dining-zones.edit', 'label' => 'Editar zonas', 'subgroup' => 'Zonas', 'route' => '/dining-zone', 'icon' => 'map', 'order' => 1],
            ['name' => 'dining-zones.delete', 'label' => 'Eliminar zonas', 'subgroup' => 'Zonas', 'route' => '/dining-zone', 'icon' => 'map', 'order' => 1],
            ['name' => 'dining-zones.allPlaces', 'label' => 'Zonas de todas las sedes', 'subgroup' => 'Zonas', 'route' => '/dining-zone', 'icon' => 'map', 'order' => 1],
            ['name' => 'purchases.list', 'label' => 'Listar compras', 'subgroup' => 'Compras', 'route' => '/purchase', 'icon' => 'local_shipping', 'order' => 2],
            ['name' => 'purchases.create', 'label' => 'Registrar compras', 'subgroup' => 'Compras', 'route' => '/purchase', 'icon' => 'local_shipping', 'order' => 2],
            ['name' => 'purchases.allPlaces', 'label' => 'Compras de todas las sedes', 'subgroup' => 'Compras', 'route' => '/purchase', 'icon' => 'local_shipping', 'order' => 2],
            ['name' => 'suppliers.list', 'label' => 'Listar proveedores', 'subgroup' => 'Proveedores', 'route' => '/supplier', 'icon' => 'handshake', 'order' => 2],
            ['name' => 'suppliers.create', 'label' => 'Crear proveedores', 'subgroup' => 'Proveedores', 'route' => '/supplier', 'icon' => 'handshake', 'order' => 2],
            ['name' => 'suppliers.edit', 'label' => 'Editar proveedores', 'subgroup' => 'Proveedores', 'route' => '/supplier', 'icon' => 'handshake', 'order' => 2],
            ['name' => 'suppliers.delete', 'label' => 'Eliminar proveedores', 'subgroup' => 'Proveedores', 'route' => '/supplier', 'icon' => 'handshake', 'order' => 2],
            ['name' => 'suppliers.allPlaces', 'label' => 'Proveedores de todas las sedes', 'subgroup' => 'Proveedores', 'route' => '/supplier', 'icon' => 'handshake', 'order' => 2],
            ['name' => 'customers.list', 'label' => 'Listar clientes', 'subgroup' => 'Clientes', 'route' => '/customer', 'icon' => 'groups', 'order' => 3],
            ['name' => 'customers.create', 'label' => 'Crear clientes', 'subgroup' => 'Clientes', 'route' => '/customer', 'icon' => 'groups', 'order' => 3],
            ['name' => 'customers.edit', 'label' => 'Editar clientes', 'subgroup' => 'Clientes', 'route' => '/customer', 'icon' => 'groups', 'order' => 3],
            ['name' => 'customers.delete', 'label' => 'Eliminar clientes', 'subgroup' => 'Clientes', 'route' => '/customer', 'icon' => 'groups', 'order' => 3],
            ['name' => 'customers.allPlaces', 'label' => 'Clientes de todas las sedes', 'subgroup' => 'Clientes', 'route' => '/customer', 'icon' => 'groups', 'order' => 3],
            ['name' => 'sales.list', 'label' => 'Listar ventas', 'subgroup' => 'Ventas', 'route' => '/sale', 'icon' => 'receipt_long', 'order' => 4],
            ['name' => 'sales.create', 'label' => 'Cobrar en caja', 'subgroup' => 'Caja', 'route' => '/register', 'icon' => 'point_of_sale', 'order' => 6],
            ['name' => 'sales.overridePrice', 'label' => 'Cambiar precio en venta', 'subgroup' => 'Caja', 'route' => '/register', 'icon' => 'point_of_sale', 'order' => 6],
            ['name' => 'sales.allPlaces', 'label' => 'Ventas de todas las sedes', 'subgroup' => 'Ventas', 'route' => '/sale', 'icon' => 'receipt_long', 'order' => 4],
            ['name' => 'register-sessions.list', 'label' => 'Historial de cierres', 'subgroup' => 'Cierres de caja', 'route' => '/register-session', 'icon' => 'account_balance_wallet', 'order' => 6],
            ['name' => 'register-sessions.allPlaces', 'label' => 'Cierres de todas las sedes', 'subgroup' => 'Cierres de caja', 'route' => '/register-session', 'icon' => 'account_balance_wallet', 'order' => 6],
            ['name' => 'inventory.list', 'label' => 'Ver inventario', 'subgroup' => 'Inventario', 'route' => '/inventory', 'icon' => 'warehouse', 'order' => 7],
            ['name' => 'inventory.adjust', 'label' => 'Ajustar inventario', 'subgroup' => 'Inventario', 'route' => '/inventory', 'icon' => 'warehouse', 'order' => 7],
            ['name' => 'inventory.allPlaces', 'label' => 'Inventario de todas las sedes', 'subgroup' => 'Inventario', 'route' => '/inventory', 'icon' => 'warehouse', 'order' => 7],
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
            ['route' => '/tables']
        );
        $caja->syncPermissions([
            'dining-tables.attend',
            'dining-tables.edit',
            'products.list',
            'products.view',
            'product-categories.list',
            'customers.list',
            'customers.create',
        ]);
    }
}
