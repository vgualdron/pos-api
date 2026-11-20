<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds initial stock for demo beverages if the catalog migration already ran
 * without inventory lots.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('stock_lots') || !Schema::hasTable('products')) {
            return;
        }

        $now = now();
        $stocks = [
            'COL-400' => ['qty' => 120, 'unit_cost' => 1800],
            'COC-400' => ['qty' => 120, 'unit_cost' => 1900],
            'AGU-LATA' => ['qty' => 240, 'unit_cost' => 2200],
        ];

        $products = DB::table('products')
            ->whereIn('sku', array_keys($stocks))
            ->get(['id', 'place_id', 'sku']);

        foreach ($products as $product) {
            $cfg = $stocks[$product->sku] ?? null;
            if (!$cfg) {
                continue;
            }

            $lotCode = 'SEED-'.$product->sku;
            $exists = DB::table('stock_lots')
                ->where('place_id', $product->place_id)
                ->where('product_id', $product->id)
                ->where('lot_code', $lotCode)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('stock_lots')->insert([
                'place_id' => $product->place_id,
                'product_id' => $product->id,
                'purchase_id' => null,
                'lot_code' => $lotCode,
                'purchased_at' => $now,
                'expires_at' => null,
                'unit_cost' => $cfg['unit_cost'],
                'qty_initial' => $cfg['qty'],
                'qty_remaining' => $cfg['qty'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('stock_lots')) {
            return;
        }

        DB::table('stock_lots')
            ->whereIn('lot_code', ['SEED-COL-400', 'SEED-COC-400', 'SEED-AGU-LATA'])
            ->delete();
    }
};
