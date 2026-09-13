<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained('places')->restrictOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('prefix', 10)->default('A');
            $table->text('description')->nullable();
            $table->foreignId('requires_parent_service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->unsignedSmallInteger('estimated_minutes')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->string('status')->default('activo');
            $table->timestamps();

            $table->unique(['place_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
