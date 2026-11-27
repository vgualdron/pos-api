<?php

namespace App\Services;

use App\Models\DiningTable;
use App\Models\DiningZone;
use Carbon\Carbon;
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

        $operationalStatus = $this->normalizeOperationalStatus($data['operational_status'] ?? null);
        $now = Carbon::now();

        $table = DiningTable::create([
            'place_id' => $placeId,
            'code' => $code,
            'name' => $name,
            'seats' => isset($data['seats']) && $data['seats'] !== '' && $data['seats'] !== null
                ? (int) $data['seats']
                : null,
            'dining_zone_id' => $zoneId,
            'operational_status' => $operationalStatus,
            'occupied_at' => $operationalStatus === DiningTable::OP_FREE ? null : $now,
            'order_taken_at' => null,
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
            $status = $this->normalizeOperationalStatus($data['operational_status']);
            $data['operational_status'] = $status;
            $data = array_merge($data, $this->timestampsForStatus($table, $status));
        } else {
            unset($data['occupied_at'], $data['order_taken_at']);
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
        $status = $this->normalizeOperationalStatus($status);
        $table->update(array_merge(
            ['operational_status' => $status],
            $this->timestampsForStatus($table, $status)
        ));

        return $table->fresh('diningZone');
    }

    public function markOrderTaken(int $id): DiningTable
    {
        $table = $this->find($id);
        if (($table->operational_status ?? DiningTable::OP_FREE) === DiningTable::OP_FREE) {
            throw new Exception('La mesa está libre. Márquela ocupada antes de tomar el pedido.');
        }

        $now = Carbon::now();
        $table->update([
            'order_taken_at' => $now,
            'occupied_at' => $table->occupied_at ?: $now,
        ]);

        return $table->fresh('diningZone');
    }

    protected function timestampsForStatus(DiningTable $table, string $status): array
    {
        if ($status === DiningTable::OP_FREE) {
            return [
                'occupied_at' => null,
                'order_taken_at' => null,
            ];
        }

        return [
            'occupied_at' => $table->occupied_at ?: Carbon::now(),
        ];
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
