<?php

namespace App\Services;

use App\Models\DiningTable;
use Exception;

class DiningTableService
{
    public function byPlace(int $placeId, bool $onlyActive = false)
    {
        $query = DiningTable::query()
            ->where('place_id', $placeId)
            ->orderBy('zone')
            ->orderBy('sort_order')
            ->orderBy('code');

        if ($onlyActive) {
            $query->where('status', DiningTable::STATUS_ACTIVE);
        }

        return $query->get();
    }

    public function find(int $id): DiningTable
    {
        return DiningTable::findOrFail($id);
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

        return DiningTable::create([
            'place_id' => $placeId,
            'code' => $code,
            'name' => $name,
            'seats' => isset($data['seats']) && $data['seats'] !== '' && $data['seats'] !== null
                ? (int) $data['seats']
                : null,
            'zone' => $this->nullableString($data['zone'] ?? null),
            'operational_status' => $this->normalizeOperationalStatus($data['operational_status'] ?? null),
            'status' => ($data['status'] ?? DiningTable::STATUS_ACTIVE) === DiningTable::STATUS_INACTIVE
                ? DiningTable::STATUS_INACTIVE
                : DiningTable::STATUS_ACTIVE,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);
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

        if (array_key_exists('zone', $data)) {
            $data['zone'] = $this->nullableString($data['zone']);
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

        $table->update($data);

        return $table->fresh();
    }

    public function updateOperationalStatus(int $id, string $status): DiningTable
    {
        $table = $this->find($id);
        $table->update([
            'operational_status' => $this->normalizeOperationalStatus($status),
        ]);

        return $table->fresh();
    }

    public function delete(int $id): void
    {
        $this->find($id)->delete();
    }

    protected function normalizeOperationalStatus(?string $status): string
    {
        $status = strtolower(trim((string) ($status ?: DiningTable::OP_FREE)));
        if (! in_array($status, DiningTable::OPERATIONAL_STATUSES, true)) {
            throw new Exception('Estado operativo inválido. Use free, occupied o billing.');
        }

        return $status;
    }

    protected function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}
