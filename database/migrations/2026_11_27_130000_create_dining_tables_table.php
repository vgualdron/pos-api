<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dining_tables')) {
            return;
        }

        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 120);
            $table->unsignedSmallInteger('seats')->nullable();
            $table->string('zone', 80)->nullable();
            $table->string('operational_status', 20)->default('free');
            $table->string('status', 20)->default('activo');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['place_id', 'code']);
            $table->index(['place_id', 'status', 'operational_status']);
            $table->index(['place_id', 'zone', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dining_tables');
    }
};
