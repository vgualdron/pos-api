<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('turns');
        Schema::dropIfExists('station_services');
        Schema::dropIfExists('service_follow_ups');
        Schema::dropIfExists('services');
        Schema::dropIfExists('turn_priority_preloads');
        Schema::dropIfExists('display_alerts');

        if (Schema::hasTable('stations')) {
            Schema::table('stations', function (Blueprint $table) {
                $columns = [
                    'can_generate_tickets',
                    'can_call_tickets',
                    'max_open_turns',
                    'show_priority_option',
                    'issues_super_priority',
                    'printer_ip',
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn('stations', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });

            // Keep only register stations for POS; remove legacy turn station types.
            DB::table('stations')->where('type', '!=', 'register')->delete();
        }

        if (Schema::hasTable('places') && Schema::hasColumn('places', 'print_api_url')) {
            Schema::table('places', function (Blueprint $table) {
                $table->dropColumn('print_api_url');
            });
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Irreversible cleanup of turn/queue domain.
    }
};
