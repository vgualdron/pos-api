<?php

namespace App\Http\Controllers;

use App\Services\StationService;
use App\Support\PlaceScope;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StationController extends Controller
{
    protected StationService $service;

    protected array $fields = [
        'place_id',
        'name',
        'code',
        'type',
        'can_generate_tickets',
        'can_call_tickets',
        'max_open_turns',
        'show_priority_option',
        'issues_super_priority',
        'printer_ip',
        'status',
    ];

    public function __construct(StationService $service)
    {
        $this->service = $service;
    }

    public function index(): JsonResponse
    {
        try {
            return response()->json($this->service->all());
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al obtener estaciones', 'message' => $e->getMessage()], 500);
        }
    }

    public function show(Request $request, $id): JsonResponse
    {
        try {
            $record = $this->service->find($id);
            PlaceScope::assert($request->user(), (int) $record->place_id, 'stations.allPlaces');

            return response()->json($record);
        } catch (Exception $e) {
            return response()->json(['error' => "Error al obtener la estación con ID $id", 'message' => $e->getMessage()], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $data = $request->only($this->fields);
            PlaceScope::assert($request->user(), (int) ($data['place_id'] ?? 0), 'stations.allPlaces');
            $record = $this->service->create($data);

            return response()->json(['message' => 'Estación creada con éxito', 'data' => $record], 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al crear la estación', 'message' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $existing = $this->service->find($id);
            PlaceScope::assert($request->user(), (int) $existing->place_id, 'stations.allPlaces');
            $data = $request->only($this->fields);
            if (! empty($data['place_id'])) {
                PlaceScope::assert($request->user(), (int) $data['place_id'], 'stations.allPlaces');
            }
            $record = $this->service->update($id, $data);

            return response()->json(['message' => 'Estación modificada con éxito', 'data' => $record], 200);
        } catch (Exception $e) {
            return response()->json(['error' => "Error al actualizar la estación con ID $id", 'message' => $e->getMessage()], 400);
        }
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        try {
            $existing = $this->service->find($id);
            PlaceScope::assert($request->user(), (int) $existing->place_id, 'stations.allPlaces');
            $this->service->delete($id);

            return response()->json(['message' => 'Estación eliminada con éxito'], 200);
        } catch (Exception $e) {
            return response()->json(['error' => "Error al eliminar la estación con ID $id", 'message' => $e->getMessage()], 400);
        }
    }

    public function byStatus(string $status): JsonResponse
    {
        try {
            return response()->json($this->service->findByStatuses($status));
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al obtener estaciones por estado', 'message' => $e->getMessage()], 400);
        }
    }

    public function byPlace(Request $request, $placeId): JsonResponse
    {
        try {
            PlaceScope::assert(
                $request->user(),
                (int) $placeId,
                'stations.allPlaces',
                'display_videos.allPlaces',
                'turns.allPlaces',
                'users.create',
                'users.edit'
            );

            return response()->json($this->service->findByPlace($placeId));
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al obtener estaciones por sede', 'message' => $e->getMessage()], 400);
        }
    }

    public function syncServices(Request $request, $id): JsonResponse
    {
        try {
            PlaceScope::assertStation($request->user(), (int) $id, 'stations.allPlaces');
            $request->validate([
                'services' => 'required|array',
                'services.*.service_id' => 'required|integer|exists:services,id',
                'services.*.can_generate' => 'boolean',
                'services.*.can_call' => 'boolean',
                'services.*.can_display' => 'boolean',
                'services.*.display_order' => 'integer',
            ]);

            $record = $this->service->syncServices($id, $request->input('services'));

            return response()->json(['message' => 'Servicios de estación actualizados con éxito', 'data' => $record], 200);
        } catch (Exception $e) {
            return response()->json(['error' => "Error al sincronizar servicios de la estación $id", 'message' => $e->getMessage()], 400);
        }
    }
}
