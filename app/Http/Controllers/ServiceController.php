<?php

namespace App\Http\Controllers;

use App\Services\ServiceCatalogService;
use App\Support\PlaceScope;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    protected ServiceCatalogService $service;

    protected array $fields = [
        'place_id',
        'name',
        'code',
        'prefix',
        'description',
        'follow_up_service_ids',
        'estimated_minutes',
        'order',
        'status',
    ];

    public function __construct(ServiceCatalogService $service)
    {
        $this->service = $service;
    }

    public function index(): JsonResponse
    {
        try {
            return response()->json($this->service->all());
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al obtener servicios', 'message' => $e->getMessage()], 500);
        }
    }

    public function show(Request $request, $id): JsonResponse
    {
        try {
            $record = $this->service->find($id);
            PlaceScope::assert($request->user(), (int) $record->place_id, 'services.allPlaces');

            return response()->json($record);
        } catch (Exception $e) {
            return response()->json(['error' => "Error al obtener el servicio con ID $id", 'message' => $e->getMessage()], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $data = $request->only($this->fields);
            PlaceScope::assert($request->user(), (int) ($data['place_id'] ?? 0), 'services.allPlaces');
            $record = $this->service->create($data);

            return response()->json(['message' => 'Servicio creado con éxito', 'data' => $record], 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al crear el servicio', 'message' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $existing = $this->service->find($id);
            PlaceScope::assert($request->user(), (int) $existing->place_id, 'services.allPlaces');
            $data = $request->only($this->fields);
            if (! empty($data['place_id'])) {
                PlaceScope::assert($request->user(), (int) $data['place_id'], 'services.allPlaces');
            }
            $record = $this->service->update($id, $data);

            return response()->json(['message' => 'Servicio modificado con éxito', 'data' => $record], 200);
        } catch (Exception $e) {
            return response()->json(['error' => "Error al actualizar el servicio con ID $id", 'message' => $e->getMessage()], 400);
        }
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        try {
            $existing = $this->service->find($id);
            PlaceScope::assert($request->user(), (int) $existing->place_id, 'services.allPlaces');
            $this->service->delete($id);

            return response()->json(['message' => 'Servicio eliminado con éxito'], 200);
        } catch (Exception $e) {
            return response()->json(['error' => "Error al eliminar el servicio con ID $id", 'message' => $e->getMessage()], 400);
        }
    }

    public function byStatus(string $status): JsonResponse
    {
        try {
            return response()->json($this->service->findByStatuses($status));
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al obtener servicios por estado', 'message' => $e->getMessage()], 400);
        }
    }

    public function byPlace(Request $request, $placeId): JsonResponse
    {
        try {
            PlaceScope::assert(
                $request->user(),
                (int) $placeId,
                'services.allPlaces',
                'stations.allPlaces',
                'station_services.edit'
            );

            return response()->json($this->service->findByPlace($placeId));
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al obtener servicios por sede', 'message' => $e->getMessage()], 400);
        }
    }
}
