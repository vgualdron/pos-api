<?php

namespace App\Http\Controllers;

use App\Services\DiningTableService;
use App\Support\PlaceScope;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiningTableController extends Controller
{
    public function __construct(protected DiningTableService $service)
    {
    }

    public function byPlace(Request $request, $place): JsonResponse
    {
        try {
            PlaceScope::assert(
                $request->user(),
                (int) $place,
                'dining-tables.allPlaces'
            );

            $onlyActive = filter_var($request->query('active', false), FILTER_VALIDATE_BOOLEAN);

            return response()->json($this->service->byPlace((int) $place, $onlyActive));
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al listar mesas', 'message' => $e->getMessage()], 400);
        }
    }

    public function show(Request $request, $id): JsonResponse
    {
        try {
            $record = $this->service->find((int) $id);
            PlaceScope::assert($request->user(), (int) $record->place_id, 'dining-tables.allPlaces');

            return response()->json($record);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al obtener mesa', 'message' => $e->getMessage()], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'place_id' => 'required|integer|exists:places,id',
                'code' => 'required|string|max:40',
                'name' => 'required|string|max:120',
                'seats' => 'nullable|integer|min:1|max:100',
                'dining_zone_id' => 'nullable|integer|exists:dining_zones,id',
                'operational_status' => 'nullable|string|in:free,occupied,billing',
                'status' => 'nullable|string|max:20',
                'sort_order' => 'nullable|integer|min:0|max:9999',
            ]);
            PlaceScope::assert($request->user(), (int) $data['place_id'], 'dining-tables.allPlaces');
            $record = $this->service->create($data);

            return response()->json(['message' => 'Mesa creada', 'data' => $record], 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al crear mesa', 'message' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $existing = $this->service->find((int) $id);
            PlaceScope::assert($request->user(), (int) $existing->place_id, 'dining-tables.allPlaces');
            $data = $request->validate([
                'code' => 'sometimes|string|max:40',
                'name' => 'sometimes|string|max:120',
                'seats' => 'nullable|integer|min:1|max:100',
                'dining_zone_id' => 'nullable|integer|exists:dining_zones,id',
                'operational_status' => 'nullable|string|in:free,occupied,billing',
                'status' => 'nullable|string|max:20',
                'sort_order' => 'nullable|integer|min:0|max:9999',
            ]);
            $record = $this->service->update((int) $id, $data);

            return response()->json(['message' => 'Mesa actualizada', 'data' => $record]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al actualizar mesa', 'message' => $e->getMessage()], 400);
        }
    }

    public function updateStatus(Request $request, $id): JsonResponse
    {
        try {
            $existing = $this->service->find((int) $id);
            PlaceScope::assert($request->user(), (int) $existing->place_id, 'dining-tables.allPlaces');
            $data = $request->validate([
                'operational_status' => 'required|string|in:free,occupied,billing',
            ]);
            $record = $this->service->updateOperationalStatus((int) $id, $data['operational_status']);

            return response()->json(['message' => 'Estado de mesa actualizado', 'data' => $record]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al cambiar estado', 'message' => $e->getMessage()], 400);
        }
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        try {
            $existing = $this->service->find((int) $id);
            PlaceScope::assert($request->user(), (int) $existing->place_id, 'dining-tables.allPlaces');
            $this->service->delete((int) $id);

            return response()->json(['message' => 'Mesa eliminada']);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al eliminar mesa', 'message' => $e->getMessage()], 400);
        }
    }
}
