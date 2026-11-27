<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('table_orders')) {
            Schema::create('table_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();
                $table->foreignId('dining_table_id')->constrained('dining_tables')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 20)->default('open');
                $table->timestamp('opened_at');
                $table->timestamp('closed_at')->nullable();
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('discount_amount', 14, 2)->default(0);
                $table->decimal('discount_percent', 8, 2)->nullable();
                $table->decimal('tax_base', 14, 2)->default(0);
                $table->decimal('tax_total', 14, 2)->default(0);
                $table->decimal('total', 14, 2)->default(0);
                $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
                $table->timestamps();

                $table->index(['place_id', 'status']);
                $table->index(['dining_table_id', 'status']);
            });
        }

        if (! Schema::hasTable('table_order_lines')) {
            Schema::create('table_order_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('table_order_id')->constrained('table_orders')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
                $table->foreignId('product_presentation_id')->constrained('product_presentations')->restrictOnDelete();
                $table->string('product_name', 180);
                $table->string('presentation_name', 120)->nullable();
                $table->decimal('qty', 14, 4);
                $table->decimal('factor', 14, 4)->default(1);
                $table->decimal('qty_base', 14, 4);
                $table->decimal('unit_price', 14, 2);
                $table->foreignId('tax_id')->nullable()->constrained('taxes')->nullOnDelete();
                $table->decimal('tax_rate', 8, 2)->default(0);
                $table->string('tax_name', 80)->nullable();
                $table->string('tax_code', 40)->nullable();
                $table->decimal('discount_amount', 14, 2)->default(0);
                $table->decimal('line_base', 14, 2)->default(0);
                $table->decimal('tax_amount', 14, 2)->default(0);
                $table->decimal('line_total', 14, 2)->default(0);
                $table->string('notes', 500)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['table_order_id', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('table_order_lines');
        Schema::dropIfExists('table_orders');
    }
};
