<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('taxes')) {
            Schema::create('taxes', function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->unique();
                $table->string('name', 80);
                $table->string('short_name', 40)->nullable();
                $table->decimal('rate', 5, 2)->default(0);
                $table->string('kind', 20)->default('iva'); // iva | ico | other
                $table->boolean('is_inclusive')->default(true);
                $table->string('status', 20)->default('activo');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        $now = now();
        $defaults = [
            ['code' => 'IVA_0', 'name' => 'IVA 0% (exento)', 'short_name' => 'IVA 0%', 'rate' => 0, 'kind' => 'iva', 'sort_order' => 10],
            ['code' => 'IVA_5', 'name' => 'IVA 5%', 'short_name' => 'IVA 5%', 'rate' => 5, 'kind' => 'iva', 'sort_order' => 20],
            ['code' => 'IVA_19', 'name' => 'IVA 19%', 'short_name' => 'IVA 19%', 'rate' => 19, 'kind' => 'iva', 'sort_order' => 30],
            ['code' => 'ICO_8', 'name' => 'ICO / INC 8%', 'short_name' => 'ICO 8%', 'rate' => 8, 'kind' => 'ico', 'sort_order' => 40],
        ];

        foreach ($defaults as $row) {
            $exists = DB::table('taxes')->where('code', $row['code'])->exists();
            if ($exists) {
                continue;
            }
            DB::table('taxes')->insert(array_merge($row, [
                'is_inclusive' => true,
                'status' => 'activo',
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        $taxIdsByRate = DB::table('taxes')
            ->where('status', 'activo')
            ->get(['id', 'rate', 'code'])
            ->keyBy(fn ($row) => (string) round((float) $row->rate, 2));

        $defaultTaxId = (int) (DB::table('taxes')->where('code', 'IVA_19')->value('id')
            ?: DB::table('taxes')->orderBy('id')->value('id'));

        Schema::table('product_presentations', function (Blueprint $table) use ($defaultTaxId) {
            if (!Schema::hasColumn('product_presentations', 'tax_id')) {
                $table->foreignId('tax_id')
                    ->nullable()
                    ->after('sale_price')
                    ->constrained('taxes')
                    ->nullOnDelete();
            }
        });

        if (Schema::hasColumn('product_presentations', 'tax_rate')) {
            $presentations = DB::table('product_presentations')->select('id', 'tax_rate', 'tax_id')->get();
            foreach ($presentations as $row) {
                if (!empty($row->tax_id)) {
                    continue;
                }
                $key = (string) round((float) ($row->tax_rate ?? 19), 2);
                $taxId = (int) (($taxIdsByRate[$key]->id ?? null) ?: $defaultTaxId);
                $rate = (float) (($taxIdsByRate[$key]->rate ?? null) ?? 19);
                DB::table('product_presentations')->where('id', $row->id)->update([
                    'tax_id' => $taxId,
                    'tax_rate' => $rate,
                ]);
            }
        } else {
            DB::table('product_presentations')->whereNull('tax_id')->update(['tax_id' => $defaultTaxId]);
        }

        Schema::table('sale_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_lines', 'tax_id')) {
                $table->foreignId('tax_id')
                    ->nullable()
                    ->after('unit_price')
                    ->constrained('taxes')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('sale_lines', 'tax_name')) {
                $table->string('tax_name', 80)->nullable()->after('tax_rate');
            }
            if (!Schema::hasColumn('sale_lines', 'tax_code')) {
                $table->string('tax_code', 40)->nullable()->after('tax_name');
            }
        });

        if (Schema::hasColumn('sale_lines', 'tax_rate')) {
            $lines = DB::table('sale_lines')->select('id', 'tax_rate', 'tax_id')->get();
            foreach ($lines as $row) {
                if (!empty($row->tax_id)) {
                    continue;
                }
                $key = (string) round((float) ($row->tax_rate ?? 0), 2);
                $tax = $taxIdsByRate[$key] ?? null;
                DB::table('sale_lines')->where('id', $row->id)->update([
                    'tax_id' => $tax?->id,
                    'tax_name' => $tax ? DB::table('taxes')->where('id', $tax->id)->value('short_name') : null,
                    'tax_code' => $tax?->code,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('sale_lines', function (Blueprint $table) {
            if (Schema::hasColumn('sale_lines', 'tax_id')) {
                $table->dropConstrainedForeignId('tax_id');
            }
            foreach (['tax_code', 'tax_name'] as $column) {
                if (Schema::hasColumn('sale_lines', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('product_presentations', function (Blueprint $table) {
            if (Schema::hasColumn('product_presentations', 'tax_id')) {
                $table->dropConstrainedForeignId('tax_id');
            }
        });

        Schema::dropIfExists('taxes');
    }
};
