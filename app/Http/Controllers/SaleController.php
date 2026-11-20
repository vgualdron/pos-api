<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Services\SaleService;
use App\Support\PlaceScope;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SaleController extends Controller
{
    public function __construct(protected SaleService $service)
    {
    }

    public function catalog(Request $request): JsonResponse
    {
        try {
            $stationId = $request->query('station_id', $request->input('station_id'));
            $stationId = ($stationId === null || $stationId === '') ? null : (int) $stationId;

            return response()->json($this->service->catalogForUser($request->user(), $stationId));
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

    public function show(Request $request, $id): JsonResponse
    {
        try {
            $sale = $this->service->find((int) $id);
            PlaceScope::assert($request->user(), (int) $sale->place_id, 'sales.allPlaces');

            return response()->json($sale);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al consultar la venta', 'message' => $e->getMessage()], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'lines' => 'required|array|min:1',
                'lines.*.product_presentation_id' => 'required|integer|exists:product_presentations,id',
                'lines.*.qty' => 'required|numeric|min:0.0001',
                'lines.*.unit_price' => 'nullable|numeric|min:0',
                'lines.*.tax_rate' => 'nullable|numeric|in:0,5,19',
                'payment_method' => ['nullable', Rule::in(Sale::PAYMENT_METHODS)],
                'amount_received' => 'nullable|numeric|min:0',
                'discount_amount' => 'nullable|numeric|min:0',
                'discount_percent' => 'nullable|numeric|min:0|max:100',
                'customer_id' => 'nullable|integer|exists:customers,id',
                'station_id' => 'nullable|integer|exists:stations,id',
            ]);
            $sale = $this->service->create($request->user(), $data);

            return response()->json(['message' => 'Venta registrada', 'data' => $sale], 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al vender', 'message' => $e->getMessage()], 400);
        }
    }

    public function cancel(Request $request, $id): JsonResponse
    {
        try {
            $data = $request->validate([
                'reason' => 'nullable|string|max:255',
            ]);
            $sale = $this->service->cancel($request->user(), (int) $id, $data['reason'] ?? null);

            return response()->json(['message' => 'Venta anulada', 'data' => $sale]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al anular la venta', 'message' => $e->getMessage()], 400);
        }
    }
}
