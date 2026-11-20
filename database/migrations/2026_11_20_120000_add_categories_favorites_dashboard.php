<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_categories')) {
            Schema::create('product_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();
                $table->string('name', 120);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->string('status', 20)->default('activo');
                $table->timestamps();

                $table->unique(['place_id', 'name']);
                $table->index(['place_id', 'status']);
            });
        }

        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'product_category_id')) {
                $table->foreignId('product_category_id')
                    ->nullable()
                    ->after('place_id')
                    ->constrained('product_categories')
                    ->nullOnDelete();
            }
        });

        if (!Schema::hasTable('product_favorites')) {
            Schema::create('product_favorites', function (Blueprint $table) {
                $table->id();
                $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['place_id', 'product_id']);
                $table->index(['place_id', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_favorites');

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'product_category_id')) {
                $table->dropConstrainedForeignId('product_category_id');
            }
        });

        Schema::dropIfExists('product_categories');
    }
};
