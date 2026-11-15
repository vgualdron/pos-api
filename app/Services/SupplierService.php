<?php

namespace App\Services;

use App\Models\Supplier;
use Exception;

class SupplierService
{
    public function byPlace(int $placeId, bool $onlyActive = false)
    {
        $query = Supplier::query()
            ->where('place_id', $placeId)
            ->orderBy('name');

        if ($onlyActive) {
            $query->where('status', 'activo');
        }

        return $query->get();
    }

    public function find(int $id): Supplier
    {
        return Supplier::findOrFail($id);
    }

    public function create(array $data): Supplier
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new Exception('El nombre del proveedor es obligatorio.');
        }

        return Supplier::create([
            'place_id' => (int) $data['place_id'],
            'name' => $name,
            'document_type' => $data['document_type'] ?? null,
            'document_number' => $data['document_number'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'status' => $data['status'] ?? 'activo',
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function update(int $id, array $data): Supplier
    {
        $supplier = $this->find($id);

        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                throw new Exception('El nombre del proveedor es obligatorio.');
            }
            $data['name'] = $name;
        }

        $supplier->update($data);

        return $supplier->fresh();
    }

    public function delete(int $id): void
    {
        $supplier = $this->find($id);
        if ($supplier->purchases()->exists()) {
            throw new Exception('No se puede eliminar un proveedor con compras asociadas. Márquelo inactivo.');
        }
        $supplier->delete();
    }
}
