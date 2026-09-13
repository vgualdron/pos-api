<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_follow_ups', function (Blueprint $table) {
            $table->id();
            // Servicio inicial (el que se atiende primero)
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            // Servicio que se puede generar después
            $table->foreignId('follow_up_service_id')->constrained('services')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['service_id', 'follow_up_service_id']);
        });

        // Migrar: hijo.requires_parent = padre  →  padre puede generar hijo después
        $rows = DB::table('services')
            ->whereNotNull('requires_parent_service_id')
            ->select('id', 'requires_parent_service_id')
            ->get();

        $now = now();
        foreach ($rows as $row) {
            DB::table('service_follow_ups')->insertOrIgnore([
                'service_id' => $row->requires_parent_service_id,
                'follow_up_service_id' => $row->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('service_follow_ups');
    }
};
