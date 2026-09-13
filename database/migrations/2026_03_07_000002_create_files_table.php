<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('url');
            $table->unsignedBigInteger('model_id')->nullable();
            $table->string('model_name')->nullable();
            $table->string('type')->nullable();
            $table->string('extension', 20)->nullable();
            $table->string('status')->nullable();
            $table->text('observation')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->unsignedBigInteger('registered_by')->nullable();
            $table->dateTime('registered_date')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->dateTime('reviewed_date')->nullable();
            $table->timestamps();

            $table->index(['model_name', 'model_id']);
            $table->index('status');

            $table->foreign('registered_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
