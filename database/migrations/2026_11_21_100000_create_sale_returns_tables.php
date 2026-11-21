<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sale_lines') && !Schema::hasColumn('sale_lines', 'qty_returned')) {
            Schema::table('sale_lines', function (Blueprint $table) {
                $table->decimal('qty_returned', 14, 4)->default(0)->after('qty');
            });
        }

        if (Schema::hasTable('sale_line_lots') && !Schema::hasColumn('sale_line_lots', 'qty_base_returned')) {
            Schema::table('sale_line_lots', function (Blueprint $table) {
                $table->decimal('qty_base_returned', 14, 4)->default(0)->after('qty_base');
            });
        }

        if (Schema::hasTable('sales') && !Schema::hasColumn('sales', 'returned_total')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->decimal('returned_total', 14, 2)->default(0)->after('cost_total');
                $table->decimal('returned_cost_total', 14, 4)->default(0)->after('returned_total');
            });
        }

        if (!Schema::hasTable('sale_returns')) {
            Schema::create('sale_returns', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
                $table->foreignId('place_id')->constrained('places');
                $table->foreignId('station_id')->nullable()->constrained('stations')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('register_session_id')->nullable()->constrained('register_sessions')->nullOnDelete();
                $table->timestamp('returned_at');
                $table->string('reason')->nullable();
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('discount_amount', 14, 2)->default(0);
                $table->decimal('tax_base', 14, 2)->default(0);
                $table->decimal('tax_total', 14, 2)->default(0);
                $table->decimal('total', 14, 2)->default(0);
                $table->decimal('cost_total', 14, 4)->default(0);
                // Reserved for DIAN credit note linkage later.
                $table->string('dian_credit_note_id')->nullable();
                $table->string('dian_status')->nullable();
                $table->timestamps();

                $table->index(['sale_id', 'returned_at']);
                $table->index(['place_id', 'returned_at']);
            });
        }

        if (!Schema::hasTable('sale_return_lines')) {
            Schema::create('sale_return_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sale_return_id')->constrained('sale_returns')->cascadeOnDelete();
                $table->foreignId('sale_line_id')->constrained('sale_lines')->cascadeOnDelete();
                $table->decimal('qty', 14, 4);
                $table->decimal('factor', 14, 4);
                $table->decimal('qty_base', 14, 4);
                $table->decimal('unit_price', 14, 2);
                $table->foreignId('tax_id')->nullable()->constrained('taxes')->nullOnDelete();
                $table->decimal('tax_rate', 8, 2)->default(0);
                $table->string('tax_name')->nullable();
                $table->string('tax_code')->nullable();
                $table->decimal('discount_amount', 14, 2)->default(0);
                $table->decimal('line_base', 14, 2)->default(0);
                $table->decimal('tax_amount', 14, 2)->default(0);
                $table->decimal('line_total', 14, 2)->default(0);
                $table->decimal('cost_total', 14, 4)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('sale_return_line_lots')) {
            Schema::create('sale_return_line_lots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sale_return_line_id')->constrained('sale_return_lines')->cascadeOnDelete();
                $table->foreignId('sale_line_lot_id')->nullable()->constrained('sale_line_lots')->nullOnDelete();
                $table->foreignId('stock_lot_id')->constrained('stock_lots');
                $table->decimal('qty_base', 14, 4);
                $table->decimal('unit_cost', 14, 4)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('sale_return_payments')) {
            Schema::create('sale_return_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sale_return_id')->constrained('sale_returns')->cascadeOnDelete();
                $table->string('payment_method', 32);
                $table->decimal('amount', 14, 2);
                $table->unsignedSmallInteger('sort_order')->default(1);
                $table->timestamps();

                $table->index(['sale_return_id', 'payment_method']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_return_payments');
        Schema::dropIfExists('sale_return_line_lots');
        Schema::dropIfExists('sale_return_lines');
        Schema::dropIfExists('sale_returns');

        if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'returned_total')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropColumn(['returned_total', 'returned_cost_total']);
            });
        }

        if (Schema::hasTable('sale_line_lots') && Schema::hasColumn('sale_line_lots', 'qty_base_returned')) {
            Schema::table('sale_line_lots', function (Blueprint $table) {
                $table->dropColumn('qty_base_returned');
            });
        }

        if (Schema::hasTable('sale_lines') && Schema::hasColumn('sale_lines', 'qty_returned')) {
            Schema::table('sale_lines', function (Blueprint $table) {
                $table->dropColumn('qty_returned');
            });
        }
    }
};
