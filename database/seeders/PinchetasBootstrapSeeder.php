<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Place;
use App\Models\ProductCategory;
use App\Models\Station;
use Illuminate\Database\Seeder;

class PinchetasBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::updateOrCreate(
            ['document_number' => '900PINCH01'],
            [
                'name' => 'Pinchetas',
                'document_type' => 'nit',
                'address' => 'Colombia',
                'phone' => '3000000000',
                'email' => 'admin@pinchetas.local',
                'status' => 'activo',
                'configuration' => [
                    'template_color' => '#8B5A2B',
                    'domain' => 'pinchetas.local',
                    'business_mode' => 'restaurant',
                ],
            ]
        );

        $places = [
            [
                'name' => 'Sayago',
                'address' => 'Sede Sayago',
                'email' => 'sayago@pinchetas.local',
                'station' => 'Caja Sayago',
                'station_code' => 'SAY-CAJA-1',
            ],
            [
                'name' => 'Faroles',
                'address' => 'Sede Faroles',
                'email' => 'faroles@pinchetas.local',
                'station' => 'Caja Faroles',
                'station_code' => 'FAR-CAJA-1',
            ],
        ];

        $categories = [
            'Entradas',
            'Palitos',
            'Flechas',
            'Menú infantil',
            'Pinchetas',
            'Cortes y parrilla',
            'Clásicos a base de pincheta',
            'Especiales de la casa',
            'Bebidas',
            'Insumos',
        ];

        foreach ($places as $placeData) {
            $place = Place::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => $placeData['name'],
                ],
                [
                    'address' => $placeData['address'],
                    'phone' => '3000000000',
                    'email' => $placeData['email'],
                    'status' => 'activo',
                ]
            );

            Station::updateOrCreate(
                [
                    'place_id' => $place->id,
                    'code' => $placeData['station_code'],
                ],
                [
                    'name' => $placeData['station'],
                    'type' => Station::TYPE_REGISTER,
                    'status' => 'activo',
                ]
            );

            foreach ($categories as $index => $categoryName) {
                ProductCategory::updateOrCreate(
                    [
                        'place_id' => $place->id,
                        'name' => $categoryName,
                    ],
                    [
                        'status' => 'activo',
                        'show_on_register' => $categoryName !== 'Insumos',
                        'sort_order' => $index + 1,
                    ]
                );
            }
        }

        $this->command?->info('Pinchetas lista: company restaurant + Sayago/Faroles + cajas + categorías.');
    }
}
