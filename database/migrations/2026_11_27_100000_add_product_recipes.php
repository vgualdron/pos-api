<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'inventory_mode')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('inventory_mode', 20)->default('standard')->after('base_unit_name');
            });
        }

        if (! Schema::hasTable('product_recipe_items')) {
            Schema::create('product_recipe_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('ingredient_product_id')->constrained('products')->restrictOnDelete();
                $table->decimal('qty_base', 14, 4);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['product_id', 'ingredient_product_id']);
                $table->index(['ingredient_product_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_recipe_items');

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'inventory_mode')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('inventory_mode');
            });
        }
    }
};
