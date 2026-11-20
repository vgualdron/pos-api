<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('products') || !Schema::hasTable('product_presentations')) {
            return;
        }

        $places = DB::table('places')->orderBy('id')->get(['id']);
        if ($places->isEmpty()) {
            return;
        }

        $taxId = null;
        $taxRate = 19.0;
        if (Schema::hasTable('taxes')) {
            $tax = DB::table('taxes')->where('code', 'IVA_19')->first()
                ?: DB::table('taxes')->where('status', 'activo')->orderBy('sort_order')->first();
            if ($tax) {
                $taxId = (int) $tax->id;
                $taxRate = (float) $tax->rate;
            }
        }

        $now = now();
        $catalog = $this->catalog();

        foreach ($places as $place) {
            $placeId = (int) $place->id;
            $categoryIds = $this->ensureCategories($placeId, $now);

            foreach ($catalog as $item) {
                $existing = DB::table('products')
                    ->where('place_id', $placeId)
                    ->where('sku', $item['sku'])
                    ->first();

                $payload = [
                    'name' => $item['name'],
                    'barcode' => $item['barcode'],
                    'base_unit_name' => $item['base_unit_name'],
                    'status' => 'activo',
                    'updated_at' => $now,
                ];

                if (Schema::hasColumn('products', 'min_stock')) {
                    $payload['min_stock'] = $item['min_stock'];
                }

                if (Schema::hasColumn('products', 'product_category_id')) {
                    $payload['product_category_id'] = $categoryIds[$item['category_key']] ?? null;
                }

                if ($existing) {
                    DB::table('products')->where('id', $existing->id)->update($payload);
                    $productId = (int) $existing->id;
                } else {
                    $productId = (int) DB::table('products')->insertGetId(array_merge($payload, [
                        'place_id' => $placeId,
                        'sku' => $item['sku'],
                        'created_at' => $now,
                    ]));
                }

                $this->syncPresentations($productId, $item['presentations'], $taxId, $taxRate, $now);
                $this->attachImage($productId, $item['image']);
                $this->ensureStock($placeId, $productId, $item, $now);
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('products')) {
            return;
        }

        $skus = collect($this->catalog())->pluck('sku')->all();
        $productIds = DB::table('products')->whereIn('sku', $skus)->pluck('id');

        if ($productIds->isEmpty()) {
            return;
        }

        if (Schema::hasTable('files')) {
            DB::table('files')
                ->where('model_name', 'products')
                ->where('name', 'PHOTO_PRODUCT')
                ->whereIn('model_id', $productIds->all())
                ->delete();
        }

        if (Schema::hasTable('product_favorites')) {
            DB::table('product_favorites')->whereIn('product_id', $productIds->all())->delete();
        }

        if (Schema::hasTable('stock_lots')) {
            DB::table('stock_lots')
                ->whereIn('product_id', $productIds->all())
                ->where('lot_code', 'like', 'SEED-%')
                ->delete();
        }

        DB::table('products')->whereIn('id', $productIds->all())->delete();
    }

    protected function catalog(): array
    {
        return [
            [
                'sku' => 'COL-400',
                'name' => 'Colombiana 400 ml',
                'barcode' => '7702090023400',
                'base_unit_name' => 'botella',
                'min_stock' => 12,
                'initial_stock' => 120,
                'unit_cost' => 1800,
                'category_key' => 'gaseosas',
                'image' => 'colombiana-400.webp',
                'presentations' => [
                    ['name' => 'Unidad', 'factor' => 1, 'sale_price' => 2800, 'is_default' => true],
                    ['name' => 'Sixpack', 'factor' => 6, 'sale_price' => 15000, 'is_default' => false],
                    ['name' => 'Bandeja', 'factor' => 24, 'sale_price' => 55000, 'is_default' => false],
                ],
            ],
            [
                'sku' => 'COC-400',
                'name' => 'Coca-Cola Original 400 ml',
                'barcode' => '7702535012181',
                'base_unit_name' => 'botella',
                'min_stock' => 12,
                'initial_stock' => 120,
                'unit_cost' => 1900,
                'category_key' => 'gaseosas',
                'image' => 'coca-cola-400.jpg',
                'presentations' => [
                    ['name' => 'Unidad', 'factor' => 1, 'sale_price' => 3000, 'is_default' => true],
                    ['name' => 'Sixpack', 'factor' => 6, 'sale_price' => 16000, 'is_default' => false],
                    ['name' => 'Bandeja', 'factor' => 24, 'sale_price' => 58000, 'is_default' => false],
                ],
            ],
            [
                'sku' => 'AGU-LATA',
                'name' => 'Cerveza Águila Original Lata',
                'barcode' => '7702004001234',
                'base_unit_name' => 'lata',
                'min_stock' => 24,
                'initial_stock' => 240,
                'unit_cost' => 2200,
                'category_key' => 'cervezas',
                'image' => 'aguila-lata.webp',
                'presentations' => [
                    ['name' => 'Unidad', 'factor' => 1, 'sale_price' => 3500, 'is_default' => true],
                    ['name' => 'Sixpack', 'factor' => 6, 'sale_price' => 18000, 'is_default' => false],
                    ['name' => 'Bandeja', 'factor' => 24, 'sale_price' => 65000, 'is_default' => false],
                ],
            ],
        ];
    }

    protected function ensureCategories(int $placeId, $now): array
    {
        if (!Schema::hasTable('product_categories')) {
            return [];
        }

        $defs = [
            'gaseosas' => ['name' => 'Gaseosas', 'sort_order' => 10],
            'cervezas' => ['name' => 'Cervezas', 'sort_order' => 20],
        ];

        $ids = [];
        foreach ($defs as $key => $def) {
            $existing = DB::table('product_categories')
                ->where('place_id', $placeId)
                ->where('name', $def['name'])
                ->first();

            if ($existing) {
                $ids[$key] = (int) $existing->id;
                continue;
            }

            $ids[$key] = (int) DB::table('product_categories')->insertGetId([
                'place_id' => $placeId,
                'name' => $def['name'],
                'sort_order' => $def['sort_order'],
                'status' => 'activo',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $ids;
    }

    protected function syncPresentations(int $productId, array $presentations, ?int $taxId, float $taxRate, $now): void
    {
        foreach ($presentations as $row) {
            $existing = DB::table('product_presentations')
                ->where('product_id', $productId)
                ->where('name', $row['name'])
                ->first();

            $payload = [
                'factor' => $row['factor'],
                'sale_price' => $row['sale_price'],
                'is_default' => (bool) $row['is_default'],
                'status' => 'activo',
                'updated_at' => $now,
            ];

            if (Schema::hasColumn('product_presentations', 'tax_id')) {
                $payload['tax_id'] = $taxId;
            }
            if (Schema::hasColumn('product_presentations', 'tax_rate')) {
                $payload['tax_rate'] = $taxRate;
            }

            if ($existing) {
                DB::table('product_presentations')->where('id', $existing->id)->update($payload);
            } else {
                DB::table('product_presentations')->insert(array_merge($payload, [
                    'product_id' => $productId,
                    'name' => $row['name'],
                    'barcode' => null,
                    'created_at' => $now,
                ]));
            }
        }
    }

    protected function ensureStock(int $placeId, int $productId, array $item, $now): void
    {
        if (!Schema::hasTable('stock_lots')) {
            return;
        }

        $lotCode = 'SEED-'.$item['sku'];
        $qty = (float) ($item['initial_stock'] ?? 0);
        $unitCost = (float) ($item['unit_cost'] ?? 0);
        if ($qty <= 0) {
            return;
        }

        $existing = DB::table('stock_lots')
            ->where('place_id', $placeId)
            ->where('product_id', $productId)
            ->where('lot_code', $lotCode)
            ->first();

        $payload = [
            'purchased_at' => $now,
            'unit_cost' => $unitCost,
            'qty_initial' => $qty,
            'qty_remaining' => $qty,
            'updated_at' => $now,
        ];

        if ($existing) {
            // Only refill when the seed lot was never sold down.
            if ((float) $existing->qty_remaining <= 0 || (float) $existing->qty_remaining === (float) $existing->qty_initial) {
                DB::table('stock_lots')->where('id', $existing->id)->update($payload);
            }
            return;
        }

        DB::table('stock_lots')->insert(array_merge($payload, [
            'place_id' => $placeId,
            'product_id' => $productId,
            'purchase_id' => null,
            'lot_code' => $lotCode,
            'expires_at' => null,
            'created_at' => $now,
        ]));
    }

    protected function attachImage(int $productId, string $fileName): void
    {
        if (!Schema::hasTable('files')) {
            return;
        }

        $source = database_path('seed-assets/products/'.$fileName);
        if (!is_file($source)) {
            return;
        }

        $extension = pathinfo($fileName, PATHINFO_EXTENSION) ?: 'jpg';
        $storedName = 'PHOTO_PRODUCT-'.time().'-'.$productId.'.'.$extension;
        $relativePath = $productId.'/'.$storedName;
        $url = 'products/'.$relativePath;

        try {
            Storage::disk('products')->makeDirectory((string) $productId);
            Storage::disk('products')->put($relativePath, file_get_contents($source));
        } catch (\Throwable $e) {
            // Storage may be unavailable in some environments; product rows still remain useful.
            return;
        }

        DB::table('files')
            ->where('name', 'PHOTO_PRODUCT')
            ->where('model_name', 'products')
            ->where('model_id', $productId)
            ->delete();

        DB::table('files')->insert([
            'name' => 'PHOTO_PRODUCT',
            'url' => $url,
            'model_id' => $productId,
            'model_name' => 'products',
            'status' => 'activo',
            'type' => 'image',
            'extension' => $extension,
            'registered_date' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
