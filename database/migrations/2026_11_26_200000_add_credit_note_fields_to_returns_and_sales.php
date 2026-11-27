<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sale_returns')) {
            Schema::table('sale_returns', function (Blueprint $table) {
                if (! Schema::hasColumn('sale_returns', 'nc_reference_code')) {
                    $table->string('nc_reference_code')->nullable()->after('dian_status');
                }
                if (! Schema::hasColumn('sale_returns', 'nc_number')) {
                    $table->string('nc_number')->nullable()->after('nc_reference_code');
                }
                if (! Schema::hasColumn('sale_returns', 'nc_cude')) {
                    $table->string('nc_cude', 128)->nullable()->after('nc_number');
                }
                if (! Schema::hasColumn('sale_returns', 'nc_qr_url')) {
                    $table->text('nc_qr_url')->nullable()->after('nc_cude');
                }
                if (! Schema::hasColumn('sale_returns', 'nc_public_url')) {
                    $table->text('nc_public_url')->nullable()->after('nc_qr_url');
                }
                if (! Schema::hasColumn('sale_returns', 'nc_error')) {
                    $table->text('nc_error')->nullable()->after('nc_public_url');
                }
                if (! Schema::hasColumn('sale_returns', 'nc_emitted_at')) {
                    $table->timestamp('nc_emitted_at')->nullable()->after('nc_error');
                }
            });
        }

        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                if (! Schema::hasColumn('sales', 'nc_status')) {
                    $table->string('nc_status', 32)->nullable()->after('fe_emitted_at');
                }
                if (! Schema::hasColumn('sales', 'nc_reference_code')) {
                    $table->string('nc_reference_code')->nullable()->after('nc_status');
                }
                if (! Schema::hasColumn('sales', 'nc_number')) {
                    $table->string('nc_number')->nullable()->after('nc_reference_code');
                }
                if (! Schema::hasColumn('sales', 'nc_cude')) {
                    $table->string('nc_cude', 128)->nullable()->after('nc_number');
                }
                if (! Schema::hasColumn('sales', 'nc_qr_url')) {
                    $table->text('nc_qr_url')->nullable()->after('nc_cude');
                }
                if (! Schema::hasColumn('sales', 'nc_public_url')) {
                    $table->text('nc_public_url')->nullable()->after('nc_qr_url');
                }
                if (! Schema::hasColumn('sales', 'nc_error')) {
                    $table->text('nc_error')->nullable()->after('nc_public_url');
                }
                if (! Schema::hasColumn('sales', 'nc_emitted_at')) {
                    $table->timestamp('nc_emitted_at')->nullable()->after('nc_error');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sale_returns')) {
            Schema::table('sale_returns', function (Blueprint $table) {
                $cols = [
                    'nc_reference_code',
                    'nc_number',
                    'nc_cude',
                    'nc_qr_url',
                    'nc_public_url',
                    'nc_error',
                    'nc_emitted_at',
                ];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('sale_returns', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                $cols = [
                    'nc_status',
                    'nc_reference_code',
                    'nc_number',
                    'nc_cude',
                    'nc_qr_url',
                    'nc_public_url',
                    'nc_error',
                    'nc_emitted_at',
                ];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('sales', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
