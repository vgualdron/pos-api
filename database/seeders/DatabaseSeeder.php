<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Company;
use App\Models\Place;
use App\Models\Role;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $company = Company::firstOrCreate(
            ['document_number' => '900000001'],
            [
                'name' => 'Empresa S.A.S',
                'document_type' => 'NIT',
                'address' => 'Direccion principal',
                'phone' => '3000000000',
                'email' => 'company@datatrix.cloud',
                'status' => 'activo',
            ]
        );

        $place = Place::firstOrCreate(
            [
                'company_id' => $company->id,
                'name' => 'Sede Principal',
            ],
            [
                'address' => 'Direccion sede principal',
                'phone' => '3000000001',
                'email' => 'sede.principal@datatrix.cloud',
                'status' => 'activo',
            ]
        );

        $userData = [
            'name' => 'Victor Hernando Gualdrón Ruiz',
            'company_id' => $company->id,
            'place_id' => 1,
            'status' => 'activo',
            'password' => Hash::make('123456789'),
        ];

        $user = User::updateOrCreate(
            ['email' => 'victor.gualdron.r@gmail.com'],
            $userData
        );

        $this->call(CorePermissionsSeeder::class);
        $this->call(PosPermissionsSeeder::class);
        $this->call(PosReportsSeeder::class);
        $this->call(AdminRoleSeeder::class);
        $this->call(PinchetasBootstrapSeeder::class);
        $this->call(PinchetasCatalogSeeder::class);
        $this->call(PinchetasDiningTablesSeeder::class);

        $role = Role::where('name', 'ADMINISTRADOR')
            ->where('guard_name', 'api')
            ->first();

        if ($role) {
            $user->syncRoles([$role->name]);
        }
    }
}
