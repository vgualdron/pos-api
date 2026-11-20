<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sale_payments')) {
            Schema::create('sale_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
                $table->string('payment_method', 32);
                $table->decimal('amount', 14, 2);
                $table->unsignedSmallInteger('sort_order')->default(1);
                $table->timestamps();

                $table->index(['sale_id', 'payment_method']);
            });
        }

        if (!Schema::hasTable('sales') || !Schema::hasTable('sale_payments')) {
            return;
        }

        // Backfill legacy single-method sales into sale_payments.
        $existing = DB::table('sale_payments')->distinct()->pluck('sale_id')->all();
        $existingLookup = array_fill_keys($existing, true);

        DB::table('sales')
            ->select('id', 'payment_method', 'total', 'sold_at', 'created_at', 'updated_at')
            ->orderBy('id')
            ->chunkById(500, function ($sales) use ($existingLookup) {
                $rows = [];
                $now = now();
                foreach ($sales as $sale) {
                    if (isset($existingLookup[$sale->id])) {
                        continue;
                    }
                    $method = $sale->payment_method ?: 'cash';
                    if ($method === 'mixed') {
                        $method = 'cash';
                    }
                    $rows[] = [
                        'sale_id' => $sale->id,
                        'payment_method' => $method,
                        'amount' => round((float) $sale->total, 2),
                        'sort_order' => 1,
                        'created_at' => $sale->sold_at ?: ($sale->created_at ?: $now),
                        'updated_at' => $sale->updated_at ?: $now,
                    ];
                }
                if ($rows) {
                    DB::table('sale_payments')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
    }
};
