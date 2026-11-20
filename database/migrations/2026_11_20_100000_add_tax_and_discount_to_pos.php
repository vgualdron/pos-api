<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_presentations', function (Blueprint $table) {
            if (!Schema::hasColumn('product_presentations', 'tax_rate')) {
                // Sale price is IVA-included when tax_rate > 0 (common in Colombian retail).
                $table->decimal('tax_rate', 5, 2)->default(19)->after('sale_price');
            }
        });

        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'discount_amount')) {
                $table->decimal('discount_amount', 14, 2)->default(0)->after('subtotal');
            }
            if (!Schema::hasColumn('sales', 'discount_percent')) {
                $table->decimal('discount_percent', 5, 2)->nullable()->after('discount_amount');
            }
            if (!Schema::hasColumn('sales', 'tax_base')) {
                $table->decimal('tax_base', 14, 2)->default(0)->after('discount_percent');
            }
            if (!Schema::hasColumn('sales', 'tax_total')) {
                $table->decimal('tax_total', 14, 2)->default(0)->after('tax_base');
            }
        });

        Schema::table('sale_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_lines', 'tax_rate')) {
                $table->decimal('tax_rate', 5, 2)->default(0)->after('unit_price');
            }
            if (!Schema::hasColumn('sale_lines', 'discount_amount')) {
                $table->decimal('discount_amount', 14, 2)->default(0)->after('tax_rate');
            }
            if (!Schema::hasColumn('sale_lines', 'line_base')) {
                $table->decimal('line_base', 14, 2)->default(0)->after('discount_amount');
            }
            if (!Schema::hasColumn('sale_lines', 'tax_amount')) {
                $table->decimal('tax_amount', 14, 2)->default(0)->after('line_base');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sale_lines', function (Blueprint $table) {
            foreach (['tax_amount', 'line_base', 'discount_amount', 'tax_rate'] as $column) {
                if (Schema::hasColumn('sale_lines', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('sales', function (Blueprint $table) {
            foreach (['tax_total', 'tax_base', 'discount_percent', 'discount_amount'] as $column) {
                if (Schema::hasColumn('sales', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('product_presentations', function (Blueprint $table) {
            if (Schema::hasColumn('product_presentations', 'tax_rate')) {
                $table->dropColumn('tax_rate');
            }
        });
    }
};
