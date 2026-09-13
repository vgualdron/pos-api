<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->string('print_api_url')->nullable()->after('email');
        });

        Schema::table('stations', function (Blueprint $table) {
            $table->string('printer_ip')->nullable()->after('can_call_tickets');
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->dropColumn('print_api_url');
        });

        Schema::table('stations', function (Blueprint $table) {
            $table->dropColumn('printer_ip');
        });
    }
};
