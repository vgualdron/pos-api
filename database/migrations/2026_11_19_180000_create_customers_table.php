<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained('places')->restrictOnDelete();

            // DIAN / electronic invoice buyer data
            $table->string('person_type', 20)->default('natural'); // natural | juridica
            $table->string('document_type', 10); // DIAN identification type code
            $table->string('document_number', 40);
            $table->string('verification_digit', 2)->nullable();

            $table->string('legal_name'); // display / razón social
            $table->string('first_name')->nullable();
            $table->string('first_last_name')->nullable();
            $table->string('second_last_name')->nullable();

            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('mobile', 40)->nullable();

            $table->string('address_line')->nullable();
            $table->string('city_code', 10)->nullable(); // DIVIPOLA municipality
            $table->string('city_name')->nullable();
            $table->string('department_code', 10)->nullable();
            $table->string('department_name')->nullable();
            $table->string('country_code', 5)->default('CO');

            $table->string('tax_regime', 40)->nullable(); // responsable_iva | no_responsable_iva | simple
            $table->json('fiscal_responsibilities')->nullable(); // e.g. ["O-13","R-99-PN"]
            $table->string('merchant_registration')->nullable();

            $table->string('status', 20)->default('activo');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['place_id', 'document_type', 'document_number'],
                'customers_place_document_unique'
            );
            $table->index(['place_id', 'status']);
            $table->index(['place_id', 'legal_name']);
        });

        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'customer_id')) {
                $table->foreignId('customer_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('customers')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'customer_id')) {
                $table->dropConstrainedForeignId('customer_id');
            }
        });

        Schema::dropIfExists('customers');
    }
};
