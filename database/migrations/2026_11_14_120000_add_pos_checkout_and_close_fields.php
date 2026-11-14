<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('payment_method')->default('cash')->after('status');
            $table->decimal('amount_received', 14, 2)->nullable()->after('payment_method');
            $table->decimal('change_amount', 14, 2)->default(0)->after('amount_received');
            $table->timestamp('cancelled_at')->nullable()->after('change_amount');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->string('cancel_reason')->nullable()->after('cancelled_by');
        });

        Schema::table('register_sessions', function (Blueprint $table) {
            $table->decimal('expected_cash', 14, 2)->nullable()->after('closing_cash');
            $table->decimal('cash_sales_total', 14, 2)->default(0)->after('expected_cash');
            $table->decimal('card_sales_total', 14, 2)->default(0)->after('cash_sales_total');
            $table->decimal('transfer_sales_total', 14, 2)->default(0)->after('card_sales_total');
            $table->unsignedInteger('sales_count')->default(0)->after('transfer_sales_total');
            $table->decimal('difference_cash', 14, 2)->nullable()->after('sales_count');
            $table->string('notes')->nullable()->after('difference_cash');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn([
                'payment_method',
                'amount_received',
                'change_amount',
                'cancelled_at',
                'cancel_reason',
            ]);
        });

        Schema::table('register_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'expected_cash',
                'cash_sales_total',
                'card_sales_total',
                'transfer_sales_total',
                'sales_count',
                'difference_cash',
                'notes',
            ]);
        });
    }
};
