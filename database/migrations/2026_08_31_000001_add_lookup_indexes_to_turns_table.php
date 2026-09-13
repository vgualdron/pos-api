<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('turns', function (Blueprint $table) {
            $table->index(
                ['place_id', 'patient_document', 'created_at'],
                'turns_place_document_created_idx'
            );
            $table->index(
                ['place_id', 'service_id', 'created_at'],
                'turns_place_service_created_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('turns', function (Blueprint $table) {
            $table->dropIndex('turns_place_document_created_idx');
            $table->dropIndex('turns_place_service_created_idx');
        });
    }
};
