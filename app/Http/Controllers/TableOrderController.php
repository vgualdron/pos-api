<?php

namespace App\Http\Controllers;

use App\Models\DiningTable;
use App\Models\Sale;
use App\Models\TableOrderLine;
use App\Services\TableOrderService;
use App\Support\PlaceScope;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TableOrderController extends Controller
{
    public function __construct(protected TableOrderService $service)
    {
    }

    public function catalog(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'place_id' => 'required|integer|exists:places,id',
            ]);
            PlaceScope::assert($request->user(), (int) $data['place_id'], 'dining-tables.allPlaces');

            return response()->json($this->service->catalogForPlace((int) $data['place_id']));
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al cargar catálogo', 'message' => $e->getMessage()], 400);
        }
    }

    public function showByTable(Request $request, $diningTableId): JsonResponse
    {
        try {
            $table = DiningTable::findOrFail((int) $diningTableId);
            PlaceScope::assert($request->user(), (int) $table->place_id, 'dining-tables.allPlaces');

            $create = filter_var($request->query('create', false), FILTER_VALIDATE_BOOLEAN);
            $order = $create
                ? $this->service->openOrderForTable((int) $diningTableId, $request->user()?->id)
                : $this->service->getOpenOrderForTable((int) $diningTableId);

            return response()->json([
                'table' => $table->load('diningZone'),
                'order' => $order,
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al obtener comanda', 'message' => $e->getMessage()], 400);
        }
    }

    public function addLine(Request $request, $diningTableId): JsonResponse
    {
        try {
            $table = DiningTable::findOrFail((int) $diningTableId);
            PlaceScope::assert($request->user(), (int) $table->place_id, 'dining-tables.allPlaces');
            $data = $request->validate([
                'product_presentation_id' => 'required|integer|exists:product_presentations,id',
                'qty' => 'required|numeric|min:0.001|max:9999',
                'notes' => 'nullable|string|max:500',
                'unit_price' => 'nullable|numeric|min:0',
            ]);

            $order = $this->service->addLine((int) $diningTableId, $data, $request->user()?->id);

            return response()->json(['message' => 'Producto agregado a la comanda', 'data' => $order], 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al agregar producto', 'message' => $e->getMessage()], 400);
        }
    }

    public function updateLine(Request $request, $lineId): JsonResponse
    {
        try {
            $line = TableOrderLine::with('order')->findOrFail((int) $lineId);
            PlaceScope::assert($request->user(), (int) $line->order->place_id, 'dining-tables.allPlaces');
            $data = $request->validate([
                'qty' => 'sometimes|numeric|min:0.001|max:9999',
                'notes' => 'nullable|string|max:500',
            ]);

            $order = $this->service->updateLine((int) $lineId, $data, $request->user()?->id);

            return response()->json(['message' => 'Línea actualizada', 'data' => $order]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al actualizar línea', 'message' => $e->getMessage()], 400);
        }
    }

    public function removeLine(Request $request, $lineId): JsonResponse
    {
        try {
            $line = TableOrderLine::with('order')->findOrFail((int) $lineId);
            PlaceScope::assert($request->user(), (int) $line->order->place_id, 'dining-tables.allPlaces');
            $order = $this->service->removeLine((int) $lineId, $request->user()?->id);

            return response()->json(['message' => 'Producto quitado de la comanda', 'data' => $order]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al quitar producto', 'message' => $e->getMessage()], 400);
        }
    }

    public function send(Request $request, $diningTableId): JsonResponse
    {
        try {
            $table = DiningTable::findOrFail((int) $diningTableId);
            PlaceScope::assert($request->user(), (int) $table->place_id, 'dining-tables.allPlaces');
            $result = $this->service->sendPending((int) $diningTableId, $request->user()?->id);

            return response()->json([
                'message' => 'Pedido enviado a cocina',
                'data' => $result['order'],
                'batches' => $result['batches'],
                'send_batch_id' => $result['send_batch_id'],
                'invoice_printer_ip' => $result['invoice_printer_ip'],
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al enviar pedido', 'message' => $e->getMessage()], 400);
        }
    }

    public function checkout(Request $request, $diningTableId): JsonResponse
    {
        try {
            $table = DiningTable::findOrFail((int) $diningTableId);
            PlaceScope::assert(
                $request->user(),
                (int) $table->place_id,
                'dining-tables.allPlaces',
                'sales.allPlaces'
            );

            $data = $request->validate([
                'payment_method' => ['nullable', Rule::in(Sale::PAYMENT_METHODS)],
                'amount_received' => 'nullable|numeric|min:0',
                'payments' => 'nullable|array|min:1',
                'payments.*.payment_method' => ['required_with:payments', Rule::in(Sale::PAYMENT_METHODS)],
                'payments.*.amount' => 'required_with:payments|numeric|min:0.01',
                'discount_amount' => 'nullable|numeric|min:0',
                'discount_percent' => 'nullable|numeric|min:0|max:100',
                'tip_amount' => 'nullable|numeric|min:0',
                'tip_percent' => 'nullable|numeric|min:0|max:100',
                'customer_id' => 'nullable|integer|exists:customers,id',
                'station_id' => 'nullable|integer|exists:stations,id',
                'document_type' => ['nullable', Rule::in(Sale::DOCUMENT_TYPES)],
            ]);

            $result = $this->service->checkout((int) $diningTableId, $request->user(), $data);
            $sale = $result['sale'];

            $message = 'Mesa facturada y liberada';
            if (($sale->document_type ?? null) === Sale::DOCUMENT_ELECTRONIC_INVOICE) {
                if (($sale->fe_status ?? null) === 'accepted') {
                    $message = 'Mesa facturada con FE y liberada';
                } elseif (($sale->fe_status ?? null) === 'failed') {
                    $message = 'Mesa facturada y liberada, pero falló la factura electrónica';
                }
            }

            return response()->json([
                'message' => $message,
                'data' => $result['order'],
                'sale' => $sale,
                'table' => $result['table'],
            ], 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al facturar la mesa', 'message' => $e->getMessage()], 400);
        }
    }
}
