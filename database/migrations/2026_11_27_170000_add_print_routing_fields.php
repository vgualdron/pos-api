<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stations') && ! Schema::hasColumn('stations', 'printer_ip')) {
            Schema::table('stations', function (Blueprint $table) {
                $table->string('printer_ip', 45)->nullable()->after('status');
            });
        }

        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'kitchen_station_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->foreignId('kitchen_station_id')
                    ->nullable()
                    ->after('product_category_id')
                    ->constrained('stations')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('dining_zones') && ! Schema::hasColumn('dining_zones', 'invoice_printer_ip')) {
            Schema::table('dining_zones', function (Blueprint $table) {
                $table->string('invoice_printer_ip', 45)->nullable()->after('sort_order');
            });
        }

        if (Schema::hasTable('table_orders') && ! Schema::hasColumn('table_orders', 'last_sent_at')) {
            Schema::table('table_orders', function (Blueprint $table) {
                $table->timestamp('last_sent_at')->nullable()->after('opened_at');
            });
        }

        if (Schema::hasTable('table_order_lines')) {
            Schema::table('table_order_lines', function (Blueprint $table) {
                if (! Schema::hasColumn('table_order_lines', 'kitchen_station_id')) {
                    $table->foreignId('kitchen_station_id')
                        ->nullable()
                        ->after('product_presentation_id')
                        ->constrained('stations')
                        ->nullOnDelete();
                }
                if (! Schema::hasColumn('table_order_lines', 'sent_at')) {
                    $table->timestamp('sent_at')->nullable()->after('notes');
                }
                if (! Schema::hasColumn('table_order_lines', 'send_batch_id')) {
                    $table->uuid('send_batch_id')->nullable()->after('sent_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('table_order_lines')) {
            Schema::table('table_order_lines', function (Blueprint $table) {
                if (Schema::hasColumn('table_order_lines', 'kitchen_station_id')) {
                    $table->dropConstrainedForeignId('kitchen_station_id');
                }
                if (Schema::hasColumn('table_order_lines', 'send_batch_id')) {
                    $table->dropColumn('send_batch_id');
                }
                if (Schema::hasColumn('table_order_lines', 'sent_at')) {
                    $table->dropColumn('sent_at');
                }
            });
        }

        if (Schema::hasTable('table_orders') && Schema::hasColumn('table_orders', 'last_sent_at')) {
            Schema::table('table_orders', function (Blueprint $table) {
                $table->dropColumn('last_sent_at');
            });
        }

        if (Schema::hasTable('dining_zones') && Schema::hasColumn('dining_zones', 'invoice_printer_ip')) {
            Schema::table('dining_zones', function (Blueprint $table) {
                $table->dropColumn('invoice_printer_ip');
            });
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'kitchen_station_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropConstrainedForeignId('kitchen_station_id');
            });
        }

        if (Schema::hasTable('stations') && Schema::hasColumn('stations', 'printer_ip')) {
            Schema::table('stations', function (Blueprint $table) {
                $table->dropColumn('printer_ip');
            });
        }
    }
};
