<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dining_zones')) {
            Schema::create('dining_zones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();
                $table->string('name', 80);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->string('status', 20)->default('activo');
                $table->timestamps();

                $table->unique(['place_id', 'name']);
                $table->index(['place_id', 'status', 'sort_order']);
            });
        }

        if (Schema::hasTable('dining_tables') && ! Schema::hasColumn('dining_tables', 'dining_zone_id')) {
            Schema::table('dining_tables', function (Blueprint $table) {
                $table->foreignId('dining_zone_id')
                    ->nullable()
                    ->after('seats')
                    ->constrained('dining_zones')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('dining_tables') && Schema::hasColumn('dining_tables', 'zone')) {
            $rows = DB::table('dining_tables')
                ->select(['id', 'place_id', 'zone'])
                ->whereNotNull('zone')
                ->where('zone', '!=', '')
                ->get();

            $zoneIdsByPlaceName = [];
            foreach ($rows as $row) {
                $placeId = (int) $row->place_id;
                $name = trim((string) $row->zone);
                if ($name === '') {
                    continue;
                }

                $key = $placeId.'|'.mb_strtolower($name);
                if (! isset($zoneIdsByPlaceName[$key])) {
                    $existing = DB::table('dining_zones')
                        ->where('place_id', $placeId)
                        ->where('name', $name)
                        ->first();

                    if ($existing) {
                        $zoneIdsByPlaceName[$key] = (int) $existing->id;
                    } else {
                        $zoneIdsByPlaceName[$key] = (int) DB::table('dining_zones')->insertGetId([
                            'place_id' => $placeId,
                            'name' => $name,
                            'sort_order' => 0,
                            'status' => 'activo',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                DB::table('dining_tables')
                    ->where('id', $row->id)
                    ->update(['dining_zone_id' => $zoneIdsByPlaceName[$key]]);
            }

            Schema::table('dining_tables', function (Blueprint $table) {
                $table->dropColumn('zone');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dining_tables') && Schema::hasColumn('dining_tables', 'dining_zone_id')) {
            if (! Schema::hasColumn('dining_tables', 'zone')) {
                Schema::table('dining_tables', function (Blueprint $table) {
                    $table->string('zone', 80)->nullable()->after('seats');
                });
            }

            $tables = DB::table('dining_tables as t')
                ->leftJoin('dining_zones as z', 'z.id', '=', 't.dining_zone_id')
                ->select(['t.id', 'z.name'])
                ->get();

            foreach ($tables as $row) {
                DB::table('dining_tables')
                    ->where('id', $row->id)
                    ->update(['zone' => $row->name]);
            }

            Schema::table('dining_tables', function (Blueprint $table) {
                $table->dropConstrainedForeignId('dining_zone_id');
            });
        }

        Schema::dropIfExists('dining_zones');
    }
};
