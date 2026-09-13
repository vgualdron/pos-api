<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('display_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained('places')->restrictOnDelete();
            $table->string('name');
            $table->string('message', 200);
            $table->string('type')->default('info');
            $table->string('status')->default('activo');
            $table->unsignedInteger('display_order')->default(0);
            $table->unsignedSmallInteger('duration_seconds')->default(15);
            $table->unsignedTinyInteger('repeat_count')->default(1);
            $table->boolean('speak_enabled')->default(true);
            $table->boolean('show_on_screen')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['place_id', 'status', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('display_alerts');
    }
};
