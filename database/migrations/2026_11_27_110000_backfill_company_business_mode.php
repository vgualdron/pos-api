<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        $companies = DB::table('companies')->select(['id', 'configuration'])->get();

        foreach ($companies as $company) {
            $configuration = $this->decodeConfiguration($company->configuration);

            if (! array_key_exists('business_mode', $configuration) || $configuration['business_mode'] === null || $configuration['business_mode'] === '') {
                $configuration['business_mode'] = 'retail';
            } else {
                $mode = strtolower(trim((string) $configuration['business_mode']));
                $configuration['business_mode'] = in_array($mode, ['retail', 'restaurant'], true)
                    ? $mode
                    : 'retail';
            }

            DB::table('companies')
                ->where('id', $company->id)
                ->update([
                    'configuration' => json_encode($configuration, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        $companies = DB::table('companies')->select(['id', 'configuration'])->get();

        foreach ($companies as $company) {
            $configuration = $this->decodeConfiguration($company->configuration);
            unset($configuration['business_mode']);

            DB::table('companies')
                ->where('id', $company->id)
                ->update([
                    'configuration' => empty($configuration)
                        ? null
                        : json_encode($configuration, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
        }
    }

    protected function decodeConfiguration(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
            if (str_starts_with(trim($raw), '#')) {
                return ['template_color' => trim($raw)];
            }
        }

        return [];
    }
};
