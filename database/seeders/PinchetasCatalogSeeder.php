<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Place;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPresentation;
use App\Models\ProductRecipeItem;
use App\Models\StockLot;
use App\Models\Tax;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds Pinchetas menu catalog per place: ingredients, dishes with recipes, drinks, opening stock.
 *
 * Requires PinchetasBootstrapSeeder first (company + places + categories).
 *
 *   php artisan db:seed --class=Database\\Seeders\\PinchetasCatalogSeeder --force
 */
class PinchetasCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('name', 'Pinchetas')->first();
        if (!$company) {
            $this->command?->error('No existe la empresa Pinchetas. Corre primero PinchetasBootstrapSeeder.');

            return;
        }

        $places = Place::query()->where('company_id', $company->id)->orderBy('id')->get();
        if ($places->isEmpty()) {
            $this->command?->error('Pinchetas no tiene sedes.');

            return;
        }

        $tax = Tax::query()->where('code', 'IVA_19')->first()
            ?: Tax::query()->where('status', 'activo')->orderBy('sort_order')->first();

        foreach ($places as $place) {
            DB::transaction(function () use ($place, $tax) {
                $this->seedPlace($place, $tax);
            });
            $this->command?->info("Catálogo Pinchetas cargado en sede {$place->name} (#{$place->id}).");
        }
    }

    protected function seedPlace(Place $place, ?Tax $tax): void
    {
        $categories = ProductCategory::query()
            ->where('place_id', $place->id)
            ->get()
            ->keyBy('name');

        $ingredients = [];
        foreach ($this->ingredientCatalog() as $row) {
            $ingredients[$row['sku']] = $this->upsertIngredient($place, $row, $categories->get('Insumos')?->id, $tax);
        }

        foreach ($this->dishCatalog() as $row) {
            $this->upsertDish($place, $row, $categories, $ingredients, $tax);
        }

        foreach ($this->drinkCatalog() as $row) {
            $this->upsertSimpleSaleProduct($place, $row, $categories->get('Bebidas')?->id, $tax);
        }

        $this->upsertSimpleSaleProduct($place, [
            'sku' => 'PIN-ADD-RECARGO-PREMIUM',
            'name' => 'Recargo pincheta premium',
            'base_unit_name' => 'und',
            'category' => 'Clásicos a base de pincheta',
            'price' => 5000,
            'inventory_mode' => Product::INVENTORY_STANDARD,
            'initial_stock' => 0,
            'unit_cost' => 0,
        ], $categories->get('Clásicos a base de pincheta')?->id, $tax);
    }

    protected function upsertIngredient(Place $place, array $row, ?int $categoryId, ?Tax $tax): Product
    {
        $product = Product::query()->updateOrCreate(
            [
                'place_id' => $place->id,
                'sku' => $row['sku'],
            ],
            [
                'product_category_id' => $categoryId,
                'name' => $row['name'],
                'barcode' => null,
                'base_unit_name' => $row['base_unit_name'],
                'inventory_mode' => Product::INVENTORY_STANDARD,
                'min_stock' => (float) ($row['min_stock'] ?? 0),
                'status' => 'activo',
            ]
        );

        $this->syncPresentations($product, $row['presentations'] ?? [
            ['name' => 'Unidad', 'factor' => 1, 'sale_price' => 0, 'is_default' => true],
        ], $tax);

        $this->ensureSeedLot(
            $place->id,
            $product->id,
            $row['sku'],
            (float) ($row['initial_stock'] ?? 0),
            (float) ($row['unit_cost'] ?? 0)
        );

        return $product->fresh();
    }

    protected function upsertDish(
        Place $place,
        array $row,
        $categories,
        array $ingredients,
        ?Tax $tax
    ): Product {
        $categoryId = $categories->get($row['category'])?->id;

        $product = Product::query()->updateOrCreate(
            [
                'place_id' => $place->id,
                'sku' => $row['sku'],
            ],
            [
                'product_category_id' => $categoryId,
                'name' => $row['name'],
                'barcode' => null,
                'base_unit_name' => 'und',
                'inventory_mode' => Product::INVENTORY_RECIPE,
                'min_stock' => 0,
                'status' => 'activo',
            ]
        );

        $this->syncPresentations($product, [
            [
                'name' => 'Unidad',
                'factor' => 1,
                'sale_price' => (float) $row['price'],
                'is_default' => true,
            ],
        ], $tax);

        $keepIds = [];
        foreach ($row['recipe'] as $index => $item) {
            $ingredient = $ingredients[$item['sku']] ?? null;
            if (!$ingredient) {
                throw new \RuntimeException("Ingrediente {$item['sku']} faltante para {$row['sku']}.");
            }

            $recipeItem = ProductRecipeItem::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'ingredient_product_id' => $ingredient->id,
                ],
                [
                    'qty_base' => (float) $item['qty'],
                    'sort_order' => $index,
                ]
            );
            $keepIds[] = $recipeItem->id;
        }

        ProductRecipeItem::query()
            ->where('product_id', $product->id)
            ->whereNotIn('id', $keepIds)
            ->delete();

        return $product;
    }

    protected function upsertSimpleSaleProduct(Place $place, array $row, ?int $categoryId, ?Tax $tax): Product
    {
        if (!empty($row['category']) && !$categoryId) {
            $categoryId = ProductCategory::query()
                ->where('place_id', $place->id)
                ->where('name', $row['category'])
                ->value('id');
        }

        $product = Product::query()->updateOrCreate(
            [
                'place_id' => $place->id,
                'sku' => $row['sku'],
            ],
            [
                'product_category_id' => $categoryId,
                'name' => $row['name'],
                'barcode' => null,
                'base_unit_name' => $row['base_unit_name'] ?? 'und',
                'inventory_mode' => $row['inventory_mode'] ?? Product::INVENTORY_STANDARD,
                'min_stock' => (float) ($row['min_stock'] ?? 0),
                'status' => 'activo',
            ]
        );

        $this->syncPresentations($product, [
            [
                'name' => 'Unidad',
                'factor' => 1,
                'sale_price' => (float) $row['price'],
                'is_default' => true,
            ],
        ], $tax);

        $stock = (float) ($row['initial_stock'] ?? 0);
        if ($stock > 0) {
            $this->ensureSeedLot($place->id, $product->id, $row['sku'], $stock, (float) ($row['unit_cost'] ?? 0));
        }

        return $product;
    }

    protected function syncPresentations(Product $product, array $presentations, ?Tax $tax): void
    {
        $keepIds = [];
        foreach ($presentations as $index => $row) {
            $presentation = ProductPresentation::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'name' => $row['name'],
                ],
                [
                    'factor' => (float) $row['factor'],
                    'sale_price' => (float) $row['sale_price'],
                    'tax_id' => $tax?->id,
                    'tax_rate' => round((float) ($tax?->rate ?? 0), 2),
                    'barcode' => $row['barcode'] ?? null,
                    'is_default' => (bool) ($row['is_default'] ?? $index === 0),
                    'status' => 'activo',
                ]
            );
            $keepIds[] = $presentation->id;
        }

        ProductPresentation::query()
            ->where('product_id', $product->id)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }

    protected function ensureSeedLot(int $placeId, int $productId, string $sku, float $qty, float $unitCost): void
    {
        if ($qty <= 0) {
            return;
        }

        $lotCode = 'SEED-PIN-'.$sku;
        $existing = StockLot::query()
            ->where('place_id', $placeId)
            ->where('product_id', $productId)
            ->where('lot_code', $lotCode)
            ->first();

        if ($existing) {
            if ((float) $existing->qty_remaining <= 0
                || abs((float) $existing->qty_remaining - (float) $existing->qty_initial) < 0.0001) {
                $existing->update([
                    'purchased_at' => now(),
                    'unit_cost' => $unitCost,
                    'qty_initial' => $qty,
                    'qty_remaining' => $qty,
                ]);
            }

            return;
        }

        StockLot::query()->create([
            'place_id' => $placeId,
            'product_id' => $productId,
            'purchase_id' => null,
            'lot_code' => $lotCode,
            'purchased_at' => now(),
            'expires_at' => null,
            'unit_cost' => $unitCost,
            'qty_initial' => $qty,
            'qty_remaining' => $qty,
        ]);
    }

    /**
     * Estimated kitchen quantities (MVP). Adjust after kitchen validation.
     */
    protected function ingredientCatalog(): array
    {
        return [
            // Proteins (base = g unless noted)
            ['sku' => 'PIN-ING-RES', 'name' => 'Res', 'base_unit_name' => 'g', 'min_stock' => 2000, 'initial_stock' => 20000, 'unit_cost' => 28, 'presentations' => [
                ['name' => 'Gramo', 'factor' => 1, 'sale_price' => 0, 'is_default' => true],
                ['name' => 'Kilo', 'factor' => 1000, 'sale_price' => 0, 'is_default' => false],
            ]],
            ['sku' => 'PIN-ING-POLLO', 'name' => 'Pollo / pechuga', 'base_unit_name' => 'g', 'min_stock' => 2000, 'initial_stock' => 20000, 'unit_cost' => 18, 'presentations' => [
                ['name' => 'Gramo', 'factor' => 1, 'sale_price' => 0, 'is_default' => true],
                ['name' => 'Kilo', 'factor' => 1000, 'sale_price' => 0, 'is_default' => false],
            ]],
            ['sku' => 'PIN-ING-CERDO', 'name' => 'Cerdo', 'base_unit_name' => 'g', 'min_stock' => 2000, 'initial_stock' => 15000, 'unit_cost' => 20, 'presentations' => [
                ['name' => 'Gramo', 'factor' => 1, 'sale_price' => 0, 'is_default' => true],
                ['name' => 'Kilo', 'factor' => 1000, 'sale_price' => 0, 'is_default' => false],
            ]],
            ['sku' => 'PIN-ING-CHICHARRON', 'name' => 'Chicharrón', 'base_unit_name' => 'g', 'min_stock' => 1000, 'initial_stock' => 8000, 'unit_cost' => 22, 'presentations' => [
                ['name' => 'Gramo', 'factor' => 1, 'sale_price' => 0, 'is_default' => true],
                ['name' => 'Kilo', 'factor' => 1000, 'sale_price' => 0, 'is_default' => false],
            ]],
            ['sku' => 'PIN-ING-CHORIZO', 'name' => 'Chorizo', 'base_unit_name' => 'und', 'min_stock' => 20, 'initial_stock' => 200, 'unit_cost' => 2500, 'presentations' => [
                ['name' => 'Unidad', 'factor' => 1, 'sale_price' => 0, 'is_default' => true],
                ['name' => 'Paquete x10', 'factor' => 10, 'sale_price' => 0, 'is_default' => false],
            ]],
            ['sku' => 'PIN-ING-MORCILLA', 'name' => 'Morcilla', 'base_unit_name' => 'und', 'min_stock' => 10, 'initial_stock' => 80, 'unit_cost' => 2800, 'presentations' => [
                ['name' => 'Unidad', 'factor' => 1, 'sale_price' => 0, 'is_default' => true],
            ]],
            ['sku' => 'PIN-ING-CHUNCHULLO', 'name' => 'Chunchullo', 'base_unit_name' => 'g', 'min_stock' => 500, 'initial_stock' => 4000, 'unit_cost' => 16, 'presentations' => [
                ['name' => 'Gramo', 'factor' => 1, 'sale_price' => 0, 'is_default' => true],
                ['name' => 'Kilo', 'factor' => 1000, 'sale_price' => 0, 'is_default' => false],
            ]],
            ['sku' => 'PIN-ING-COSTILLA', 'name' => 'Costilla BBQ', 'base_unit_name' => 'g', 'min_stock' => 2000, 'initial_stock' => 10000, 'unit_cost' => 26, 'presentations' => [
                ['name' => 'Gramo', 'factor' => 1, 'sale_price' => 0, 'is_default' => true],
                ['name' => 'Kilo', 'factor' => 1000, 'sale_price' => 0, 'is_default' => false],
            ]],
            ['sku' => 'PIN-ING-CHURRASCO', 'name' => 'Churrasco', 'base_unit_name' => 'g', 'min_stock' => 2000, 'initial_stock' => 10000, 'unit_cost' => 32, 'presentations' => [
                ['name' => 'Gramo', 'factor' => 1, 'sale_price' => 0, 'is_default' => true],
                ['name' => 'Kilo', 'factor' => 1000, 'sale_price' => 0, 'is_default' => false],
            ]],
            ['sku' => 'PIN-ING-PUNTA-ANCA', 'name' => 'Punta de anca', 'base_unit_name' => 'g', 'min_stock' => 2000, 'initial_stock' => 8000, 'unit_cost' => 36, 'presentations' => [
                ['name' => 'Gramo', 'factor' => 1, 'sale_price' => 0, 'is_default' => true],
                ['name' => 'Kilo', 'factor' => 1000, 'sale_price' => 0, 'is_default' => false],
            ]],
            ['sku' => 'PIN-ING-TOMAHAWK', 'name' => 'Tomahawk', 'base_unit_name' => 'g', 'min_stock' => 2000, 'initial_stock' => 4000, 'unit_cost' => 45, 'presentations' => [
                ['name' => 'Gramo', 'factor' => 1, 'sale_price' => 0, 'is_default' => true],
                ['name' => 'Kilo', 'factor' => 1000, 'sale_price' => 0, 'is_default' => false],
            ]],
            ['sku' => 'PIN-ING-CHATA', 'name' => 'Chata', 'base_unit_name' => 'g', 'min_stock' => 1000, 'initial_stock' => 6000, 'unit_cost' => 30, 'presentations' => [
                ['name' => 'Gramo', 'factor' => 1, 'sale_price' => 0, 'is_default' => true],
                ['name' => 'Kilo', 'factor' => 1000, 'sale_price' => 0, 'is_default' => false],
            ]],
            ['sku' => 'PIN-ING-SALCHICHA', 'name' => 'Salchicha', 'base_unit_name' => 'und', 'min_stock' => 40, 'initial_stock' => 400, 'unit_cost' => 800, 'presentations' => [
                ['name' => 'Unidad', 'factor' => 1, 'sale_price' => 0, 'is_default' => true],
                ['name' => 'Paquete x20', 'factor' => 20, 'sale_price' => 0, 'is_default' => false],
            ]],
            ['sku' => 'PIN-ING-NUGGET', 'name' => 'Nugget de pollo', 'base_unit_name' => 'und', 'min_stock' => 40, 'initial_stock' => 400, 'unit_cost' => 600, 'presentations' => [
                ['name' => 'Unidad', 'factor' => 1, 'sale_price' => 0, 'is_default' => true],
                ['name' => 'Caja x50', 'factor' => 50, 'sale_price' => 0, 'is_default' => false],
            ]],
            ['sku' => 'PIN-ING-TOCINETA', 'name' => 'Tocineta', 'base_unit_name' => 'und', 'min_stock' => 30, 'initial_stock' => 200, 'unit_cost' => 700, 'presentations' => [
                ['name' => 'Unidad', 'factor' => 1, 'sale_price' => 0, 'is_default' => true],
            ]],
            ['sku' => 'PIN-ING-HUEVO', 'name' => 'Huevo', 'base_unit_name' => 'und', 'min_stock' => 30, 'initial_stock' => 180, 'unit_cost' => 500, 'presentations' => [
                ['name' => 'Unidad', 'factor' => 1, 'sale_price' => 0, 'is_default' => true],
                ['name' => 'Cubeta x30', 'factor' => 30, 'sale_price' => 0, 'is_default' => false],
            ]],
            ['sku' => 'PIN-ING-HUEVO-COD', 'name' => 'Huevo de codorniz', 'base_unit_name' => 'und', 'min_stock' => 40, 'initial_stock' => 300, 'unit_cost' => 300, 'presentations' => [
                ['name' => 'Unidad', 'factor' => 1, 'sale_price' => 0, 'is_default' => true],
            ]],

            // Sides / bases
            ['sku' => 'PIN-ING-PAPA-CRIOLLA', 'name' => 'Papa criolla (porción)', 'base_unit_name' => 'porción', 'min_stock' => 30, 'initial_stock' => 200, 'unit_cost' => 1500],
            ['sku' => 'PIN-ING-PAPA-FRANCESA', 'name' => 'Papa a la francesa (porción)', 'base_unit_name' => 'porción', 'min_stock' => 20, 'initial_stock' => 120, 'unit_cost' => 1800],
            ['sku' => 'PIN-ING-PATACON', 'name' => 'Patacón', 'base_unit_name' => 'und', 'min_stock' => 30, 'initial_stock' => 200, 'unit_cost' => 800],
            ['sku' => 'PIN-ING-YUCA', 'name' => 'Yuca frita (porción)', 'base_unit_name' => 'porción', 'min_stock' => 15, 'initial_stock' => 80, 'unit_cost' => 1600],
            ['sku' => 'PIN-ING-AREPA', 'name' => 'Arepa', 'base_unit_name' => 'und', 'min_stock' => 30, 'initial_stock' => 150, 'unit_cost' => 700],
            ['sku' => 'PIN-ING-MAZORCA', 'name' => 'Mazorca', 'base_unit_name' => 'und', 'min_stock' => 20, 'initial_stock' => 100, 'unit_cost' => 2500],
            ['sku' => 'PIN-ING-MAIZ', 'name' => 'Maíz desgranado (porción)', 'base_unit_name' => 'porción', 'min_stock' => 20, 'initial_stock' => 100, 'unit_cost' => 1200],
            ['sku' => 'PIN-ING-PAN', 'name' => 'Pan artesanal', 'base_unit_name' => 'und', 'min_stock' => 20, 'initial_stock' => 80, 'unit_cost' => 1500],
            ['sku' => 'PIN-ING-TORTILLA', 'name' => 'Tortilla burrito', 'base_unit_name' => 'und', 'min_stock' => 20, 'initial_stock' => 80, 'unit_cost' => 1200],
            ['sku' => 'PIN-ING-PAN-BURGER', 'name' => 'Pan hamburguesa', 'base_unit_name' => 'und', 'min_stock' => 20, 'initial_stock' => 80, 'unit_cost' => 1200],
            ['sku' => 'PIN-ING-PAN-PEPITO', 'name' => 'Pan pepito', 'base_unit_name' => 'und', 'min_stock' => 15, 'initial_stock' => 60, 'unit_cost' => 1800],
            ['sku' => 'PIN-ING-VEGETALES', 'name' => 'Vegetales (porción)', 'base_unit_name' => 'porción', 'min_stock' => 20, 'initial_stock' => 80, 'unit_cost' => 1000],
            ['sku' => 'PIN-ING-CEBOLLA-CROC', 'name' => 'Cebolla crocante (porción)', 'base_unit_name' => 'porción', 'min_stock' => 15, 'initial_stock' => 60, 'unit_cost' => 900],
            ['sku' => 'PIN-ING-TOMATE', 'name' => 'Tomate', 'base_unit_name' => 'und', 'min_stock' => 20, 'initial_stock' => 80, 'unit_cost' => 400],

            // Cheese / sauces
            ['sku' => 'PIN-ING-QUESO', 'name' => 'Queso', 'base_unit_name' => 'g', 'min_stock' => 1000, 'initial_stock' => 8000, 'unit_cost' => 22, 'presentations' => [
                ['name' => 'Gramo', 'factor' => 1, 'sale_price' => 0, 'is_default' => true],
                ['name' => 'Kilo', 'factor' => 1000, 'sale_price' => 0, 'is_default' => false],
            ]],
            ['sku' => 'PIN-ING-CHEDDAR', 'name' => 'Queso cheddar (rebanada)', 'base_unit_name' => 'und', 'min_stock' => 30, 'initial_stock' => 150, 'unit_cost' => 600],
            ['sku' => 'PIN-ING-PICO', 'name' => 'Pico de gallo (porción)', 'base_unit_name' => 'porción', 'min_stock' => 30, 'initial_stock' => 150, 'unit_cost' => 800],
            ['sku' => 'PIN-ING-TARTARA', 'name' => 'Salsa tártara (porción)', 'base_unit_name' => 'porción', 'min_stock' => 30, 'initial_stock' => 150, 'unit_cost' => 700],
            ['sku' => 'PIN-ING-BBQ', 'name' => 'Salsa BBQ (porción)', 'base_unit_name' => 'porción', 'min_stock' => 20, 'initial_stock' => 100, 'unit_cost' => 700],
            ['sku' => 'PIN-ING-CHIMICHURRI', 'name' => 'Chimichurri (porción)', 'base_unit_name' => 'porción', 'min_stock' => 15, 'initial_stock' => 80, 'unit_cost' => 700],

            // Default pincheta portion used inside classics (pollo-based estimate)
            ['sku' => 'PIN-ING-PORCION-PINCHETA', 'name' => 'Porción pincheta (base)', 'base_unit_name' => 'porción', 'min_stock' => 30, 'initial_stock' => 150, 'unit_cost' => 6500],
        ];
    }

    protected function dishCatalog(): array
    {
        $pinchetaBase = [
            ['sku' => 'PIN-ING-PAPA-CRIOLLA', 'qty' => 1],
            ['sku' => 'PIN-ING-PICO', 'qty' => 1],
            ['sku' => 'PIN-ING-TARTARA', 'qty' => 1],
        ];

        return [
            // Entradas
            [
                'sku' => 'PIN-ENT-MAZORCA', 'name' => 'Mazorca', 'category' => 'Entradas', 'price' => 14900,
                'recipe' => [['sku' => 'PIN-ING-MAZORCA', 'qty' => 1]],
            ],
            [
                'sku' => 'PIN-ENT-PAPA-CRIOLLA', 'name' => 'Papa criolla', 'category' => 'Entradas', 'price' => 9900,
                'recipe' => [['sku' => 'PIN-ING-PAPA-CRIOLLA', 'qty' => 1]],
            ],
            [
                'sku' => 'PIN-ENT-CANASTA-CHICHARRON', 'name' => 'Canasta de chicharrón', 'category' => 'Entradas', 'price' => 17900,
                'recipe' => [['sku' => 'PIN-ING-CHICHARRON', 'qty' => 180]],
            ],
            [
                'sku' => 'PIN-ENT-DEDOS-QUESO', 'name' => 'Dedos de queso', 'category' => 'Entradas', 'price' => 17900,
                'recipe' => [['sku' => 'PIN-ING-QUESO', 'qty' => 150]],
            ],
            [
                'sku' => 'PIN-ENT-PAPA-FRANCESA', 'name' => 'Papa a la francesa', 'category' => 'Entradas', 'price' => 12900,
                'recipe' => [['sku' => 'PIN-ING-PAPA-FRANCESA', 'qty' => 1]],
            ],
            [
                'sku' => 'PIN-ENT-CUBOS-QUESO', 'name' => 'Cubos de queso', 'category' => 'Entradas', 'price' => 19900,
                'recipe' => [['sku' => 'PIN-ING-QUESO', 'qty' => 180]],
            ],
            [
                'sku' => 'PIN-ENT-ENSALADA-CRIOLLA', 'name' => 'Ensalada criolla', 'category' => 'Entradas', 'price' => 15900,
                'recipe' => [
                    ['sku' => 'PIN-ING-QUESO', 'qty' => 80],
                    ['sku' => 'PIN-ING-MAIZ', 'qty' => 1],
                    ['sku' => 'PIN-ING-PAPA-CRIOLLA', 'qty' => 1],
                    ['sku' => 'PIN-ING-PICO', 'qty' => 1],
                ],
            ],

            // Palitos
            [
                'sku' => 'PIN-PAL-PECHUGA', 'name' => 'Palito de pechuga', 'category' => 'Palitos', 'price' => 17900,
                'recipe' => [
                    ['sku' => 'PIN-ING-POLLO', 'qty' => 150],
                    ['sku' => 'PIN-ING-PATACON', 'qty' => 2],
                ],
            ],
            [
                'sku' => 'PIN-PAL-CHURRASCO', 'name' => 'Palito de churrasco (200 g)', 'category' => 'Palitos', 'price' => 23900,
                'recipe' => [
                    ['sku' => 'PIN-ING-CHURRASCO', 'qty' => 200],
                    ['sku' => 'PIN-ING-PATACON', 'qty' => 2],
                ],
            ],

            // Flechas
            [
                'sku' => 'PIN-FLE-MIXTA', 'name' => 'Flecha mixta', 'category' => 'Flechas', 'price' => 17900,
                'recipe' => [
                    ['sku' => 'PIN-ING-RES', 'qty' => 80],
                    ['sku' => 'PIN-ING-POLLO', 'qty' => 80],
                    ['sku' => 'PIN-ING-PAPA-CRIOLLA', 'qty' => 1],
                ],
            ],
            [
                'sku' => 'PIN-FLE-RES', 'name' => 'Flecha de res (160 g)', 'category' => 'Flechas', 'price' => 21900,
                'recipe' => [
                    ['sku' => 'PIN-ING-RES', 'qty' => 160],
                    ['sku' => 'PIN-ING-PAPA-CRIOLLA', 'qty' => 1],
                ],
            ],
            [
                'sku' => 'PIN-FLE-POLLO', 'name' => 'Flecha de pollo', 'category' => 'Flechas', 'price' => 15900,
                'recipe' => [
                    ['sku' => 'PIN-ING-POLLO', 'qty' => 140],
                    ['sku' => 'PIN-ING-PAPA-CRIOLLA', 'qty' => 1],
                ],
            ],

            // Menú infantil
            [
                'sku' => 'PIN-INF-NUGGETS', 'name' => 'Nuggets de pollo', 'category' => 'Menú infantil', 'price' => 19900,
                'recipe' => [
                    ['sku' => 'PIN-ING-NUGGET', 'qty' => 6],
                    ['sku' => 'PIN-ING-PAPA-CRIOLLA', 'qty' => 1],
                ],
            ],
            [
                'sku' => 'PIN-INF-SALCHIPAPA', 'name' => 'Salchipapa', 'category' => 'Menú infantil', 'price' => 19900,
                'recipe' => [
                    ['sku' => 'PIN-ING-SALCHICHA', 'qty' => 2],
                    ['sku' => 'PIN-ING-QUESO', 'qty' => 40],
                    ['sku' => 'PIN-ING-HUEVO-COD', 'qty' => 3],
                    ['sku' => 'PIN-ING-PAPA-CRIOLLA', 'qty' => 1],
                ],
            ],

            // Pinchetas
            [
                'sku' => 'PIN-PIN-CHORIZO', 'name' => 'Pincheta chorizo', 'category' => 'Pinchetas', 'price' => 19900,
                'recipe' => array_merge([['sku' => 'PIN-ING-CHORIZO', 'qty' => 2]], $pinchetaBase),
            ],
            [
                'sku' => 'PIN-PIN-CLASICA', 'name' => 'Pincheta clásica', 'category' => 'Pinchetas', 'price' => 32900,
                'recipe' => array_merge([
                    ['sku' => 'PIN-ING-RES', 'qty' => 120],
                    ['sku' => 'PIN-ING-POLLO', 'qty' => 120],
                ], $pinchetaBase),
            ],
            [
                'sku' => 'PIN-PIN-TRINCHETA', 'name' => 'Trincheta', 'category' => 'Pinchetas', 'price' => 36900,
                'recipe' => array_merge([
                    ['sku' => 'PIN-ING-RES', 'qty' => 100],
                    ['sku' => 'PIN-ING-POLLO', 'qty' => 100],
                    ['sku' => 'PIN-ING-CERDO', 'qty' => 100],
                ], $pinchetaBase),
            ],
            [
                'sku' => 'PIN-PIN-CHICHARRON', 'name' => 'Pincheta chicharrón', 'category' => 'Pinchetas', 'price' => 26900,
                'recipe' => array_merge([['sku' => 'PIN-ING-CHICHARRON', 'qty' => 200]], $pinchetaBase),
            ],
            [
                'sku' => 'PIN-PIN-RES', 'name' => 'Pincheta res', 'category' => 'Pinchetas', 'price' => 33900,
                'recipe' => array_merge([['sku' => 'PIN-ING-RES', 'qty' => 220]], $pinchetaBase),
            ],
            [
                'sku' => 'PIN-PIN-MIXTA', 'name' => 'Pincheta mixta', 'category' => 'Pinchetas', 'price' => 30900,
                'recipe' => array_merge([
                    ['sku' => 'PIN-ING-CERDO', 'qty' => 120],
                    ['sku' => 'PIN-ING-POLLO', 'qty' => 120],
                ], $pinchetaBase),
            ],
            [
                'sku' => 'PIN-PIN-POLLO', 'name' => 'Pincheta pollo', 'category' => 'Pinchetas', 'price' => 28900,
                'recipe' => array_merge([['sku' => 'PIN-ING-POLLO', 'qty' => 220]], $pinchetaBase),
            ],
            [
                'sku' => 'PIN-PIN-CERDO', 'name' => 'Pincheta cerdo', 'category' => 'Pinchetas', 'price' => 29900,
                'recipe' => array_merge([['sku' => 'PIN-ING-CERDO', 'qty' => 220]], $pinchetaBase),
            ],
            [
                'sku' => 'PIN-PIN-CUATRO', 'name' => 'Pincheta cuatro carnes', 'category' => 'Pinchetas', 'price' => 89900,
                'recipe' => array_merge([
                    ['sku' => 'PIN-ING-RES', 'qty' => 180],
                    ['sku' => 'PIN-ING-CERDO', 'qty' => 180],
                    ['sku' => 'PIN-ING-POLLO', 'qty' => 180],
                    ['sku' => 'PIN-ING-CHORIZO', 'qty' => 2],
                    ['sku' => 'PIN-ING-MAZORCA', 'qty' => 1],
                ], $pinchetaBase),
            ],

            // Cortes y parrilla
            [
                'sku' => 'PIN-COR-PECHUGA', 'name' => 'Pechuga 350 g', 'category' => 'Cortes y parrilla', 'price' => 34900,
                'recipe' => [
                    ['sku' => 'PIN-ING-POLLO', 'qty' => 350],
                    ['sku' => 'PIN-ING-PAPA-CRIOLLA', 'qty' => 1],
                    ['sku' => 'PIN-ING-TARTARA', 'qty' => 1],
                ],
            ],
            [
                'sku' => 'PIN-COR-COSTILLAS', 'name' => 'Costillas BBQ 500 g', 'category' => 'Cortes y parrilla', 'price' => 39900,
                'recipe' => [
                    ['sku' => 'PIN-ING-COSTILLA', 'qty' => 500],
                    ['sku' => 'PIN-ING-PAPA-CRIOLLA', 'qty' => 1],
                    ['sku' => 'PIN-ING-PICO', 'qty' => 1],
                    ['sku' => 'PIN-ING-TARTARA', 'qty' => 1],
                ],
            ],
            [
                'sku' => 'PIN-COR-CHURRASCO', 'name' => 'Churrasco 350 g', 'category' => 'Cortes y parrilla', 'price' => 42900,
                'recipe' => [
                    ['sku' => 'PIN-ING-CHURRASCO', 'qty' => 350],
                    ['sku' => 'PIN-ING-PAPA-CRIOLLA', 'qty' => 1],
                    ['sku' => 'PIN-ING-TARTARA', 'qty' => 1],
                ],
            ],
            [
                'sku' => 'PIN-COR-PUNTA', 'name' => 'Punta de anca 350 g', 'category' => 'Cortes y parrilla', 'price' => 44900,
                'recipe' => [
                    ['sku' => 'PIN-ING-PUNTA-ANCA', 'qty' => 350],
                    ['sku' => 'PIN-ING-PAPA-CRIOLLA', 'qty' => 1],
                    ['sku' => 'PIN-ING-TARTARA', 'qty' => 1],
                ],
            ],
            [
                'sku' => 'PIN-COR-PARRILLA-MIX', 'name' => 'Parrilla mixta', 'category' => 'Cortes y parrilla', 'price' => 46900,
                'recipe' => [
                    ['sku' => 'PIN-ING-CHUNCHULLO', 'qty' => 120],
                    ['sku' => 'PIN-ING-RES', 'qty' => 120],
                    ['sku' => 'PIN-ING-POLLO', 'qty' => 120],
                    ['sku' => 'PIN-ING-MORCILLA', 'qty' => 1],
                    ['sku' => 'PIN-ING-AREPA', 'qty' => 2],
                ],
            ],
            [
                'sku' => 'PIN-COR-TOMAHAWK', 'name' => 'Tomahawk 400 g', 'category' => 'Cortes y parrilla', 'price' => 64900,
                'recipe' => [
                    ['sku' => 'PIN-ING-TOMAHAWK', 'qty' => 400],
                    ['sku' => 'PIN-ING-YUCA', 'qty' => 1],
                    ['sku' => 'PIN-ING-PICO', 'qty' => 1],
                    ['sku' => 'PIN-ING-TARTARA', 'qty' => 1],
                ],
            ],

            // Clásicos (default pincheta portion = pollo-based intermediate)
            [
                'sku' => 'PIN-CLA-BURRITO', 'name' => 'Burrito', 'category' => 'Clásicos a base de pincheta', 'price' => 29900,
                'recipe' => [
                    ['sku' => 'PIN-ING-TORTILLA', 'qty' => 1],
                    ['sku' => 'PIN-ING-PORCION-PINCHETA', 'qty' => 1],
                    ['sku' => 'PIN-ING-PICO', 'qty' => 1],
                    ['sku' => 'PIN-ING-TARTARA', 'qty' => 1],
                ],
            ],
            [
                'sku' => 'PIN-CLA-PEPITO', 'name' => 'Pepito', 'category' => 'Clásicos a base de pincheta', 'price' => 34900,
                'recipe' => [
                    ['sku' => 'PIN-ING-PAN-PEPITO', 'qty' => 1],
                    ['sku' => 'PIN-ING-PORCION-PINCHETA', 'qty' => 1],
                    ['sku' => 'PIN-ING-CHEDDAR', 'qty' => 1],
                    ['sku' => 'PIN-ING-HUEVO', 'qty' => 1],
                    ['sku' => 'PIN-ING-VEGETALES', 'qty' => 1],
                ],
            ],
            [
                'sku' => 'PIN-CLA-PINCHOPAN', 'name' => 'Pinchopan', 'category' => 'Clásicos a base de pincheta', 'price' => 32900,
                'recipe' => [
                    ['sku' => 'PIN-ING-PAN', 'qty' => 1],
                    ['sku' => 'PIN-ING-PORCION-PINCHETA', 'qty' => 1],
                    ['sku' => 'PIN-ING-TOCINETA', 'qty' => 2],
                    ['sku' => 'PIN-ING-QUESO', 'qty' => 40],
                    ['sku' => 'PIN-ING-MAIZ', 'qty' => 1],
                    ['sku' => 'PIN-ING-PAPA-CRIOLLA', 'qty' => 1],
                ],
            ],
            [
                'sku' => 'PIN-CLA-PATACON', 'name' => 'Patacón', 'category' => 'Clásicos a base de pincheta', 'price' => 29000,
                'recipe' => [
                    ['sku' => 'PIN-ING-PATACON', 'qty' => 2],
                    ['sku' => 'PIN-ING-PORCION-PINCHETA', 'qty' => 1],
                    ['sku' => 'PIN-ING-QUESO', 'qty' => 40],
                    ['sku' => 'PIN-ING-MAIZ', 'qty' => 1],
                    ['sku' => 'PIN-ING-TARTARA', 'qty' => 1],
                    ['sku' => 'PIN-ING-BBQ', 'qty' => 1],
                ],
            ],
            [
                'sku' => 'PIN-CLA-AREPA', 'name' => 'Pincho con arepa', 'category' => 'Clásicos a base de pincheta', 'price' => 29900,
                'recipe' => [
                    ['sku' => 'PIN-ING-AREPA', 'qty' => 2],
                    ['sku' => 'PIN-ING-PORCION-PINCHETA', 'qty' => 1],
                    ['sku' => 'PIN-ING-QUESO', 'qty' => 40],
                    ['sku' => 'PIN-ING-MAIZ', 'qty' => 1],
                    ['sku' => 'PIN-ING-TARTARA', 'qty' => 1],
                    ['sku' => 'PIN-ING-BBQ', 'qty' => 1],
                ],
            ],
            [
                'sku' => 'PIN-CLA-MAICITOS', 'name' => 'Maicitos', 'category' => 'Clásicos a base de pincheta', 'price' => 34900,
                'recipe' => [
                    ['sku' => 'PIN-ING-PORCION-PINCHETA', 'qty' => 1],
                    ['sku' => 'PIN-ING-QUESO', 'qty' => 50],
                    ['sku' => 'PIN-ING-MAIZ', 'qty' => 1],
                    ['sku' => 'PIN-ING-TOCINETA', 'qty' => 2],
                    ['sku' => 'PIN-ING-PAPA-CRIOLLA', 'qty' => 1],
                ],
            ],
            [
                'sku' => 'PIN-CLA-BURGER', 'name' => 'Pincheta burger', 'category' => 'Clásicos a base de pincheta', 'price' => 34900,
                'recipe' => [
                    ['sku' => 'PIN-ING-PAN-BURGER', 'qty' => 1],
                    ['sku' => 'PIN-ING-PORCION-PINCHETA', 'qty' => 1],
                    ['sku' => 'PIN-ING-TOCINETA', 'qty' => 2],
                    ['sku' => 'PIN-ING-QUESO', 'qty' => 40],
                    ['sku' => 'PIN-ING-MAIZ', 'qty' => 1],
                    ['sku' => 'PIN-ING-PAPA-CRIOLLA', 'qty' => 1],
                ],
            ],

            // Especiales
            [
                'sku' => 'PIN-ESP-SALCHIPOLLO', 'name' => 'Salchipollo', 'category' => 'Especiales de la casa', 'price' => 34900,
                'recipe' => [
                    ['sku' => 'PIN-ING-SALCHICHA', 'qty' => 2],
                    ['sku' => 'PIN-ING-POLLO', 'qty' => 150],
                    ['sku' => 'PIN-ING-TOCINETA', 'qty' => 2],
                    ['sku' => 'PIN-ING-MAIZ', 'qty' => 1],
                    ['sku' => 'PIN-ING-PAPA-CRIOLLA', 'qty' => 1],
                    ['sku' => 'PIN-ING-TARTARA', 'qty' => 1],
                ],
            ],
            [
                'sku' => 'PIN-ESP-CLUB', 'name' => 'Club House', 'category' => 'Especiales de la casa', 'price' => 27900,
                'recipe' => [
                    ['sku' => 'PIN-ING-PAN', 'qty' => 2],
                    ['sku' => 'PIN-ING-POLLO', 'qty' => 120],
                    ['sku' => 'PIN-ING-QUESO', 'qty' => 40],
                    ['sku' => 'PIN-ING-TOCINETA', 'qty' => 2],
                    ['sku' => 'PIN-ING-HUEVO', 'qty' => 1],
                    ['sku' => 'PIN-ING-VEGETALES', 'qty' => 1],
                    ['sku' => 'PIN-ING-PAPA-FRANCESA', 'qty' => 1],
                ],
            ],
            [
                'sku' => 'PIN-ESP-BURGER-TARTARA', 'name' => 'Hamburguesa pollo tártara', 'category' => 'Especiales de la casa', 'price' => 26900,
                'recipe' => [
                    ['sku' => 'PIN-ING-PAN-BURGER', 'qty' => 1],
                    ['sku' => 'PIN-ING-RES', 'qty' => 100],
                    ['sku' => 'PIN-ING-POLLO', 'qty' => 80],
                    ['sku' => 'PIN-ING-CHEDDAR', 'qty' => 1],
                    ['sku' => 'PIN-ING-TOMATE', 'qty' => 1],
                    ['sku' => 'PIN-ING-TOCINETA', 'qty' => 2],
                    ['sku' => 'PIN-ING-TARTARA', 'qty' => 1],
                ],
            ],
            [
                'sku' => 'PIN-ESP-CHORIPAN', 'name' => 'Choripán argentino', 'category' => 'Especiales de la casa', 'price' => 24900,
                'recipe' => [
                    ['sku' => 'PIN-ING-PAN', 'qty' => 1],
                    ['sku' => 'PIN-ING-CHORIZO', 'qty' => 1],
                    ['sku' => 'PIN-ING-CHIMICHURRI', 'qty' => 1],
                    ['sku' => 'PIN-ING-TARTARA', 'qty' => 1],
                ],
            ],
            [
                'sku' => 'PIN-ESP-TRICITO', 'name' => 'Tricito', 'category' => 'Especiales de la casa', 'price' => 35900,
                'recipe' => [
                    ['sku' => 'PIN-ING-POLLO', 'qty' => 100],
                    ['sku' => 'PIN-ING-CHORIZO', 'qty' => 1],
                    ['sku' => 'PIN-ING-CERDO', 'qty' => 100],
                    ['sku' => 'PIN-ING-PAPA-CRIOLLA', 'qty' => 1],
                    ['sku' => 'PIN-ING-CEBOLLA-CROC', 'qty' => 1],
                    ['sku' => 'PIN-ING-QUESO', 'qty' => 40],
                    ['sku' => 'PIN-ING-MAIZ', 'qty' => 1],
                    ['sku' => 'PIN-ING-HUEVO-COD', 'qty' => 3],
                ],
            ],
            [
                'sku' => 'PIN-ESP-PICADA', 'name' => 'Picada personal', 'category' => 'Especiales de la casa', 'price' => 46900,
                'recipe' => [
                    ['sku' => 'PIN-ING-CHATA', 'qty' => 120],
                    ['sku' => 'PIN-ING-POLLO', 'qty' => 120],
                    ['sku' => 'PIN-ING-CHICHARRON', 'qty' => 120],
                    ['sku' => 'PIN-ING-CHORIZO', 'qty' => 1],
                    ['sku' => 'PIN-ING-PAPA-CRIOLLA', 'qty' => 1],
                ],
            ],
        ];
    }

    protected function drinkCatalog(): array
    {
        $rows = [];

        foreach ([
            ['Coco', 'PIN-BEB-LIM-COCO'],
            ['Hierbabuena', 'PIN-BEB-LIM-HIERBA'],
            ['Mango verde', 'PIN-BEB-LIM-MANGO'],
        ] as [$flavor, $sku]) {
            $rows[] = [
                'sku' => $sku,
                'name' => "Limonada {$flavor}",
                'price' => 12900,
                'initial_stock' => 50,
                'unit_cost' => 2500,
            ];
        }

        foreach ([
            ['Clásica', 'PIN-BEB-CHE-CLASICA'],
            ['Frutos rojos', 'PIN-BEB-CHE-ROJOS'],
            ['Frutos amarillos', 'PIN-BEB-CHE-AMAR'],
        ] as [$flavor, $sku]) {
            $rows[] = [
                'sku' => $sku,
                'name' => "Chelada {$flavor}",
                'price' => 14900,
                'initial_stock' => 40,
                'unit_cost' => 3000,
            ];
        }

        $rows[] = ['sku' => 'PIN-BEB-MARACUMANGO', 'name' => 'Maracumango', 'price' => 14900, 'initial_stock' => 40, 'unit_cost' => 3000];
        $rows[] = ['sku' => 'PIN-BEB-MICHELADA', 'name' => 'Michelada', 'price' => 12900, 'initial_stock' => 40, 'unit_cost' => 2800];
        $rows[] = ['sku' => 'PIN-BEB-LIM-ROSAS', 'name' => 'Limonada de rosas', 'price' => 20900, 'initial_stock' => 30, 'unit_cost' => 4500];

        foreach ([
            'Mora', 'Banano', 'Mango', 'Lulo', 'Fresa', 'Naranja', 'Limón', 'Cereza', 'Maracuyá',
        ] as $flavor) {
            $slug = strtoupper(str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ', ' '], ['A', 'E', 'I', 'O', 'U', 'N', '-'], $flavor));
            $rows[] = [
                'sku' => "PIN-BEB-FRAP-{$slug}",
                'name' => "Frappé {$flavor}",
                'price' => 10900,
                'initial_stock' => 40,
                'unit_cost' => 2200,
            ];
        }

        foreach ([
            ['Oreo', 'PIN-BEB-MAL-OREO'],
            ['Nutella', 'PIN-BEB-MAL-NUTELLA'],
            ['Vainilla Blue', 'PIN-BEB-MAL-VAINILLA'],
        ] as [$flavor, $sku]) {
            $rows[] = [
                'sku' => $sku,
                'name' => "Malteada {$flavor}",
                'price' => 19900,
                'initial_stock' => 30,
                'unit_cost' => 4500,
            ];
        }

        return array_map(function (array $row) {
            return array_merge([
                'base_unit_name' => 'vaso',
                'category' => 'Bebidas',
                'inventory_mode' => Product::INVENTORY_STANDARD,
                'min_stock' => 10,
            ], $row);
        }, $rows);
    }
}
