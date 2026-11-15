<?php

namespace App\Http\Controllers;

use App\Services\SupplierService;
use App\Support\PlaceScope;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function __construct(protected SupplierService $service)
    {
    }

    public function byPlace(Request $request, $place): JsonResponse
    {
        try {
            PlaceScope::assert(
                $request->user(),
                (int) $place,
                'suppliers.allPlaces',
                'purchases.allPlaces'
            );

            $onlyActive = filter_var($request->query('active', false), FILTER_VALIDATE_BOOLEAN);

            return response()->json($this->service->byPlace((int) $place, $onlyActive));
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al listar proveedores', 'message' => $e->getMessage()], 400);
        }
    }

    public function show(Request $request, $id): JsonResponse
    {
        try {
            $record = $this->service->find((int) $id);
            PlaceScope::assert($request->user(), (int) $record->place_id, 'suppliers.allPlaces', 'purchases.allPlaces');

            return response()->json($record);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al obtener proveedor', 'message' => $e->getMessage()], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'place_id' => 'required|integer|exists:places,id',
                'name' => 'required|string|max:255',
                'document_type' => 'nullable|string|max:40',
                'document_number' => 'nullable|string|max:80',
                'phone' => 'nullable|string|max:40',
                'email' => 'nullable|email|max:120',
                'address' => 'nullable|string|max:255',
                'status' => 'nullable|string|max:20',
                'notes' => 'nullable|string|max:1000',
            ]);
            PlaceScope::assert($request->user(), (int) $data['place_id'], 'suppliers.allPlaces', 'purchases.allPlaces');
            $record = $this->service->create($data);

            return response()->json(['message' => 'Proveedor creado', 'data' => $record], 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al crear proveedor', 'message' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $existing = $this->service->find((int) $id);
            PlaceScope::assert($request->user(), (int) $existing->place_id, 'suppliers.allPlaces', 'purchases.allPlaces');
            $data = $request->validate([
                'name' => 'sometimes|string|max:255',
                'document_type' => 'nullable|string|max:40',
                'document_number' => 'nullable|string|max:80',
                'phone' => 'nullable|string|max:40',
                'email' => 'nullable|email|max:120',
                'address' => 'nullable|string|max:255',
                'status' => 'nullable|string|max:20',
                'notes' => 'nullable|string|max:1000',
            ]);
            $record = $this->service->update((int) $id, $data);

            return response()->json(['message' => 'Proveedor actualizado', 'data' => $record]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al actualizar proveedor', 'message' => $e->getMessage()], 400);
        }
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        try {
            $existing = $this->service->find((int) $id);
            PlaceScope::assert($request->user(), (int) $existing->place_id, 'suppliers.allPlaces', 'purchases.allPlaces');
            $this->service->delete((int) $id);

            return response()->json(['message' => 'Proveedor eliminado']);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al eliminar proveedor', 'message' => $e->getMessage()], 400);
        }
    }
}
