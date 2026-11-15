<?php

namespace App\Http\Controllers;

use App\Services\PurchaseService;
use App\Support\PlaceScope;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function __construct(protected PurchaseService $service)
    {
    }

    public function byPlace(Request $request, $place): JsonResponse
    {
        try {
            PlaceScope::assert($request->user(), (int) $place, 'purchases.allPlaces');

            $data = $request->validate([
                'date_from' => 'nullable|date_format:Y-m-d',
                'date_to' => 'nullable|date_format:Y-m-d',
                'supplier_id' => 'nullable|integer|exists:suppliers,id',
                'q' => 'nullable|string|max:120',
            ]);

            return response()->json($this->service->allByPlace(
                (int) $place,
                $data['date_from'] ?? null,
                $data['date_to'] ?? null,
                isset($data['supplier_id']) ? (int) $data['supplier_id'] : null,
                $data['q'] ?? null
            ));
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al listar compras', 'message' => $e->getMessage()], 400);
        }
    }

    public function show(Request $request, $id): JsonResponse
    {
        try {
            $purchase = $this->service->find((int) $id);
            PlaceScope::assert($request->user(), (int) $purchase->place_id, 'purchases.allPlaces');

            return response()->json($purchase);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al consultar la compra', 'message' => $e->getMessage()], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'place_id' => 'required|integer|exists:places,id',
                'purchased_at' => 'nullable|date',
                'supplier_id' => 'nullable|integer|exists:suppliers,id',
                'supplier_name' => 'nullable|string|max:255',
                'notes' => 'nullable|string|max:1000',
                'lines' => 'required|array|min:1',
                'lines.*.product_presentation_id' => 'required|integer|exists:product_presentations,id',
                'lines.*.qty' => 'required|numeric|min:0.0001',
                'lines.*.unit_cost' => 'required|numeric|min:0',
                'lines.*.lot_code' => 'nullable|string|max:80',
                'lines.*.expires_at' => 'nullable|date',
            ]);
            PlaceScope::assert($request->user(), (int) $data['place_id'], 'purchases.allPlaces');
            $record = $this->service->create($request->user(), $data);

            return response()->json(['message' => 'Compra registrada', 'data' => $record], 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al registrar compra', 'message' => $e->getMessage()], 400);
        }
    }
}
