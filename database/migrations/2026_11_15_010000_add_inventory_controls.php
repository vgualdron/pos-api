<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'min_stock')) {
                $table->decimal('min_stock', 14, 4)->default(0)->after('base_unit_name');
            }
        });

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained('places')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('stock_lot_id')->constrained('stock_lots')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40);
            $table->decimal('qty_before', 14, 4);
            $table->decimal('qty_delta', 14, 4);
            $table->decimal('qty_after', 14, 4);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['place_id', 'created_at']);
            $table->index(['stock_lot_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'min_stock')) {
                $table->dropColumn('min_stock');
            }
        });
    }
};
