<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 2)->unique()->comment('DIVIPOLA department code');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('municipalities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->string('code', 5)->unique()->comment('DIVIPOLA municipality code');
            $table->string('name');
            $table->timestamps();
            $table->index(['department_id', 'name']);
        });

        $path = database_path('data/dane_divipola.json');
        $rows = file_exists($path)
            ? (json_decode(file_get_contents($path), true) ?: [])
            : [];
        $now = now();

        $departments = collect($rows)
            ->unique('department_code')
            ->sortBy('department_code')
            ->map(fn (array $row) => [
                'code' => $row['department_code'],
                'name' => $row['department'],
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->all();

        if (count($departments) > 0) {
            DB::table('departments')->insert($departments);
        }

        $departmentIds = DB::table('departments')->pluck('id', 'code')->all();

        collect($rows)
            ->sortBy('code')
            ->map(fn (array $row) => [
                'department_id' => $departmentIds[$row['department_code']],
                'code' => $row['code'],
                'name' => $row['name'],
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->chunk(300)
            ->each(fn ($chunk) => DB::table('municipalities')->insert($chunk->values()->all()));
    }

    public function down(): void
    {
        Schema::dropIfExists('municipalities');
        Schema::dropIfExists('departments');
    }
};
