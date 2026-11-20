<?php

use Database\Seeders\PosReportsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reports')) {
            return;
        }

        (new PosReportsSeeder())->run();
    }

    public function down(): void
    {
        if (!Schema::hasTable('reports')) {
            return;
        }

        \Illuminate\Support\Facades\DB::table('reports')
            ->where('name', 'like', 'POS · %')
            ->delete();
    }
};
