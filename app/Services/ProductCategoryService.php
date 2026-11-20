<?php

namespace App\Services;

use App\Models\ProductCategory;
use Exception;

class ProductCategoryService
{
    public function byPlace(int $placeId, bool $onlyActive = false)
    {
        $query = ProductCategory::query()
            ->where('place_id', $placeId)
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($onlyActive) {
            $query->where('status', 'activo');
        }

        return $query->get();
    }

    public function find(int $id): ProductCategory
    {
        return ProductCategory::findOrFail($id);
    }

    public function create(array $data): ProductCategory
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new Exception('El nombre de la categoría es obligatorio.');
        }

        $placeId = (int) $data['place_id'];
        if (ProductCategory::where('place_id', $placeId)->where('name', $name)->exists()) {
            throw new Exception('Ya existe una categoría con ese nombre en la sede.');
        }

        return ProductCategory::create([
            'place_id' => $placeId,
            'name' => $name,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'status' => $data['status'] ?? 'activo',
        ]);
    }

    public function update(int $id, array $data): ProductCategory
    {
        $category = $this->find($id);

        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                throw new Exception('El nombre de la categoría es obligatorio.');
            }
            $exists = ProductCategory::where('place_id', $category->place_id)
                ->where('name', $name)
                ->where('id', '!=', $category->id)
                ->exists();
            if ($exists) {
                throw new Exception('Ya existe una categoría con ese nombre en la sede.');
            }
            $data['name'] = $name;
        }

        $category->update($data);

        return $category->fresh();
    }

    public function delete(int $id): void
    {
        $category = $this->find($id);
        if ($category->products()->exists()) {
            throw new Exception('No se puede eliminar una categoría con productos. Márquela inactiva o reasigne los productos.');
        }
        $category->delete();
    }
}
