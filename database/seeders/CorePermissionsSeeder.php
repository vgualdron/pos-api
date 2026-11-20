<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

class CorePermissionsSeeder extends Seeder
{
    /**
     * Seed only permissions that still match active modules/routes.
     */
    public function run(): void
    {
        $groups = [];

        $groups['organizations'] = Group::updateOrCreate(
            ['name' => 'Organizations'],
            [
                'icon' => 'corporate_fare',
                'label' => 'Modulo de organización',
                'order' => 1,
            ]
        );

        $groups['security'] = Group::updateOrCreate(
            ['name' => 'Security'],
            [
                'icon' => 'security',
                'label' => 'Modulo de Seguridad',
                'order' => 0,
            ]
        );

        $groups['informes'] = Group::updateOrCreate(
            ['name' => 'Informes'],
            [
                'icon' => 'assessment',
                'label' => 'Modulo de Informes',
                'order' => 4,
            ]
        );

        $permissions = [
            [
                'name' => 'users.view',
                'icon' => 'person',
                'label' => 'Ver usuarios',
                'order' => 7,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar usuarios',
                'group_key' => 'security',
                'route' => '/user',
            ],
            [
                'name' => 'users.list',
                'icon' => 'person',
                'label' => 'Listar usuarios',
                'order' => 7,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar usuarios',
                'group_key' => 'security',
                'route' => '/user',
            ],
            [
                'name' => 'users.create',
                'icon' => 'person',
                'label' => 'Crear usuarios',
                'order' => 7,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar usuarios',
                'group_key' => 'security',
                'route' => '/user',
            ],
            [
                'name' => 'users.edit',
                'icon' => 'person',
                'label' => 'Modificar usuarios',
                'order' => 7,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar usuarios',
                'group_key' => 'security',
                'route' => '/user',
            ],
            [
                'name' => 'users.delete',
                'icon' => 'person',
                'label' => 'Eliminar usuarios',
                'order' => 7,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar usuarios',
                'group_key' => 'security',
                'route' => '/user',
            ],
            [
                'name' => 'users.changePassword',
                'icon' => 'person',
                'label' => 'Cambiar contrasena',
                'order' => 7,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar usuarios',
                'group_key' => 'security',
                'route' => '/user',
            ],
            [
                'name' => 'users.allUsers',
                'icon' => 'person',
                'label' => 'Ver usuarios de todas las empresas',
                'order' => 8,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar usuarios',
                'group_key' => 'security',
                'route' => '/user',
            ],
            [
                'name' => 'roles.view',
                'icon' => 'supervised_user_circle',
                'label' => 'Ver roles',
                'order' => 5,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar roles',
                'group_key' => 'security',
                'route' => '/role',
            ],
            [
                'name' => 'roles.list',
                'icon' => 'supervised_user_circle',
                'label' => 'Listar roles',
                'order' => 5,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar roles',
                'group_key' => 'security',
                'route' => '/role',
            ],
            [
                'name' => 'roles.create',
                'icon' => 'supervised_user_circle',
                'label' => 'Crear roles',
                'order' => 5,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar roles',
                'group_key' => 'security',
                'route' => '/role',
            ],
            [
                'name' => 'roles.edit',
                'icon' => 'supervised_user_circle',
                'label' => 'Modificar roles',
                'order' => 5,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar roles',
                'group_key' => 'security',
                'route' => '/role',
            ],
            [
                'name' => 'roles.delete',
                'icon' => 'supervised_user_circle',
                'label' => 'Eliminar roles',
                'order' => 5,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar roles',
                'group_key' => 'security',
                'route' => '/role',
            ],
            [
                'name' => 'groups.view',
                'icon' => 'account_tree',
                'label' => 'Ver grupos de funcionalidades',
                'order' => 4,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar grupos de funcionalidades',
                'group_key' => 'security',
                'route' => '/group',
            ],
            [
                'name' => 'groups.list',
                'icon' => 'account_tree',
                'label' => 'Listar grupos',
                'order' => 4,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar grupos de funcionalidades',
                'group_key' => 'security',
                'route' => '/group',
            ],
            [
                'name' => 'groups.create',
                'icon' => 'account_tree',
                'label' => 'Crear grupos de funcionalidades',
                'order' => 4,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar grupos de funcionalidades',
                'group_key' => 'security',
                'route' => '/group',
            ],
            [
                'name' => 'groups.edit',
                'icon' => 'account_tree',
                'label' => 'Modificar grupos de funcionalidades',
                'order' => 4,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar grupos de funcionalidades',
                'group_key' => 'security',
                'route' => '/group',
            ],
            [
                'name' => 'groups.delete',
                'icon' => 'account_tree',
                'label' => 'Eliminar grupos de funcionalidades',
                'order' => 4,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar grupos de funcionalidades',
                'group_key' => 'security',
                'route' => '/group',
            ],
            [
                'name' => 'permissions.view',
                'icon' => 'gpp_good',
                'label' => 'Ver permisos',
                'order' => 6,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar permisos',
                'group_key' => 'security',
                'route' => '/permission',
            ],
            [
                'name' => 'permissions.list',
                'icon' => 'gpp_good',
                'label' => 'Listar permisos',
                'order' => 6,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar permisos',
                'group_key' => 'security',
                'route' => '/permission',
            ],
            [
                'name' => 'permissions.create',
                'icon' => 'gpp_good',
                'label' => 'Crear permisos',
                'order' => 6,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar permisos',
                'group_key' => 'security',
                'route' => '/permission',
            ],
            [
                'name' => 'permissions.edit',
                'icon' => 'gpp_good',
                'label' => 'Modificar permisos',
                'order' => 6,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar permisos',
                'group_key' => 'security',
                'route' => '/permission',
            ],
            [
                'name' => 'permissions.delete',
                'icon' => 'gpp_good',
                'label' => 'Eliminar permisos',
                'order' => 6,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar permisos',
                'group_key' => 'security',
                'route' => '/permission',
            ],
            [
                'name' => 'companies.view',
                'icon' => 'business',
                'label' => 'Ver empresas',
                'order' => 0,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar empresas',
                'group_key' => 'organizations',
                'route' => '/company',
            ],
            [
                'name' => 'companies.list',
                'icon' => 'business',
                'label' => 'Listar empresas',
                'order' => 0,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar empresas',
                'group_key' => 'organizations',
                'route' => '/company',
            ],
            [
                'name' => 'companies.create',
                'icon' => 'business',
                'label' => 'Crear empresas',
                'order' => 0,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar empresas',
                'group_key' => 'organizations',
                'route' => '/company',
            ],
            [
                'name' => 'companies.edit',
                'icon' => 'business',
                'label' => 'Modificar empresas',
                'order' => 0,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar empresas',
                'group_key' => 'organizations',
                'route' => '/company',
            ],
            [
                'name' => 'companies.delete',
                'icon' => 'business',
                'label' => 'Eliminar empresas',
                'order' => 0,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar empresas',
                'group_key' => 'organizations',
                'route' => '/company',
            ],
            [
                'name' => 'companies.allCompanies',
                'icon' => 'business',
                'label' => 'Ver todas las empresas',
                'order' => 0,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar empresas',
                'group_key' => 'organizations',
                'route' => '/company',
            ],
            [
                'name' => 'places.view',
                'icon' => 'holiday_village',
                'label' => 'Ver sedes',
                'order' => 1,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar sedes',
                'group_key' => 'organizations',
                'route' => '/place',
            ],
            [
                'name' => 'places.list',
                'icon' => 'holiday_village',
                'label' => 'Listar sedes',
                'order' => 1,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar sedes',
                'group_key' => 'organizations',
                'route' => '/place',
            ],
            [
                'name' => 'places.create',
                'icon' => 'holiday_village',
                'label' => 'Crear sedes',
                'order' => 1,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar sedes',
                'group_key' => 'organizations',
                'route' => '/place',
            ],
            [
                'name' => 'places.edit',
                'icon' => 'holiday_village',
                'label' => 'Modificar sedes',
                'order' => 1,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar sedes',
                'group_key' => 'organizations',
                'route' => '/place',
            ],
            [
                'name' => 'places.delete',
                'icon' => 'holiday_village',
                'label' => 'Eliminar sedes',
                'order' => 1,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar sedes',
                'group_key' => 'organizations',
                'route' => '/place',
            ],
            [
                'name' => 'stations.view',
                'icon' => 'devices',
                'label' => 'Ver estaciones',
                'order' => 2,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar estaciones',
                'group_key' => 'organizations',
                'route' => '/station',
            ],
            [
                'name' => 'stations.list',
                'icon' => 'devices',
                'label' => 'Listar estaciones',
                'order' => 2,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar estaciones',
                'group_key' => 'organizations',
                'route' => '/station',
            ],
            [
                'name' => 'stations.create',
                'icon' => 'devices',
                'label' => 'Crear estaciones',
                'order' => 2,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar estaciones',
                'group_key' => 'organizations',
                'route' => '/station',
            ],
            [
                'name' => 'stations.edit',
                'icon' => 'devices',
                'label' => 'Modificar estaciones',
                'order' => 2,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar estaciones',
                'group_key' => 'organizations',
                'route' => '/station',
            ],
            [
                'name' => 'stations.delete',
                'icon' => 'devices',
                'label' => 'Eliminar estaciones',
                'order' => 2,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar estaciones',
                'group_key' => 'organizations',
                'route' => '/station',
            ],
            [
                'name' => 'stations.allPlaces',
                'icon' => 'devices',
                'label' => 'Ver / manipular todas las sedes',
                'order' => 2,
                'guard_name' => 'api',
                'subgroup' => 'Gestionar estaciones',
                'group_key' => 'organizations',
                'route' => '/station',
            ],

            [
                'name' => 'reports.list',
                'icon' => 'list_alt',
                'label' => 'Listar reportes',
                'order' => 9,
                'guard_name' => 'api',
                'subgroup' => 'Reportes',
                'group_key' => 'informes',
                'route' => '/report',
            ],
            [
                'name' => 'reports.export',
                'icon' => 'export',
                'label' => 'Exportar reportes',
                'order' => 9,
                'guard_name' => 'api',
                'subgroup' => 'Reportes',
                'group_key' => 'informes',
                'route' => '/report',
            ],
        ];

        foreach ($permissions as $permission) {
            $attributes = [
                'name' => $permission['name'],
                'guard_name' => $permission['guard_name'],
            ];

            $values = [];
            if (Schema::hasColumn('permissions', 'icon')) {
                $values['icon'] = $permission['icon'];
            }
            if (Schema::hasColumn('permissions', 'label')) {
                $values['label'] = $permission['label'];
            }
            if (Schema::hasColumn('permissions', 'order')) {
                $values['order'] = $permission['order'];
            }
            if (Schema::hasColumn('permissions', 'subgroup')) {
                $values['subgroup'] = $permission['subgroup'];
            }
            if (Schema::hasColumn('permissions', 'group_id')) {
                $values['group_id'] = $groups[$permission['group_key']]->id;
            }
            if (Schema::hasColumn('permissions', 'route')) {
                $values['route'] = $permission['route'];
            }

            Permission::updateOrCreate($attributes, $values);
        }

        // Remove legacy permissions from deleted modules.
        $activeNames = array_column($permissions, 'name');
        $posNames = [
            'pos-dashboard.view', 'pos-dashboard.allPlaces',
            'products.list', 'products.view', 'products.create', 'products.edit', 'products.delete', 'products.allPlaces',
            'product-categories.list', 'product-categories.create', 'product-categories.edit', 'product-categories.delete', 'product-categories.allPlaces',
            'purchases.list', 'purchases.create', 'purchases.allPlaces',
            'suppliers.list', 'suppliers.create', 'suppliers.edit', 'suppliers.delete', 'suppliers.allPlaces',
            'customers.list', 'customers.create', 'customers.edit', 'customers.delete', 'customers.allPlaces',

            'sales.list', 'sales.create', 'sales.overridePrice', 'sales.allPlaces',
            'register-sessions.list', 'register-sessions.allPlaces',
            'inventory.list', 'inventory.adjust', 'inventory.allPlaces',
        ];
        Permission::whereNotIn('name', array_merge($activeNames, $posNames))->delete();

        $activeGroups = ['Organizations', 'Security', 'Informes', 'POS'];
        Group::whereNotIn('name', $activeGroups)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
