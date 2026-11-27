<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_categories')) {
            return;
        }

        if (! Schema::hasColumn('product_categories', 'show_on_register')) {
            Schema::table('product_categories', function (Blueprint $table) {
                $table->boolean('show_on_register')->default(true)->after('status');
            });
        }

        // Insumos: keep active, hide from register chips.
        DB::table('product_categories')
            ->where('name', 'Insumos')
            ->update([
                'status' => 'activo',
                'show_on_register' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_categories')) {
            return;
        }

        if (Schema::hasColumn('product_categories', 'show_on_register')) {
            Schema::table('product_categories', function (Blueprint $table) {
                $table->dropColumn('show_on_register');
            });
        }
    }
};
