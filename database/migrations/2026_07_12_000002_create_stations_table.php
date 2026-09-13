<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained('places')->restrictOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('type')->default('kiosk');
            $table->boolean('can_generate_tickets')->default(false);
            $table->boolean('can_call_tickets')->default(false);
            $table->string('status')->default('activo');
            $table->timestamps();

            $table->unique(['place_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stations');
    }
};
