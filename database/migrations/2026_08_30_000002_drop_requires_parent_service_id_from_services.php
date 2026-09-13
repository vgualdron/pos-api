<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('services', 'requires_parent_service_id')) {
            return;
        }

        Schema::table('services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requires_parent_service_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('services', 'requires_parent_service_id')) {
            return;
        }

        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('requires_parent_service_id')->nullable()->constrained('services')->nullOnDelete();
        });
    }
};
