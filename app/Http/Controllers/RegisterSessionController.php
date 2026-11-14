<?php

namespace App\Http\Controllers;

use App\Services\RegisterSessionService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegisterSessionController extends Controller
{
    public function __construct(protected RegisterSessionService $service)
    {
    }

    public function current(Request $request): JsonResponse
    {
        try {
            $stationId = $this->optionalStationId($request);

            return response()->json(['data' => $this->service->current($request->user(), $stationId)]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al consultar caja', 'message' => $e->getMessage()], 400);
        }
    }

    public function summary(Request $request): JsonResponse
    {
        try {
            $stationId = $this->optionalStationId($request);

            return response()->json(['data' => $this->service->summary($request->user(), $stationId)]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al consultar resumen de caja', 'message' => $e->getMessage()], 400);
        }
    }

    public function open(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'opening_cash' => 'nullable|numeric|min:0',
                'station_id' => 'nullable|integer|exists:stations,id',
            ]);
            $session = $this->service->open($request->user(), $data);

            return response()->json(['message' => 'Caja abierta', 'data' => $session]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al abrir caja', 'message' => $e->getMessage()], 400);
        }
    }

    public function close(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'closing_cash' => 'required|numeric|min:0',
                'notes' => 'nullable|string|max:255',
                'station_id' => 'nullable|integer|exists:stations,id',
            ]);
            $session = $this->service->close($request->user(), $data);

            return response()->json(['message' => 'Caja cerrada', 'data' => $session]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al cerrar caja', 'message' => $e->getMessage()], 400);
        }
    }

    protected function optionalStationId(Request $request): ?int
    {
        $value = $request->query('station_id', $request->input('station_id'));
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
