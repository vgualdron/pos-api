<?php

namespace App\Services;

use App\Models\DiningZone;
use Exception;

class DiningZoneService
{
    public function byPlace(int $placeId, bool $onlyActive = false)
    {
        $query = DiningZone::query()
            ->where('place_id', $placeId)
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($onlyActive) {
            $query->where('status', DiningZone::STATUS_ACTIVE);
        }

        return $query->get();
    }

    public function find(int $id): DiningZone
    {
        return DiningZone::findOrFail($id);
    }

    public function create(array $data): DiningZone
    {
        $placeId = (int) $data['place_id'];
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new Exception('El nombre de la zona es obligatorio.');
        }

        if (DiningZone::where('place_id', $placeId)->where('name', $name)->exists()) {
            throw new Exception('Ya existe una zona con ese nombre en la sede.');
        }

        return DiningZone::create([
            'place_id' => $placeId,
            'name' => $name,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'status' => ($data['status'] ?? DiningZone::STATUS_ACTIVE) === DiningZone::STATUS_INACTIVE
                ? DiningZone::STATUS_INACTIVE
                : DiningZone::STATUS_ACTIVE,
        ]);
    }

    public function update(int $id, array $data): DiningZone
    {
        $zone = $this->find($id);

        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                throw new Exception('El nombre de la zona es obligatorio.');
            }
            $exists = DiningZone::where('place_id', $zone->place_id)
                ->where('name', $name)
                ->where('id', '!=', $zone->id)
                ->exists();
            if ($exists) {
                throw new Exception('Ya existe una zona con ese nombre en la sede.');
            }
            $data['name'] = $name;
        }

        if (array_key_exists('status', $data)) {
            $data['status'] = $data['status'] === DiningZone::STATUS_INACTIVE
                ? DiningZone::STATUS_INACTIVE
                : DiningZone::STATUS_ACTIVE;
        }

        if (array_key_exists('sort_order', $data)) {
            $data['sort_order'] = (int) $data['sort_order'];
        }

        $zone->update($data);

        return $zone->fresh();
    }

    public function delete(int $id): void
    {
        $zone = $this->find($id);
        if ($zone->diningTables()->exists()) {
            throw new Exception('No se puede eliminar una zona con mesas. Reasigne las mesas o márquela inactiva.');
        }
        $zone->delete();
    }
}
