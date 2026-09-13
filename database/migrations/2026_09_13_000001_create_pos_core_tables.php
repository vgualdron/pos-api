<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained('places')->restrictOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();
            $table->string('base_unit_name')->default('unidad');
            $table->string('status')->default('activo');
            $table->timestamps();

            $table->index(['place_id', 'status']);
            $table->unique(['place_id', 'sku']);
        });

        Schema::create('product_presentations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('factor', 14, 4);
            $table->decimal('sale_price', 14, 2);
            $table->string('barcode')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('status')->default('activo');
            $table->timestamps();

            $table->index(['product_id', 'status']);
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained('places')->restrictOnDelete();
            $table->foreignId('station_id')->nullable()->constrained('stations')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('purchased_at');
            $table->string('supplier_name')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('completed');
            $table->timestamps();

            $table->index(['place_id', 'purchased_at']);
        });

        Schema::create('stock_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained('places')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained('purchases')->nullOnDelete();
            $table->string('lot_code')->nullable();
            $table->timestamp('purchased_at');
            $table->date('expires_at')->nullable();
            $table->decimal('unit_cost', 14, 4);
            $table->decimal('qty_initial', 14, 4);
            $table->decimal('qty_remaining', 14, 4);
            $table->timestamps();

            $table->index(['place_id', 'product_id', 'purchased_at']);
        });

        Schema::create('purchase_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_presentation_id')->nullable()->constrained('product_presentations')->nullOnDelete();
            $table->foreignId('stock_lot_id')->nullable()->constrained('stock_lots')->nullOnDelete();
            $table->decimal('qty_presentations', 14, 4);
            $table->decimal('factor', 14, 4);
            $table->decimal('qty_base', 14, 4);
            $table->decimal('unit_cost', 14, 4);
            $table->timestamps();
        });

        Schema::create('register_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained('places')->restrictOnDelete();
            $table->foreignId('station_id')->constrained('stations')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('opening_cash', 14, 2)->default(0);
            $table->decimal('closing_cash', 14, 2)->nullable();
            $table->string('status')->default('open');
            $table->timestamps();

            $table->index(['station_id', 'status']);
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained('places')->restrictOnDelete();
            $table->foreignId('station_id')->constrained('stations')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('register_session_id')->nullable()->constrained('register_sessions')->nullOnDelete();
            $table->timestamp('sold_at');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('cost_total', 14, 4)->default(0);
            $table->string('status')->default('completed');
            $table->timestamps();

            $table->index(['place_id', 'sold_at']);
        });

        Schema::create('sale_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_presentation_id')->constrained('product_presentations')->restrictOnDelete();
            $table->decimal('qty', 14, 4);
            $table->decimal('factor', 14, 4);
            $table->decimal('qty_base', 14, 4);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->decimal('cost_total', 14, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('sale_line_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_line_id')->constrained('sale_lines')->cascadeOnDelete();
            $table->foreignId('stock_lot_id')->constrained('stock_lots')->restrictOnDelete();
            $table->decimal('qty_base', 14, 4);
            $table->decimal('unit_cost', 14, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_line_lots');
        Schema::dropIfExists('sale_lines');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('register_sessions');
        Schema::dropIfExists('purchase_lines');
        Schema::dropIfExists('stock_lots');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('product_presentations');
        Schema::dropIfExists('products');
    }
};
