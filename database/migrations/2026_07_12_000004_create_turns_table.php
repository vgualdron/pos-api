<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained('places')->restrictOnDelete();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->foreignId('station_id')->constrained('stations')->restrictOnDelete();
            $table->foreignId('called_by_station_id')->nullable()->constrained('stations')->nullOnDelete();
            $table->foreignId('parent_turn_id')->nullable()->constrained('turns')->nullOnDelete();
            $table->string('ticket_number');
            $table->unsignedInteger('daily_sequence');
            $table->string('patient_name')->nullable();
            $table->string('patient_document')->nullable();
            $table->string('patient_phone')->nullable();
            $table->string('priority')->default('normal');
            $table->string('status')->default('waiting');
            $table->string('qr_token')->unique();
            $table->timestamp('called_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['place_id', 'service_id', 'status', 'created_at']);
            $table->index(['patient_document', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turns');
    }
};
