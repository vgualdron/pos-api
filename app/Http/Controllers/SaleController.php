<?php

namespace App\Http\Controllers;

use App\Services\SaleService;
use App\Support\PlaceScope;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function __construct(protected SaleService $service)
    {
    }

    public function catalog(Request $request): JsonResponse
    {
        try {
            return response()->json($this->service->catalogForUser($request->user()));
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al cargar catálogo', 'message' => $e->getMessage()], 400);
        }
    }

    public function byPlace(Request $request, $place): JsonResponse
    {
        try {
            PlaceScope::assert($request->user(), (int) $place, 'sales.allPlaces');

            $data = $request->validate([
                'date_from' => 'nullable|date_format:Y-m-d',
                'date_to' => 'nullable|date_format:Y-m-d',
            ]);

            return response()->json($this->service->recentForPlace(
                (int) $place,
                $data['date_from'] ?? null,
                $data['date_to'] ?? null
            ));
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al listar ventas', 'message' => $e->getMessage()], 400);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'lines' => 'required|array|min:1',
                'lines.*.product_presentation_id' => 'required|integer|exists:product_presentations,id',
                'lines.*.qty' => 'required|numeric|min:0.0001',
            ]);
            $sale = $this->service->create($request->user(), $data);

            return response()->json(['message' => 'Venta registrada', 'data' => $sale], 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al vender', 'message' => $e->getMessage()], 400);
        }
    }
}
