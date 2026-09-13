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
            return response()->json(['data' => $this->service->current($request->user())]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al consultar caja', 'message' => $e->getMessage()], 400);
        }
    }

    public function open(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'opening_cash' => 'nullable|numeric|min:0',
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
                'closing_cash' => 'nullable|numeric|min:0',
            ]);
            $session = $this->service->close($request->user(), $data);

            return response()->json(['message' => 'Caja cerrada', 'data' => $session]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al cerrar caja', 'message' => $e->getMessage()], 400);
        }
    }
}
