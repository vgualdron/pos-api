<?php

namespace App\Http\Controllers;

use App\Models\StockAdjustment;
use App\Services\InventoryService;
use App\Support\PlaceScope;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function __construct(protected InventoryService $service)
    {
    }

    public function lots(Request $request, $place): JsonResponse
    {
        try {
            PlaceScope::assert($request->user(), (int) $place, 'inventory.allPlaces', 'products.allPlaces');
            $data = $request->validate([
                'filter' => 'nullable|string|in:all,low,expiring,expired',
                'q' => 'nullable|string|max:120',
            ]);

            return response()->json($this->service->lotsForPlace(
                (int) $place,
                $data['filter'] ?? null,
                $data['q'] ?? null
            ));
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al listar lotes', 'message' => $e->getMessage()], 400);
        }
    }

    public function products(Request $request, $place): JsonResponse
    {
        try {
            PlaceScope::assert($request->user(), (int) $place, 'inventory.allPlaces', 'products.allPlaces');

            return response()->json($this->service->productsForPlace((int) $place));
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al listar inventario', 'message' => $e->getMessage()], 400);
        }
    }

    public function summary(Request $request, $place): JsonResponse
    {
        try {
            PlaceScope::assert($request->user(), (int) $place, 'inventory.allPlaces', 'products.allPlaces');

            return response()->json(['data' => $this->service->summaryForPlace((int) $place)]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al consultar alertas', 'message' => $e->getMessage()], 400);
        }
    }

    public function adjustments(Request $request, $place): JsonResponse
    {
        try {
            PlaceScope::assert($request->user(), (int) $place, 'inventory.allPlaces', 'products.allPlaces');
            $data = $request->validate([
                'date_from' => 'nullable|date_format:Y-m-d',
                'date_to' => 'nullable|date_format:Y-m-d',
                'type' => ['nullable', Rule::in(StockAdjustment::TYPES)],
                'q' => 'nullable|string|max:120',
            ]);

            return response()->json($this->service->adjustmentsForPlace(
                (int) $place,
                $data['date_from'] ?? null,
                $data['date_to'] ?? null,
                $data['type'] ?? null,
                $data['q'] ?? null
            ));
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al listar ajustes', 'message' => $e->getMessage()], 400);
        }
    }

    public function adjust(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'stock_lot_id' => 'required|integer|exists:stock_lots,id',
                'type' => ['required', Rule::in(StockAdjustment::TYPES)],
                'qty_base' => 'required|numeric|min:0',
                'notes' => 'nullable|string|max:255',
                'expires_at' => 'nullable|date',
            ]);

            $lot = \App\Models\StockLot::findOrFail((int) $data['stock_lot_id']);
            PlaceScope::assert($request->user(), (int) $lot->place_id, 'inventory.allPlaces', 'products.allPlaces');

            $updated = $this->service->adjust($request->user(), $data);

            return response()->json(['message' => 'Inventario actualizado', 'data' => $updated]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al ajustar inventario', 'message' => $e->getMessage()], 400);
        }
    }
}
