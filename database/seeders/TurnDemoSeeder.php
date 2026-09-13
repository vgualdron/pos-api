<?php

namespace Database\Seeders;

use App\Models\Place;
use App\Models\Role;
use App\Models\Service;
use App\Models\Station;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TurnDemoSeeder extends Seeder
{
    public function run(): void
    {
        $place = Place::first();

        if (!$place) {
            return;
        }

        $entryExams = Service::updateOrCreate(
            ['place_id' => $place->id, 'code' => 'ENTRY_EXAMS'],
            [
                'name' => 'Examenes de ingreso',
                'prefix' => 'E',
                'description' => 'Turno principal para area de examenes',
                'estimated_minutes' => 5,
                'order' => 1,
                'status' => 'activo',
            ]
        );

        $billing = Service::updateOrCreate(
            ['place_id' => $place->id, 'code' => 'BILLING'],
            [
                'name' => 'Facturacion',
                'prefix' => 'F',
                'description' => 'Turno directo a consultorio',
                'estimated_minutes' => 10,
                'order' => 2,
                'status' => 'activo',
            ]
        );

        $lab = Service::updateOrCreate(
            ['place_id' => $place->id, 'code' => 'LAB'],
            [
                'name' => 'Laboratorio',
                'prefix' => 'L',
                'description' => 'Turno secundario de laboratorio',
                'estimated_minutes' => 15,
                'order' => 3,
                'status' => 'activo',
            ]
        );

        $rx = Service::updateOrCreate(
            ['place_id' => $place->id, 'code' => 'RX'],
            [
                'name' => 'Rayos X',
                'prefix' => 'R',
                'description' => 'Turno secundario de rayos x',
                'estimated_minutes' => 15,
                'order' => 4,
                'status' => 'activo',
            ]
        );

        // Ingreso puede generar después Lab y Rayos X
        $entryExams->followUpServices()->sync([$lab->id, $rx->id]);

        $kioskPrimary = Station::updateOrCreate(
            ['place_id' => $place->id, 'code' => 'KIOSK-ENTRY'],
            [
                'name' => 'Kiosco entrada',
                'type' => Station::TYPE_KIOSK,
                'can_generate_tickets' => true,
                'can_call_tickets' => false,
                'status' => 'activo',
            ]
        );

        $kioskSecondary = Station::updateOrCreate(
            ['place_id' => $place->id, 'code' => 'KIOSK-SECONDARY'],
            [
                'name' => 'Kiosco area examenes',
                'type' => Station::TYPE_KIOSK,
                'can_generate_tickets' => true,
                'can_call_tickets' => false,
                'status' => 'activo',
            ]
        );

        $consultoryLab = Station::updateOrCreate(
            ['place_id' => $place->id, 'code' => 'CONS-LAB'],
            [
                'name' => 'Consultorio laboratorio',
                'type' => Station::TYPE_CONSULTORY,
                'can_generate_tickets' => false,
                'can_call_tickets' => true,
                'status' => 'activo',
            ]
        );

        $display = Station::updateOrCreate(
            ['place_id' => $place->id, 'code' => 'TV-MAIN'],
            [
                'name' => 'TV sala principal',
                'type' => Station::TYPE_DISPLAY,
                'can_generate_tickets' => false,
                'can_call_tickets' => false,
                'status' => 'activo',
            ]
        );

        $kioskPrimary->services()->sync([
            $entryExams->id => ['can_generate' => true, 'can_call' => false, 'can_display' => false, 'display_order' => 1],
            $billing->id => ['can_generate' => true, 'can_call' => false, 'can_display' => false, 'display_order' => 2],
        ]);

        $kioskSecondary->services()->sync([
            $lab->id => ['can_generate' => true, 'can_call' => false, 'can_display' => false, 'display_order' => 1],
            $rx->id => ['can_generate' => true, 'can_call' => false, 'can_display' => false, 'display_order' => 2],
        ]);

        $consultoryLab->services()->sync([
            $lab->id => ['can_generate' => false, 'can_call' => true, 'can_display' => false, 'display_order' => 1],
        ]);

        $display->services()->sync([
            $lab->id => ['can_generate' => false, 'can_call' => false, 'can_display' => true, 'display_order' => 1],
            $billing->id => ['can_generate' => false, 'can_call' => false, 'can_display' => true, 'display_order' => 2],
        ]);

        $roles = [
            'KIOSK' => ['turns.view', 'turns.create'],
            'CONSULTORY' => ['turns.view', 'turns.call', 'turns.recall', 'turns.complete', 'turns.no_show'],
            'DISPLAY' => ['turns.view'],
        ];

        foreach ($roles as $roleName => $permissions) {
            $role = Role::updateOrCreate(
                ['name' => $roleName, 'guard_name' => 'api'],
                ['route' => '/turn']
            );
            $role->syncPermissions($permissions);
        }

        User::updateOrCreate(
            ['email' => 'kiosk.entry@datatrix.cloud'],
            [
                'name' => 'Kiosco Entrada',
                'company_id' => $place->company_id,
                'place_id' => $place->id,
                'station_id' => $kioskPrimary->id,
                'status' => 'activo',
                'password' => Hash::make('123456789'),
            ]
        )->syncRoles(['KIOSK']);

        User::updateOrCreate(
            ['email' => 'kiosk.secondary@datatrix.cloud'],
            [
                'name' => 'Kiosco Secundario',
                'company_id' => $place->company_id,
                'place_id' => $place->id,
                'station_id' => $kioskSecondary->id,
                'status' => 'activo',
                'password' => Hash::make('123456789'),
            ]
        )->syncRoles(['KIOSK']);

        User::updateOrCreate(
            ['email' => 'consultory.lab@datatrix.cloud'],
            [
                'name' => 'Consultorio Laboratorio',
                'company_id' => $place->company_id,
                'place_id' => $place->id,
                'station_id' => $consultoryLab->id,
                'status' => 'activo',
                'password' => Hash::make('123456789'),
            ]
        )->syncRoles(['CONSULTORY']);

        User::updateOrCreate(
            ['email' => 'display.main@datatrix.cloud'],
            [
                'name' => 'TV Sala Principal',
                'company_id' => $place->company_id,
                'place_id' => $place->id,
                'station_id' => $display->id,
                'status' => 'activo',
                'password' => Hash::make('123456789'),
            ]
        )->syncRoles(['DISPLAY']);
    }
}
