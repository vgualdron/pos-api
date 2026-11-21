<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('place_pos_resolution_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->string('resolution_number')->nullable();
            $table->date('resolution_date')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->string('prefix', 32)->nullable();
            $table->unsignedBigInteger('range_from')->nullable();
            $table->unsignedBigInteger('range_to')->nullable();
            $table->unsignedBigInteger('current_number')->nullable();
            $table->timestamps();

            $table->unique('place_id');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->string('pos_number')->nullable()->after('document_type');
            $table->string('pos_prefix', 32)->nullable()->after('pos_number');
            $table->unsignedBigInteger('pos_consecutive')->nullable()->after('pos_prefix');
            $table->string('pos_resolution_number')->nullable()->after('pos_consecutive');
            $table->date('pos_resolution_date')->nullable()->after('pos_resolution_number');
            $table->unsignedBigInteger('pos_range_from')->nullable()->after('pos_resolution_date');
            $table->unsignedBigInteger('pos_range_to')->nullable()->after('pos_range_from');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn([
                'pos_number',
                'pos_prefix',
                'pos_consecutive',
                'pos_resolution_number',
                'pos_resolution_date',
                'pos_range_from',
                'pos_range_to',
            ]);
        });

        Schema::dropIfExists('place_pos_resolution_settings');
    }
};
