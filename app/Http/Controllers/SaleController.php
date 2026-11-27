<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Services\SaleReturnService;
use App\Services\SaleService;
use App\Support\PlaceScope;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SaleController extends Controller
{
    public function __construct(
        protected SaleService $service,
        protected SaleReturnService $returnService
    ) {
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
                'lines.*.tax_id' => 'nullable|integer|exists:taxes,id',
                'payment_method' => ['nullable', Rule::in(Sale::PAYMENT_METHODS)],
                'amount_received' => 'nullable|numeric|min:0',
                'payments' => 'nullable|array|min:1',
                'payments.*.payment_method' => ['required_with:payments', Rule::in(Sale::PAYMENT_METHODS)],
                'payments.*.amount' => 'required_with:payments|numeric|min:0.01',
                'discount_amount' => 'nullable|numeric|min:0',
                'discount_percent' => 'nullable|numeric|min:0|max:100',
                'customer_id' => 'nullable|integer|exists:customers,id',
                'station_id' => 'nullable|integer|exists:stations,id',
                'document_type' => ['nullable', Rule::in(Sale::DOCUMENT_TYPES)],
            ]);
            $sale = $this->service->create($request->user(), $data);
            $message = 'Venta registrada';
            if (($sale->document_type ?? null) === Sale::DOCUMENT_ELECTRONIC_INVOICE) {
                if (($sale->fe_status ?? null) === 'accepted') {
                    $message = 'Venta registrada y factura electrónica emitida';
                } elseif (($sale->fe_status ?? null) === 'failed') {
                    $message = 'Venta registrada, pero falló la factura electrónica';
                }
            }

            return response()->json(['message' => $message, 'data' => $sale], 201);
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
            $message = 'Venta anulada';
            if (($sale->document_type ?? null) === Sale::DOCUMENT_ELECTRONIC_INVOICE && ! empty($sale->fe_number)) {
                if (($sale->nc_status ?? null) === 'accepted' || ! empty($sale->nc_cude)) {
                    $message = 'Venta anulada y nota crédito emitida';
                } elseif (($sale->nc_status ?? null) === 'failed') {
                    $message = 'Venta anulada, pero falló la nota crédito';
                }
            }

            return response()->json(['message' => $message, 'data' => $sale]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al anular la venta', 'message' => $e->getMessage()], 400);
        }
    }

    public function retryElectronicInvoice(Request $request, $id): JsonResponse
    {
        try {
            $sale = $this->service->find((int) $id);
            PlaceScope::assert($request->user(), (int) $sale->place_id, 'sales.allPlaces');

            $sale = $this->service->retryElectronicInvoice((int) $id);
            $accepted = ($sale->fe_status ?? null) === 'accepted' || ! empty($sale->fe_cufe);
            $message = $accepted
                ? 'Factura electrónica emitida'
                : ('Falló el reintento de factura electrónica'
                    . (! empty($sale->fe_error) ? ': ' . $sale->fe_error : ''));

            return response()->json([
                'message' => $message,
                'data' => $sale,
            ], $accepted ? 200 : 400);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Error al reintentar la factura electrónica',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function storeReturn(Request $request, $id): JsonResponse
    {
        try {
            $data = $request->validate([
                'reason' => 'nullable|string|max:255',
                'station_id' => 'nullable|integer|exists:stations,id',
                'lines' => 'required|array|min:1',
                'lines.*.sale_line_id' => 'required|integer|exists:sale_lines,id',
                'lines.*.qty' => 'required|numeric|min:0.0001',
                'payments' => 'nullable|array|min:1',
                'payments.*.payment_method' => ['required_with:payments', Rule::in(Sale::PAYMENT_METHODS)],
                'payments.*.amount' => 'required_with:payments|numeric|min:0.01',
            ]);

            $sale = $this->returnService->create($request->user(), (int) $id, $data);
            $message = 'Devolución registrada';
            if (($sale->document_type ?? null) === Sale::DOCUMENT_ELECTRONIC_INVOICE && ! empty($sale->fe_number)) {
                $latestReturn = collect($sale->returns ?? [])->sortByDesc('id')->first();
                $ncStatus = is_array($latestReturn)
                    ? ($latestReturn['dian_status'] ?? null)
                    : ($latestReturn->dian_status ?? null);
                if ($ncStatus === 'accepted') {
                    $message = 'Devolución registrada y nota crédito emitida';
                } elseif ($ncStatus === 'failed') {
                    $message = 'Devolución registrada, pero falló la nota crédito';
                }
            }

            return response()->json(['message' => $message, 'data' => $sale], 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al devolver', 'message' => $e->getMessage()], 400);
        }
    }
}
