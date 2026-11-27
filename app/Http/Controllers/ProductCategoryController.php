<?php

namespace App\Http\Controllers;

use App\Services\ProductCategoryService;
use App\Support\PlaceScope;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    public function __construct(protected ProductCategoryService $service)
    {
    }

    public function byPlace(Request $request, $place): JsonResponse
    {
        try {
            PlaceScope::assert(
                $request->user(),
                (int) $place,
                'product-categories.allPlaces',
                'products.allPlaces'
            );

            $onlyActive = filter_var($request->query('active', false), FILTER_VALIDATE_BOOLEAN);

            return response()->json($this->service->byPlace((int) $place, $onlyActive));
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al listar categorías', 'message' => $e->getMessage()], 400);
        }
    }

    public function show(Request $request, $id): JsonResponse
    {
        try {
            $record = $this->service->find((int) $id);
            PlaceScope::assert($request->user(), (int) $record->place_id, 'product-categories.allPlaces', 'products.allPlaces');

            return response()->json($record);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al obtener categoría', 'message' => $e->getMessage()], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'place_id' => 'required|integer|exists:places,id',
                'name' => 'required|string|max:120',
                'sort_order' => 'nullable|integer|min:0|max:9999',
                'status' => 'nullable|string|max:20',
                'show_on_register' => 'nullable|boolean',
            ]);
            PlaceScope::assert($request->user(), (int) $data['place_id'], 'product-categories.allPlaces', 'products.allPlaces');
            $record = $this->service->create($data);

            return response()->json(['message' => 'Categoría creada', 'data' => $record], 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al crear categoría', 'message' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $existing = $this->service->find((int) $id);
            PlaceScope::assert($request->user(), (int) $existing->place_id, 'product-categories.allPlaces', 'products.allPlaces');
            $data = $request->validate([
                'name' => 'sometimes|string|max:120',
                'sort_order' => 'nullable|integer|min:0|max:9999',
                'status' => 'nullable|string|max:20',
                'show_on_register' => 'nullable|boolean',
            ]);
            $record = $this->service->update((int) $id, $data);

            return response()->json(['message' => 'Categoría actualizada', 'data' => $record]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al actualizar categoría', 'message' => $e->getMessage()], 400);
        }
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        try {
            $existing = $this->service->find((int) $id);
            PlaceScope::assert($request->user(), (int) $existing->place_id, 'product-categories.allPlaces', 'products.allPlaces');
            $this->service->delete((int) $id);

            return response()->json(['message' => 'Categoría eliminada']);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al eliminar categoría', 'message' => $e->getMessage()], 400);
        }
    }
}
