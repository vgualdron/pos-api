<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dining_tables')) {
            return;
        }

        Schema::table('dining_tables', function (Blueprint $table) {
            if (! Schema::hasColumn('dining_tables', 'occupied_at')) {
                $table->timestamp('occupied_at')->nullable()->after('operational_status');
            }
            if (! Schema::hasColumn('dining_tables', 'order_taken_at')) {
                $table->timestamp('order_taken_at')->nullable()->after('occupied_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dining_tables')) {
            return;
        }

        Schema::table('dining_tables', function (Blueprint $table) {
            if (Schema::hasColumn('dining_tables', 'order_taken_at')) {
                $table->dropColumn('order_taken_at');
            }
            if (Schema::hasColumn('dining_tables', 'occupied_at')) {
                $table->dropColumn('occupied_at');
            }
        });
    }
};
