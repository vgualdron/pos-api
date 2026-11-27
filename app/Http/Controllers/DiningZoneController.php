<?php

namespace App\Http\Controllers;

use App\Services\DiningZoneService;
use App\Support\PlaceScope;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiningZoneController extends Controller
{
    public function __construct(protected DiningZoneService $service)
    {
    }

    public function byPlace(Request $request, $place): JsonResponse
    {
        try {
            PlaceScope::assert($request->user(), (int) $place, 'dining-zones.allPlaces');
            $onlyActive = filter_var($request->query('active', false), FILTER_VALIDATE_BOOLEAN);

            return response()->json($this->service->byPlace((int) $place, $onlyActive));
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al listar zonas', 'message' => $e->getMessage()], 400);
        }
    }

    public function show(Request $request, $id): JsonResponse
    {
        try {
            $record = $this->service->find((int) $id);
            PlaceScope::assert($request->user(), (int) $record->place_id, 'dining-zones.allPlaces');

            return response()->json($record);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al obtener zona', 'message' => $e->getMessage()], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'place_id' => 'required|integer|exists:places,id',
                'name' => 'required|string|max:80',
                'sort_order' => 'nullable|integer|min:0|max:9999',
                'status' => 'nullable|string|max:20',
            ]);
            PlaceScope::assert($request->user(), (int) $data['place_id'], 'dining-zones.allPlaces');
            $record = $this->service->create($data);

            return response()->json(['message' => 'Zona creada', 'data' => $record], 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al crear zona', 'message' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $existing = $this->service->find((int) $id);
            PlaceScope::assert($request->user(), (int) $existing->place_id, 'dining-zones.allPlaces');
            $data = $request->validate([
                'name' => 'sometimes|string|max:80',
                'sort_order' => 'nullable|integer|min:0|max:9999',
                'status' => 'nullable|string|max:20',
            ]);
            $record = $this->service->update((int) $id, $data);

            return response()->json(['message' => 'Zona actualizada', 'data' => $record]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al actualizar zona', 'message' => $e->getMessage()], 400);
        }
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        try {
            $existing = $this->service->find((int) $id);
            PlaceScope::assert($request->user(), (int) $existing->place_id, 'dining-zones.allPlaces');
            $this->service->delete((int) $id);

            return response()->json(['message' => 'Zona eliminada']);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al eliminar zona', 'message' => $e->getMessage()], 400);
        }
    }
}
