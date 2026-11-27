<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\DiningTable;
use App\Models\DiningZone;
use App\Models\Place;
use Illuminate\Database\Seeder;

class PinchetasDiningTablesSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('name', 'Pinchetas')->first();
        if (!$company) {
            $this->command?->warn('Pinchetas no existe. Corre PinchetasBootstrapSeeder primero.');

            return;
        }

        $places = Place::query()->where('company_id', $company->id)->orderBy('id')->get();
        if ($places->isEmpty()) {
            $this->command?->warn('Pinchetas no tiene sedes.');

            return;
        }

        $layout = [
            ['code' => 'M1', 'name' => 'Mesa 1', 'seats' => 4, 'zone' => 'Salón', 'sort_order' => 1],
            ['code' => 'M2', 'name' => 'Mesa 2', 'seats' => 4, 'zone' => 'Salón', 'sort_order' => 2],
            ['code' => 'M3', 'name' => 'Mesa 3', 'seats' => 2, 'zone' => 'Salón', 'sort_order' => 3],
            ['code' => 'M4', 'name' => 'Mesa 4', 'seats' => 6, 'zone' => 'Salón', 'sort_order' => 4],
            ['code' => 'M5', 'name' => 'Mesa 5', 'seats' => 4, 'zone' => 'Salón', 'sort_order' => 5],
            ['code' => 'M6', 'name' => 'Mesa 6', 'seats' => 8, 'zone' => 'Salón', 'sort_order' => 6],
            ['code' => 'T1', 'name' => 'Terraza 1', 'seats' => 4, 'zone' => 'Terraza', 'sort_order' => 10],
            ['code' => 'T2', 'name' => 'Terraza 2', 'seats' => 4, 'zone' => 'Terraza', 'sort_order' => 11],
            ['code' => 'T3', 'name' => 'Terraza 3', 'seats' => 6, 'zone' => 'Terraza', 'sort_order' => 12],
            ['code' => 'T4', 'name' => 'Terraza 4', 'seats' => 2, 'zone' => 'Terraza', 'sort_order' => 13],
        ];

        foreach ($places as $place) {
            $zoneIds = [];
            foreach (['Salón' => 1, 'Terraza' => 2] as $zoneName => $sort) {
                $zone = DiningZone::updateOrCreate(
                    [
                        'place_id' => $place->id,
                        'name' => $zoneName,
                    ],
                    [
                        'sort_order' => $sort,
                        'status' => DiningZone::STATUS_ACTIVE,
                    ]
                );
                $zoneIds[$zoneName] = $zone->id;
            }

            foreach ($layout as $row) {
                DiningTable::updateOrCreate(
                    [
                        'place_id' => $place->id,
                        'code' => $row['code'],
                    ],
                    [
                        'name' => $row['name'],
                        'seats' => $row['seats'],
                        'dining_zone_id' => $zoneIds[$row['zone']] ?? null,
                        'operational_status' => DiningTable::OP_FREE,
                        'status' => DiningTable::STATUS_ACTIVE,
                        'sort_order' => $row['sort_order'],
                    ]
                );
            }
            $this->command?->info("Zonas y mesas Pinchetas cargadas en {$place->name}.");
        }
    }
}
