<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('document_type', 32)->default('pos_ticket')->after('status');
            $table->string('fe_status', 32)->nullable()->after('document_type');
            $table->string('fe_reference_code')->nullable()->after('fe_status');
            $table->string('fe_number')->nullable()->after('fe_reference_code');
            $table->string('fe_cufe', 128)->nullable()->after('fe_number');
            $table->text('fe_qr_url')->nullable()->after('fe_cufe');
            $table->text('fe_public_url')->nullable()->after('fe_qr_url');
            $table->text('fe_error')->nullable()->after('fe_public_url');
            $table->timestamp('fe_emitted_at')->nullable()->after('fe_error');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn([
                'document_type',
                'fe_status',
                'fe_reference_code',
                'fe_number',
                'fe_cufe',
                'fe_qr_url',
                'fe_public_url',
                'fe_error',
                'fe_emitted_at',
            ]);
        });
    }
};
