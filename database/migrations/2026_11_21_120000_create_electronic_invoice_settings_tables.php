<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_electronic_invoice_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('provider', 32)->default('factus');
            $table->string('api_url');
            $table->string('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->text('access_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('tested_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique('company_id');
        });

        Schema::create('place_electronic_invoice_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->unsignedBigInteger('numbering_range_id')->nullable();
            $table->string('numbering_range_label')->nullable();
            $table->string('establishment_name')->nullable();
            $table->string('establishment_address')->nullable();
            $table->string('establishment_phone')->nullable();
            $table->string('establishment_email')->nullable();
            $table->string('municipality_code', 5)->nullable();
            $table->timestamps();

            $table->unique('place_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_electronic_invoice_settings');
        Schema::dropIfExists('company_electronic_invoice_settings');
    }
};
