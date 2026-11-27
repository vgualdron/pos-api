<?php

namespace App\Services;

use App\Models\DiningTable;
use App\Models\DiningZone;
use Exception;

class DiningTableService
{
    public function byPlace(int $placeId, bool $onlyActive = false)
    {
        $query = DiningTable::query()
            ->where('dining_tables.place_id', $placeId)
            ->with('diningZone')
            ->leftJoin('dining_zones', 'dining_zones.id', '=', 'dining_tables.dining_zone_id')
            ->orderBy('dining_zones.sort_order')
            ->orderBy('dining_zones.name')
            ->orderBy('dining_tables.sort_order')
            ->orderBy('dining_tables.code')
            ->select('dining_tables.*');

        if ($onlyActive) {
            $query->where('dining_tables.status', DiningTable::STATUS_ACTIVE);
        }

        return $query->get();
    }

    public function find(int $id): DiningTable
    {
        return DiningTable::with('diningZone')->findOrFail($id);
    }

    public function create(array $data): DiningTable
    {
        $placeId = (int) $data['place_id'];
        $code = trim((string) ($data['code'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));

        if ($code === '') {
            throw new Exception('El código de la mesa es obligatorio.');
        }
        if ($name === '') {
            throw new Exception('El nombre de la mesa es obligatorio.');
        }

        if (DiningTable::where('place_id', $placeId)->where('code', $code)->exists()) {
            throw new Exception('Ya existe una mesa con ese código en la sede.');
        }

        $zoneId = $this->resolveZoneId($placeId, $data['dining_zone_id'] ?? null);

        $table = DiningTable::create([
            'place_id' => $placeId,
            'code' => $code,
            'name' => $name,
            'seats' => isset($data['seats']) && $data['seats'] !== '' && $data['seats'] !== null
                ? (int) $data['seats']
                : null,
            'dining_zone_id' => $zoneId,
            'operational_status' => $this->normalizeOperationalStatus($data['operational_status'] ?? null),
            'status' => ($data['status'] ?? DiningTable::STATUS_ACTIVE) === DiningTable::STATUS_INACTIVE
                ? DiningTable::STATUS_INACTIVE
                : DiningTable::STATUS_ACTIVE,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return $table->fresh('diningZone');
    }

    public function update(int $id, array $data): DiningTable
    {
        $table = $this->find($id);

        if (array_key_exists('code', $data)) {
            $code = trim((string) $data['code']);
            if ($code === '') {
                throw new Exception('El código de la mesa es obligatorio.');
            }
            $exists = DiningTable::where('place_id', $table->place_id)
                ->where('code', $code)
                ->where('id', '!=', $table->id)
                ->exists();
            if ($exists) {
                throw new Exception('Ya existe una mesa con ese código en la sede.');
            }
            $data['code'] = $code;
        }

        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                throw new Exception('El nombre de la mesa es obligatorio.');
            }
            $data['name'] = $name;
        }

        if (array_key_exists('dining_zone_id', $data)) {
            $data['dining_zone_id'] = $this->resolveZoneId((int) $table->place_id, $data['dining_zone_id']);
        }

        if (array_key_exists('seats', $data)) {
            $data['seats'] = $data['seats'] === '' || $data['seats'] === null
                ? null
                : (int) $data['seats'];
        }

        if (array_key_exists('operational_status', $data)) {
            $data['operational_status'] = $this->normalizeOperationalStatus($data['operational_status']);
        }

        if (array_key_exists('status', $data)) {
            $data['status'] = $data['status'] === DiningTable::STATUS_INACTIVE
                ? DiningTable::STATUS_INACTIVE
                : DiningTable::STATUS_ACTIVE;
        }

        if (array_key_exists('sort_order', $data)) {
            $data['sort_order'] = (int) $data['sort_order'];
        }

        unset($data['zone']);
        $table->update($data);

        return $table->fresh('diningZone');
    }

    public function updateOperationalStatus(int $id, string $status): DiningTable
    {
        $table = $this->find($id);
        $table->update([
            'operational_status' => $this->normalizeOperationalStatus($status),
        ]);

        return $table->fresh('diningZone');
    }

    public function delete(int $id): void
    {
        $this->find($id)->delete();
    }

    protected function resolveZoneId(int $placeId, mixed $zoneId): ?int
    {
        if ($zoneId === null || $zoneId === '') {
            return null;
        }

        $zone = DiningZone::find((int) $zoneId);
        if (!$zone || (int) $zone->place_id !== $placeId) {
            throw new Exception('La zona no pertenece a esta sede.');
        }

        return (int) $zone->id;
    }

    protected function normalizeOperationalStatus(?string $status): string
    {
        $status = strtolower(trim((string) ($status ?: DiningTable::OP_FREE)));
        if (! in_array($status, DiningTable::OPERATIONAL_STATUSES, true)) {
            throw new Exception('Estado operativo inválido. Use free, occupied o billing.');
        }

        return $status;
    }
}
