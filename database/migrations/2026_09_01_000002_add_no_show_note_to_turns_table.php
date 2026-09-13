<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('turns', function (Blueprint $table) {
            $table->string('no_show_note', 500)->nullable()->after('completed_at');
            $table->foreignId('no_show_station_id')
                ->nullable()
                ->after('no_show_note')
                ->constrained('stations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('turns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('no_show_station_id');
            $table->dropColumn('no_show_note');
        });
    }
};
