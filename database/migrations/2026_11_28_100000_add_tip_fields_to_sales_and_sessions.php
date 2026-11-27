<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'tip_amount')) {
                $table->decimal('tip_amount', 14, 2)->default(0)->after('total');
            }
            if (! Schema::hasColumn('sales', 'tip_percent')) {
                $table->decimal('tip_percent', 5, 2)->nullable()->after('tip_amount');
            }
        });

        Schema::table('register_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('register_sessions', 'tips_total')) {
                $table->decimal('tips_total', 14, 2)->default(0)->after('sales_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'tip_percent')) {
                $table->dropColumn('tip_percent');
            }
            if (Schema::hasColumn('sales', 'tip_amount')) {
                $table->dropColumn('tip_amount');
            }
        });

        Schema::table('register_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('register_sessions', 'tips_total')) {
                $table->dropColumn('tips_total');
            }
        });
    }
};
