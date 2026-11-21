<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Place;
use App\Models\PlacePosResolutionSetting;
use Exception;
use Illuminate\Support\Facades\DB;

class PosResolutionSettingService
{
    public function getForCompany(int $companyId): array
    {
        $company = Company::query()->with(['places' => function ($q) {
            $q->orderBy('name')->with('posResolutionSetting');
        }])->find($companyId);

        if (! $company) {
            throw new Exception('Empresa no encontrada.');
        }

        return $this->serialize($company);
    }

    public function upsert(int $companyId, array $placesData = []): array
    {
        return DB::transaction(function () use ($companyId, $placesData) {
            $company = Company::query()->find($companyId);
            if (! $company) {
                throw new Exception('Empresa no encontrada.');
            }

            $placeIds = Place::query()->where('company_id', $companyId)->pluck('id')->all();
            $allowed = array_flip($placeIds);

            foreach ($placesData as $row) {
                $placeId = (int) ($row['place_id'] ?? 0);
                if (! isset($allowed[$placeId])) {
                    throw new Exception("La sede {$placeId} no pertenece a esta empresa.");
                }

                $settings = PlacePosResolutionSetting::query()->firstOrNew(['place_id' => $placeId]);
                $enabled = (bool) ($row['is_enabled'] ?? false);

                $resolutionNumber = trim((string) ($row['resolution_number'] ?? ''));
                $prefix = trim((string) ($row['prefix'] ?? ''));
                $rangeFrom = $this->nullableInt($row['range_from'] ?? null);
                $rangeTo = $this->nullableInt($row['range_to'] ?? null);
                $current = $this->nullableInt($row['current_number'] ?? null);

                if ($enabled) {
                    if ($resolutionNumber === '') {
                        throw new Exception('El número de resolución es obligatorio cuando la resolución POS está activa.');
                    }
                    if ($prefix === '') {
                        throw new Exception('El prefijo es obligatorio cuando la resolución POS está activa.');
                    }
                    if ($rangeFrom === null || $rangeTo === null) {
                        throw new Exception('El rango desde/hasta es obligatorio cuando la resolución POS está activa.');
                    }
                    if ($rangeFrom < 1 || $rangeTo < $rangeFrom) {
                        throw new Exception('El rango de numeración POS es inválido.');
                    }
                    if ($current !== null && ($current < ($rangeFrom - 1) || $current > $rangeTo)) {
                        throw new Exception('El consecutivo actual está fuera del rango autorizado.');
                    }
                }

                $settings->is_enabled = $enabled;
                $settings->resolution_number = $resolutionNumber !== '' ? $resolutionNumber : null;
                $settings->resolution_date = $this->nullableDate($row['resolution_date'] ?? null);
                $settings->valid_from = $this->nullableDate($row['valid_from'] ?? null);
                $settings->valid_to = $this->nullableDate($row['valid_to'] ?? null);
                $settings->prefix = $prefix !== '' ? $prefix : null;
                $settings->range_from = $rangeFrom;
                $settings->range_to = $rangeTo;
                $settings->current_number = $current;
                $settings->save();
            }

            return $this->getForCompany($companyId);
        });
    }

    /**
     * Allocate next POS consecutive inside an open DB transaction.
     *
     * @return array{pos_number:string,pos_prefix:string,pos_consecutive:int,pos_resolution_number:string,pos_resolution_date:?string,pos_range_from:int,pos_range_to:int}|null
     */
    public function allocateForPlace(int $placeId): ?array
    {
        $settings = PlacePosResolutionSetting::query()
            ->where('place_id', $placeId)
            ->lockForUpdate()
            ->first();

        if (! $settings || ! $settings->is_enabled) {
            return null;
        }

        $resolutionNumber = trim((string) ($settings->resolution_number ?? ''));
        $prefix = trim((string) ($settings->prefix ?? ''));
        $rangeFrom = (int) ($settings->range_from ?? 0);
        $rangeTo = (int) ($settings->range_to ?? 0);

        if ($resolutionNumber === '' || $prefix === '' || $rangeFrom < 1 || $rangeTo < $rangeFrom) {
            throw new Exception('La resolución POS de la sede está incompleta. Revise la configuración.');
        }

        $today = now()->startOfDay();
        if ($settings->valid_from && $today->lt($settings->valid_from->copy()->startOfDay())) {
            throw new Exception('La resolución POS aún no está vigente.');
        }
        if ($settings->valid_to && $today->gt($settings->valid_to->copy()->endOfDay())) {
            throw new Exception('La resolución POS está vencida.');
        }

        $last = (int) ($settings->current_number ?? 0);
        $next = $last > 0 ? $last + 1 : $rangeFrom;
        if ($next < $rangeFrom) {
            $next = $rangeFrom;
        }
        if ($next > $rangeTo) {
            throw new Exception('Se agotó el rango de la resolución POS. Configure un nuevo rango.');
        }

        $settings->current_number = $next;
        $settings->save();

        return [
            'pos_number' => $prefix . $next,
            'pos_prefix' => $prefix,
            'pos_consecutive' => $next,
            'pos_resolution_number' => $resolutionNumber,
            'pos_resolution_date' => $settings->resolution_date?->format('Y-m-d'),
            'pos_range_from' => $rangeFrom,
            'pos_range_to' => $rangeTo,
        ];
    }

    protected function serialize(Company $company): array
    {
        $places = [];
        foreach ($company->places as $place) {
            $s = $place->posResolutionSetting;
            $places[] = [
                'place_id' => $place->id,
                'place_name' => $place->name,
                'is_enabled' => (bool) ($s?->is_enabled),
                'resolution_number' => $s?->resolution_number,
                'resolution_date' => $s?->resolution_date?->format('Y-m-d'),
                'valid_from' => $s?->valid_from?->format('Y-m-d'),
                'valid_to' => $s?->valid_to?->format('Y-m-d'),
                'prefix' => $s?->prefix,
                'range_from' => $s?->range_from,
                'range_to' => $s?->range_to,
                'current_number' => $s?->current_number,
            ];
        }

        return [
            'company_id' => $company->id,
            'company_name' => $company->name,
            'places' => $places,
        ];
    }

    protected function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    protected function nullableDate($value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
