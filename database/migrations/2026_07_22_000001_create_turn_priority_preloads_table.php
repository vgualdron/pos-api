<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turn_priority_preloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();
            $table->date('scheduled_date');
            $table->string('patient_document', 50);
            $table->string('patient_name');
            $table->string('patient_phone', 30)->nullable();
            $table->string('patient_company')->nullable();
            $table->string('priority', 30)->default('priority');
            $table->string('status', 20)->default('pending'); // pending|used|cancelled
            $table->timestamp('used_at')->nullable();
            $table->foreignId('used_turn_id')->nullable()->constrained('turns')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['place_id', 'scheduled_date', 'patient_document'],
                'turn_priority_preloads_unique_doc'
            );
            $table->index(['place_id', 'scheduled_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turn_priority_preloads');
    }
};
