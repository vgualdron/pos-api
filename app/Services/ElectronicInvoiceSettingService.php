<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanyElectronicInvoiceSetting;
use App\Models\Place;
use App\Models\PlaceElectronicInvoiceSetting;
use App\Services\Factus\FactusClient;
use Exception;
use Illuminate\Support\Facades\DB;

class ElectronicInvoiceSettingService
{
    public function __construct(protected FactusClient $factus)
    {
    }

    public function getForCompany(int $companyId): array
    {
        $company = Company::query()->with(['places' => function ($q) {
            $q->orderBy('name')->with('electronicInvoiceSetting');
        }, 'electronicInvoiceSetting'])->find($companyId);

        if (! $company) {
            throw new Exception('Empresa no encontrada.');
        }

        return $this->serialize($company);
    }

    public function upsert(int $companyId, array $companyData, array $placesData = []): array
    {
        return DB::transaction(function () use ($companyId, $companyData, $placesData) {
            $company = Company::query()->find($companyId);
            if (! $company) {
                throw new Exception('Empresa no encontrada.');
            }

            $settings = CompanyElectronicInvoiceSetting::query()->firstOrNew(['company_id' => $companyId]);
            $settings->provider = $companyData['provider'] ?? $settings->provider ?? 'factus';
            $settings->api_url = rtrim((string) ($companyData['api_url'] ?? $settings->api_url ?? ''), '/');
            $settings->client_id = $companyData['client_id'] ?? $settings->client_id;
            $settings->username = $companyData['username'] ?? $settings->username;
            $settings->is_enabled = (bool) ($companyData['is_enabled'] ?? false);

            if (array_key_exists('client_secret', $companyData) && filled($companyData['client_secret'])) {
                $settings->client_secret = $companyData['client_secret'];
                $settings->access_token = null;
                $settings->token_expires_at = null;
            }

            if (array_key_exists('password', $companyData) && filled($companyData['password'])) {
                $settings->password = $companyData['password'];
                $settings->access_token = null;
                $settings->token_expires_at = null;
            }

            if (! filled($settings->api_url)) {
                throw new Exception('La URL de la API es obligatoria.');
            }

            $settings->save();

            $placeIds = Place::query()->where('company_id', $companyId)->pluck('id')->all();
            $allowed = array_flip($placeIds);

            foreach ($placesData as $row) {
                $placeId = (int) ($row['place_id'] ?? 0);
                if (! isset($allowed[$placeId])) {
                    throw new Exception("La sede {$placeId} no pertenece a esta empresa.");
                }

                $placeSettings = PlaceElectronicInvoiceSetting::query()->firstOrNew(['place_id' => $placeId]);
                $placeSettings->is_enabled = (bool) ($row['is_enabled'] ?? false);
                $placeSettings->numbering_range_id = isset($row['numbering_range_id']) && $row['numbering_range_id'] !== ''
                    ? (int) $row['numbering_range_id']
                    : null;
                $placeSettings->numbering_range_label = $row['numbering_range_label'] ?? null;
                $placeSettings->establishment_name = $row['establishment_name'] ?? null;
                $placeSettings->establishment_address = $row['establishment_address'] ?? null;
                $placeSettings->establishment_phone = $row['establishment_phone'] ?? null;
                $placeSettings->establishment_email = $row['establishment_email'] ?? null;
                $placeSettings->municipality_code = $row['municipality_code'] ?? null;
                $placeSettings->save();
            }

            return $this->getForCompany($companyId);
        });
    }

    public function testConnection(int $companyId): array
    {
        $settings = CompanyElectronicInvoiceSetting::query()->where('company_id', $companyId)->first();
        if (! $settings) {
            throw new Exception('Guarde primero la configuración de Factus de la empresa.');
        }

        try {
            $this->factus->authenticate($settings, true);
            $ranges = $this->factus->getNumberingRanges($settings);

            $settings->tested_at = now();
            $settings->last_error = null;
            $settings->save();

            return [
                'ok' => true,
                'tested_at' => optional($settings->tested_at)->toIso8601String(),
                'numbering_ranges' => $this->mapRanges($ranges),
            ];
        } catch (Exception $e) {
            $settings->last_error = $e->getMessage();
            $settings->tested_at = now();
            $settings->access_token = null;
            $settings->token_expires_at = null;
            $settings->save();

            throw $e;
        }
    }

    public function numberingRanges(int $companyId): array
    {
        $settings = CompanyElectronicInvoiceSetting::query()->where('company_id', $companyId)->first();
        if (! $settings) {
            throw new Exception('Guarde primero la configuración de Factus de la empresa.');
        }

        $ranges = $this->factus->getNumberingRanges($settings);

        return $this->mapRanges($ranges);
    }

    protected function serialize(Company $company): array
    {
        $settings = $company->electronicInvoiceSetting;

        return [
            'company_id' => $company->id,
            'company_name' => $company->name,
            'company' => [
                'provider' => $settings?->provider ?? 'factus',
                'api_url' => $settings?->api_url ?? 'https://api-sandbox.factus.com.co',
                'client_id' => $settings?->client_id,
                'username' => $settings?->username,
                'is_enabled' => (bool) ($settings?->is_enabled ?? false),
                'has_client_secret' => $settings ? $settings->hasClientSecret() : false,
                'has_password' => $settings ? $settings->hasPassword() : false,
                'tested_at' => optional($settings?->tested_at)->toIso8601String(),
                'last_error' => $settings?->last_error,
            ],
            'places' => $company->places->map(function (Place $place) {
                $s = $place->electronicInvoiceSetting;

                return [
                    'place_id' => $place->id,
                    'place_name' => $place->name,
                    'is_enabled' => (bool) ($s?->is_enabled ?? false),
                    'numbering_range_id' => $s?->numbering_range_id,
                    'numbering_range_label' => $s?->numbering_range_label,
                    'establishment_name' => $s?->establishment_name ?? $place->name,
                    'establishment_address' => $s?->establishment_address ?? $place->address,
                    'establishment_phone' => $s?->establishment_phone ?? $place->phone,
                    'establishment_email' => $s?->establishment_email ?? $place->email,
                    'municipality_code' => $s?->municipality_code,
                ];
            })->values()->all(),
        ];
    }

    protected function mapRanges(array $ranges): array
    {
        return collect($ranges)->map(function ($row) {
            $id = $row['id'] ?? $row['numbering_range_id'] ?? null;
            $prefix = $row['prefix'] ?? '';
            $from = $row['from'] ?? $row['initial_range'] ?? null;
            $to = $row['to'] ?? $row['final_range'] ?? null;
            $resolution = $row['resolution_number'] ?? $row['resolution'] ?? null;
            $parts = array_filter([
                $prefix ? "Prefijo {$prefix}" : null,
                $id !== null ? "#{$id}" : null,
                $resolution ? "Res. {$resolution}" : null,
                ($from !== null && $to !== null) ? "{$from}-{$to}" : null,
            ]);

            return [
                'id' => $id,
                'label' => implode(' · ', $parts) ?: (string) $id,
                'raw' => $row,
            ];
        })->filter(fn ($row) => $row['id'] !== null)->values()->all();
    }
}
