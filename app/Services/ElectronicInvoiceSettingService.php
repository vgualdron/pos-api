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

            // Never wipe existing company credentials with empty payload fields
            // (e.g. saving places/ranges while secret inputs are blank by design).
            if (filled($companyData['api_url'] ?? null)) {
                $settings->api_url = rtrim(trim((string) $companyData['api_url']), '/');
            }
            if (filled($companyData['client_id'] ?? null)) {
                $settings->client_id = FactusClient::cleanCredential($companyData['client_id']);
            }
            if (filled($companyData['username'] ?? null)) {
                $settings->username = FactusClient::cleanCredential($companyData['username']);
            }
            if (array_key_exists('is_enabled', $companyData)) {
                $settings->is_enabled = (bool) $companyData['is_enabled'];
            }

            $secretsTouched = false;
            if (filled($companyData['client_secret'] ?? null)) {
                $settings->client_secret = FactusClient::cleanCredential($companyData['client_secret']);
                $secretsTouched = true;
            }

            if (filled($companyData['password'] ?? null)) {
                $settings->password = (string) $companyData['password'];
                $secretsTouched = true;
            }

            if ($secretsTouched) {
                $settings->access_token = null;
                $settings->token_expires_at = null;
            }

            if (! filled($settings->api_url)) {
                throw new Exception('La URL de la API es obligatoria.');
            }

            // Persist without decrypting existing secret columns during serialization edge-cases
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
                $placeSettings->credit_note_numbering_range_id = isset($row['credit_note_numbering_range_id']) && $row['credit_note_numbering_range_id'] !== ''
                    ? (int) $row['credit_note_numbering_range_id']
                    : null;
                $placeSettings->credit_note_numbering_range_label = $row['credit_note_numbering_range_label'] ?? null;
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
        } catch (Exception $e) {
            $settings->last_error = $e->getMessage();
            $settings->tested_at = now();
            $settings->access_token = null;
            $settings->token_expires_at = null;
            $settings->save();

            throw $e;
        }

        $ranges = [];
        $rangesError = null;
        try {
            $ranges = $this->factus->getNumberingRanges($settings);
        } catch (Exception $e) {
            $rangesError = $e->getMessage();
        }

        $dianRanges = [];
        $dianError = null;
        try {
            $dianRanges = $this->factus->getDianNumberingRanges($settings);
        } catch (Exception $e) {
            $dianError = $e->getMessage();
        }

        $settings->tested_at = now();
        $settings->last_error = count($ranges) === 0
            ? 'Conexión OK, pero no hay rangos de numeración creados en Factus. Debe crear/asociar un rango en Factus y volver a probar.'
            : null;
        $settings->save();

        return [
            'ok' => true,
            'tested_at' => optional($settings->tested_at)->toIso8601String(),
            'numbering_ranges' => $this->mapRanges($ranges),
            'dian_numbering_ranges' => $dianRanges,
            'ranges_error' => $rangesError,
            'dian_error' => $dianError,
            'api_url_used' => $settings->baseUrl(),
            'hint' => count($ranges) === 0
                ? 'Auth OK. En sandbox debes tener al menos un rango creado en Factus (panel o API). Luego vuelve a probar para ver el id y asignarlo a la sede.'
                : null,
        ];
    }


    public function numberingRanges(int $companyId): array
    {
        $settings = $this->requireCompanySettings($companyId);
        $ranges = $this->factus->getNumberingRanges($settings);

        return $this->mapRanges($ranges);
    }

    public function dianNumberingRanges(int $companyId): array
    {
        $settings = $this->requireCompanySettings($companyId);

        return $this->factus->getDianNumberingRanges($settings);
    }

    public function createNumberingRange(int $companyId, array $data): array
    {
        $settings = $this->requireCompanySettings($companyId);
        $created = $this->factus->createNumberingRange($settings, $data);
        $ranges = $this->mapRanges($this->factus->getNumberingRanges($settings));

        return [
            'created' => $created,
            'numbering_ranges' => $ranges,
        ];
    }

    public function updateNumberingRangeCurrent(int $companyId, int $rangeId, $current): array
    {
        $settings = $this->requireCompanySettings($companyId);
        $updated = $this->factus->updateNumberingRangeCurrent($settings, $rangeId, $current);

        return [
            'updated' => $updated,
            'numbering_ranges' => $this->mapRanges($this->factus->getNumberingRanges($settings)),
        ];
    }

    public function toggleNumberingRangeStatus(int $companyId, int $rangeId): array
    {
        $settings = $this->requireCompanySettings($companyId);
        $updated = $this->factus->toggleNumberingRangeStatus($settings, $rangeId);

        return [
            'updated' => $updated,
            'numbering_ranges' => $this->mapRanges($this->factus->getNumberingRanges($settings)),
        ];
    }

    protected function requireCompanySettings(int $companyId): CompanyElectronicInvoiceSetting
    {
        $settings = CompanyElectronicInvoiceSetting::query()->where('company_id', $companyId)->first();
        if (! $settings) {
            throw new Exception('Guarde primero la configuración de Factus de la empresa.');
        }

        return $settings;
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
                    'credit_note_numbering_range_id' => $s?->credit_note_numbering_range_id,
                    'credit_note_numbering_range_label' => $s?->credit_note_numbering_range_label,
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
        $documentLabels = [
            '21' => 'Factura',
            '22' => 'Nota crédito',
            '23' => 'Nota débito',
            '24' => 'Doc. soporte',
            '25' => 'Nota ajuste DS',
        ];

        return collect($ranges)->map(function ($row) use ($documentLabels) {
            $id = $row['id'] ?? $row['numbering_range_id'] ?? null;
            $prefix = trim((string) ($row['prefix'] ?? ''));
            $from = $row['from'] ?? $row['initial_range'] ?? null;
            $to = $row['to'] ?? $row['final_range'] ?? null;
            $resolution = $row['resolution_number'] ?? $row['resolution'] ?? null;
            $document = (string) ($row['document'] ?? $row['document_code'] ?? '');
            // Factus sometimes returns document name instead of code
            if ($document !== '' && ! isset($documentLabels[$document])) {
                $docName = mb_strtolower($document);
                if (str_contains($docName, 'crédito') || str_contains($docName, 'credito')) {
                    $document = '22';
                } elseif (str_contains($docName, 'débito') || str_contains($docName, 'debito')) {
                    $document = '23';
                } elseif (str_contains($docName, 'soporte')) {
                    $document = '24';
                } elseif (str_contains($docName, 'factura')) {
                    $document = '21';
                }
            }
            $docLabel = $documentLabels[$document] ?? ($row['document_name'] ?? $row['document'] ?? 'Documento');
            $parts = array_filter([
                $docLabel,
                $prefix !== '' ? "Prefijo {$prefix}" : null,
                $id !== null ? "#{$id}" : null,
                $resolution ? "Res. {$resolution}" : null,
                ($from !== null && $to !== null) ? "{$from}-{$to}" : null,
            ]);

            return [
                'id' => $id,
                'document' => $document,
                'document_label' => $docLabel,
                'prefix' => $prefix,
                'is_active' => (bool) ($row['is_active'] ?? true),
                'current' => $row['current'] ?? null,
                'label' => implode(' · ', $parts) ?: (string) $id,
                'raw' => $row,
            ];
        })->filter(fn ($row) => $row['id'] !== null)->values()->all();
    }
}
