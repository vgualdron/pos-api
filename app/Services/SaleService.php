<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\RegisterSession;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Station;
use App\Models\StockLot;
use App\Services\Factus\FactusCreditNoteService;
use App\Services\Factus\FactusInvoiceService;
use App\Support\PlaceScope;
use App\Support\PosStation;
use App\Support\SalePricing;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class SaleService
{
    public function __construct(
        protected FactusInvoiceService $factusInvoice,
        protected FactusCreditNoteService $factusCreditNote,
        protected PosResolutionSettingService $posResolution
    ) {
    }

    public function catalogForUser($user, ?int $stationId = null)
    {
        $station = PosStation::resolve($user, $stationId);

        return app(ProductService::class)->catalog((int) $station->place_id);
    }

    public function recentForPlace(int $placeId, ?string $dateFrom = null, ?string $dateTo = null)
    {
        $from = $dateFrom
            ? Carbon::parse($dateFrom)->startOfDay()
            : Carbon::today()->startOfDay();
        $to = $dateTo
            ? Carbon::parse($dateTo)->endOfDay()
            : ($dateFrom
                ? Carbon::parse($dateFrom)->endOfDay()
                : Carbon::today()->endOfDay());

        if ($to->lt($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return Sale::with(['lines.lots', 'payments', 'returns.payments', 'station', 'user', 'customer'])
            ->where('place_id', $placeId)
            ->whereBetween('sold_at', [$from, $to])
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->limit(1000)
            ->get();
    }

    public function find(int $id): Sale
    {
        return Sale::with([
            'lines.lots',
            'lines.presentation',
            'lines.product',
            'payments',
            'returns.lines',
            'returns.payments',
            'station',
            'user',
            'customer',
        ])->findOrFail($id);
    }

    public function create($user, array $data): Sale
    {
        $station = PosStation::resolve($user, isset($data['station_id']) ? (int) $data['station_id'] : null);
        $session = $this->requireOpenSession($station, $user);
        $lines = $data['lines'] ?? [];

        if (count($lines) === 0) {
            throw new Exception('La venta necesita al menos un producto.');
        }

        $customerId = $this->resolveCustomerId($station->place_id, $data['customer_id'] ?? null);
        $documentType = $this->resolveDocumentType($data['document_type'] ?? null);

        if ($documentType === Sale::DOCUMENT_ELECTRONIC_INVOICE && ! $customerId) {
            throw new Exception('Para factura electrónica debe seleccionar un cliente.');
        }

        $sale = DB::transaction(function () use ($station, $user, $session, $lines, $data, $customerId, $documentType) {
            $prepared = [];
            $itemsSubtotal = 0.0;

            foreach ($lines as $row) {
                $presentation = ProductPresentation::with(['product', 'tax'])->findOrFail($row['product_presentation_id']);
                $product = $presentation->product;

                if ((int) $product->place_id !== (int) $station->place_id) {
                    throw new Exception("El producto {$product->name} no pertenece a esta sede.");
                }

                if ($presentation->status !== 'activo' || $product->status !== 'activo') {
                    throw new Exception("{$product->name} no está activo.");
                }

                $qty = (float) ($row['qty'] ?? 0);
                if ($qty <= 0) {
                    throw new Exception('La cantidad vendida debe ser mayor que 0.');
                }

                $factor = (float) $presentation->factor;
                $qtyBase = round($qty * $factor, 4);
                $catalogPrice = (float) $presentation->sale_price;
                $unitPrice = $catalogPrice;

                if (array_key_exists('unit_price', $row) && $row['unit_price'] !== null && $row['unit_price'] !== '') {
                    $requestedPrice = round((float) $row['unit_price'], 2);
                    if ($requestedPrice < 0) {
                        throw new Exception('El precio de venta no puede ser negativo.');
                    }
                    if (abs($requestedPrice - $catalogPrice) > 0.0001) {
                        if (!$user->can('sales.overridePrice')) {
                            throw new Exception('No tiene permiso para cambiar el precio en la venta.');
                        }
                    }
                    $unitPrice = $requestedPrice;
                }

                // Tax always comes from the presentation catalog (price includes selected tax).
                $tax = $presentation->tax ?: SalePricing::resolveTax($presentation->tax_id ? (int) $presentation->tax_id : null);
                $taxRate = round((float) $tax->rate, 2);
                $lineGross = round($qty * $unitPrice, 2);
                $itemsSubtotal = round($itemsSubtotal + $lineGross, 2);

                $prepared[] = [
                    'product' => $product,
                    'presentation' => $presentation,
                    'qty' => $qty,
                    'factor' => $factor,
                    'qty_base' => $qtyBase,
                    'unit_price' => $unitPrice,
                    'tax' => $tax,
                    'tax_rate' => $taxRate,
                    'line_gross' => $lineGross,
                ];
            }

            [$discountAmount, $discountPercent] = $this->resolveDiscount($data, $itemsSubtotal);
            $lineGrosses = array_map(fn ($row) => $row['line_gross'], $prepared);
            $allocatedDiscounts = SalePricing::allocateDiscount($lineGrosses, $discountAmount);

            $sale = Sale::create([
                'place_id' => $station->place_id,
                'station_id' => $station->id,
                'user_id' => $user->id,
                'customer_id' => $customerId,
                'register_session_id' => $session->id,
                'sold_at' => Carbon::now(),
                'subtotal' => $itemsSubtotal,
                'discount_amount' => $discountAmount,
                'discount_percent' => $discountPercent,
                'tax_base' => 0,
                'tax_total' => 0,
                'total' => 0,
                'cost_total' => 0,
                'status' => Sale::STATUS_COMPLETED,
                'document_type' => $documentType,
                'payment_method' => Sale::PAYMENT_CASH,
            ]);

            $taxBase = 0.0;
            $taxTotal = 0.0;
            $payableTotal = 0.0;
            $costTotal = 0.0;

            foreach ($prepared as $index => $row) {
                $lineDiscount = round((float) ($allocatedDiscounts[$index] ?? 0), 2);
                $linePayable = round(max(0, $row['line_gross'] - $lineDiscount), 2);
                $split = SalePricing::splitGross($linePayable, $row['tax_rate']);

                $tax = $row['tax'];
                $saleLine = $sale->lines()->create([
                    'product_id' => $row['product']->id,
                    'product_presentation_id' => $row['presentation']->id,
                    'qty' => $row['qty'],
                    'factor' => $row['factor'],
                    'qty_base' => $row['qty_base'],
                    'unit_price' => $row['unit_price'],
                    'tax_id' => $tax->id,
                    'tax_rate' => $row['tax_rate'],
                    'tax_name' => $tax->displayName(),
                    'tax_code' => $tax->code,
                    'discount_amount' => $lineDiscount,
                    'line_base' => $split['base'],
                    'tax_amount' => $split['tax'],
                    'line_total' => $linePayable,
                    'cost_total' => 0,
                ]);

                $lineCost = $this->consumeStockForLine(
                    (int) $station->place_id,
                    $row['product'],
                    $row['presentation'],
                    (float) $row['qty'],
                    (float) $row['qty_base'],
                    $saleLine->id
                );

                $saleLine->update(['cost_total' => $lineCost]);
                $taxBase = round($taxBase + $split['base'], 2);
                $taxTotal = round($taxTotal + $split['tax'], 2);
                $payableTotal = round($payableTotal + $linePayable, 2);
                $costTotal += $lineCost;
            }

            [$tipAmount, $tipPercent] = $this->resolveTip($data, $payableTotal);
            $amountDue = round($payableTotal + $tipAmount, 2);

            [$paymentMethod, $payments, $amountReceived, $changeAmount] = $this->resolvePayments(
                $data,
                $amountDue
            );

            foreach ($payments as $payment) {
                SalePayment::create([
                    'sale_id' => $sale->id,
                    'payment_method' => $payment['payment_method'],
                    'amount' => $payment['amount'],
                    'sort_order' => $payment['sort_order'],
                ]);
            }

            $posFields = [];
            if ($documentType === Sale::DOCUMENT_POS_TICKET) {
                $allocated = $this->posResolution->allocateForPlace((int) $station->place_id);
                if ($allocated) {
                    $posFields = $allocated;
                }
            }

            $sale->update(array_merge([
                'subtotal' => $itemsSubtotal,
                'discount_amount' => $discountAmount,
                'discount_percent' => $discountPercent,
                'tax_base' => $taxBase,
                'tax_total' => $taxTotal,
                'total' => $payableTotal,
                'tip_amount' => $tipAmount,
                'tip_percent' => $tipPercent,
                'cost_total' => round($costTotal, 4),
                'payment_method' => $paymentMethod,
                'amount_received' => $amountReceived,
                'change_amount' => $changeAmount,
            ], $posFields));

            return $sale->fresh(['lines.lots', 'lines.presentation', 'lines.product', 'payments', 'station', 'user', 'customer']);
        });

        if ($documentType === Sale::DOCUMENT_ELECTRONIC_INVOICE) {
            try {
                return $this->factusInvoice->emitForSale($sale);
            } catch (Exception $e) {
                // Sale is already persisted; FE failure is recorded on the sale row.
                return $this->freshSale((int) $sale->id);
            }
        }

        return $sale;
    }

    public function retryElectronicInvoice(int $saleId): Sale
    {
        $sale = $this->find($saleId);

        if (($sale->document_type ?? null) !== Sale::DOCUMENT_ELECTRONIC_INVOICE) {
            throw new Exception('Esta venta no es factura electrónica.');
        }

        if (($sale->status ?? null) === Sale::STATUS_CANCELLED) {
            throw new Exception('No se puede emitir FE de una venta anulada.');
        }

        if (! empty($sale->fe_cufe) || ($sale->fe_status ?? null) === 'accepted') {
            throw new Exception('Esta venta ya tiene factura electrónica aceptada.');
        }

        $status = $sale->fe_status ?? null;
        if (! in_array($status, ['failed', 'pending', 'submitted', null, ''], true)) {
            throw new Exception('Esta venta no admite reintento de factura electrónica.');
        }

        try {
            return $this->factusInvoice->emitForSale($sale);
        } catch (Exception $e) {
            return $this->freshSale($saleId);
        }
    }

    public function retryCreditNote(int $saleId): Sale
    {
        $sale = $this->find($saleId);

        return $this->factusCreditNote->retryForCancel($sale)->fresh([
            'lines.lots',
            'lines.presentation',
            'lines.product',
            'payments',
            'returns.lines',
            'returns.payments',
            'station',
            'user',
            'customer',
        ]);
    }

    protected function freshSale(int $saleId): Sale
    {
        return Sale::with([
            'lines.lots',
            'lines.presentation',
            'lines.product',
            'payments',
            'station',
            'user',
            'customer',
        ])->findOrFail($saleId);
    }

    protected function resolveDocumentType($value): string
    {
        $type = is_string($value) ? trim($value) : '';
        if ($type === '' || ! in_array($type, Sale::DOCUMENT_TYPES, true)) {
            return Sale::DOCUMENT_POS_TICKET;
        }

        return $type;
    }

    protected function resolveDiscount(array $data, float $itemsSubtotal): array
    {
        $percent = null;
        if (array_key_exists('discount_percent', $data) && $data['discount_percent'] !== null && $data['discount_percent'] !== '') {
            $percent = round((float) $data['discount_percent'], 2);
            if ($percent < 0 || $percent > 100) {
                throw new Exception('El porcentaje de descuento debe estar entre 0 y 100.');
            }
        }

        $amount = 0.0;
        $hasAmount = array_key_exists('discount_amount', $data)
            && $data['discount_amount'] !== null
            && $data['discount_amount'] !== '';
        if ($hasAmount) {
            $amount = round((float) $data['discount_amount'], 2);
        }
        if ($amount <= 0 && $percent !== null) {
            $amount = round($itemsSubtotal * ($percent / 100), 2);
        }

        if ($amount < 0) {
            throw new Exception('El descuento no puede ser negativo.');
        }
        if ($amount - $itemsSubtotal > 0.0001) {
            throw new Exception('El descuento no puede ser mayor al subtotal.');
        }

        if ($percent === null && $itemsSubtotal > 0 && $amount > 0) {
            $percent = round(($amount / $itemsSubtotal) * 100, 2);
        }

        return [round($amount, 2), $percent];
    }

    /**
     * Tip is optional and defaults to 0. Payments must cover merchandise total + tip.
     *
     * @return array{0:float,1:?float}
     */
    protected function resolveTip(array $data, float $payableTotal): array
    {
        $percent = null;
        if (array_key_exists('tip_percent', $data) && $data['tip_percent'] !== null && $data['tip_percent'] !== '') {
            $percent = round((float) $data['tip_percent'], 2);
            if ($percent < 0 || $percent > 100) {
                throw new Exception('El porcentaje de propina debe estar entre 0 y 100.');
            }
        }

        $amount = 0.0;
        $hasAmount = array_key_exists('tip_amount', $data)
            && $data['tip_amount'] !== null
            && $data['tip_amount'] !== '';
        if ($hasAmount) {
            $amount = round((float) $data['tip_amount'], 2);
        }
        if ($amount <= 0 && $percent !== null && $percent > 0) {
            $amount = round($payableTotal * ($percent / 100), 2);
        }

        if ($amount < 0) {
            throw new Exception('La propina no puede ser negativa.');
        }

        if ($amount <= 0) {
            return [0.0, null];
        }

        if ($percent === null && $payableTotal > 0) {
            $percent = round(($amount / $payableTotal) * 100, 2);
        }

        return [round($amount, 2), $percent];
    }

    public function cancel($user, int $saleId, ?string $reason = null): Sale
    {
        $sale = DB::transaction(function () use ($user, $saleId, $reason) {
            $sale = Sale::with(['lines.lots', 'lines.product', 'lines.presentation', 'payments', 'customer', 'place'])
                ->lockForUpdate()
                ->findOrFail($saleId);
            PlaceScope::assert($user, (int) $sale->place_id, 'sales.allPlaces');

            if ($sale->status === Sale::STATUS_CANCELLED) {
                throw new Exception('La venta ya está anulada.');
            }

            $hasReturns = $sale->lines->contains(fn ($line) => (float) ($line->qty_returned ?? 0) > 0)
                || (float) ($sale->returned_total ?? 0) > 0;
            if ($hasReturns) {
                throw new Exception('Esta venta ya tiene devoluciones. Use devolución parcial para el resto.');
            }

            foreach ($sale->lines as $line) {
                foreach ($line->lots as $saleLineLot) {
                    $lot = StockLot::lockForUpdate()->find($saleLineLot->stock_lot_id);
                    if (!$lot) {
                        continue;
                    }
                    $restorable = round(
                        (float) $saleLineLot->qty_base - (float) ($saleLineLot->qty_base_returned ?? 0),
                        4
                    );
                    if ($restorable <= 0) {
                        continue;
                    }
                    $lot->qty_remaining = round(((float) $lot->qty_remaining) + $restorable, 4);
                    $lot->save();
                }
            }

            $sale->update([
                'status' => Sale::STATUS_CANCELLED,
                'cancelled_at' => Carbon::now(),
                'cancelled_by' => $user->id,
                'cancel_reason' => $reason ? trim($reason) : null,
            ]);

            return $sale->fresh([
                'lines.lots',
                'lines.presentation',
                'lines.product',
                'payments',
                'station',
                'user',
                'customer',
                'place',
            ]);
        });

        $sale = $this->factusCreditNote->emitForCancel($sale, $reason);

        return $sale->fresh([
            'lines.lots',
            'lines.presentation',
            'lines.product',
            'payments',
            'station',
            'user',
            'customer',
        ]);
    }

    protected function resolveCustomerId(int $placeId, $customerId): ?int
    {
        if ($customerId === null || $customerId === '' || $customerId === false) {
            return null;
        }

        $customer = Customer::findOrFail((int) $customerId);
        if ((int) $customer->place_id !== (int) $placeId) {
            throw new Exception('El cliente no pertenece a esta sede.');
        }
        if ($customer->status !== 'activo') {
            throw new Exception('El cliente no está activo.');
        }

        return (int) $customer->id;
    }

    /**
     * @return array{0:string,1:array<int,array{payment_method:string,amount:float,sort_order:int}>,2:float,3:float}
     */
    protected function resolvePayments(array $data, float $total): array
    {
        $total = round($total, 2);
        $rawPayments = $data['payments'] ?? null;
        $normalized = [];

        if (is_array($rawPayments) && count($rawPayments) > 0) {
            foreach ($rawPayments as $row) {
                $method = $row['payment_method'] ?? null;
                $amount = round((float) ($row['amount'] ?? 0), 2);
                if ($amount <= 0) {
                    continue;
                }
                if (!in_array($method, Sale::PAYMENT_METHODS, true)) {
                    throw new Exception('Método de pago inválido.');
                }
                $normalized[$method] = round(($normalized[$method] ?? 0) + $amount, 2);
            }
        } else {
            $method = $data['payment_method'] ?? Sale::PAYMENT_CASH;
            if (!in_array($method, Sale::PAYMENT_METHODS, true)) {
                throw new Exception('Método de pago inválido.');
            }
            $normalized[$method] = $total;
        }

        if (!$normalized) {
            throw new Exception('La venta necesita al menos un pago.');
        }

        $payments = [];
        $sum = 0.0;
        $order = 1;
        foreach (Sale::PAYMENT_METHODS as $method) {
            if (!isset($normalized[$method])) {
                continue;
            }
            $amount = round((float) $normalized[$method], 2);
            $payments[] = [
                'payment_method' => $method,
                'amount' => $amount,
                'sort_order' => $order++,
            ];
            $sum = round($sum + $amount, 2);
        }

        if (abs($sum - $total) > 0.01) {
            throw new Exception('La suma de los pagos debe ser igual al total de la venta.');
        }

        $cashPortion = round((float) ($normalized[Sale::PAYMENT_CASH] ?? 0), 2);
        $hasAmountReceived = array_key_exists('amount_received', $data)
            && $data['amount_received'] !== null
            && $data['amount_received'] !== '';

        if ($cashPortion > 0) {
            $received = $hasAmountReceived ? round((float) $data['amount_received'], 2) : $cashPortion;
            if ($received + 0.0001 < $cashPortion) {
                throw new Exception('El efectivo recibido es menor a la parte pagada en efectivo.');
            }

            $change = round($received - $cashPortion, 2);
        } else {
            $received = $total;
            $change = 0.0;
        }

        $paymentMethod = count($payments) === 1
            ? $payments[0]['payment_method']
            : Sale::PAYMENT_MIXED;

        return [$paymentMethod, $payments, $received, $change];
    }

    protected function consumeStockForLine(
        int $placeId,
        Product $product,
        ProductPresentation $presentation,
        float $qty,
        float $qtyBase,
        int $saleLineId
    ): float {
        if ($product->usesRecipeInventory()) {
            $product->loadMissing('recipeItems.ingredient');
            $items = $product->recipeItems;
            if ($items->isEmpty()) {
                throw new Exception("{$product->name} está en modo receta pero no tiene ingredientes.");
            }

            $cost = 0.0;
            foreach ($items as $item) {
                $ingredient = $item->ingredient;
                if (!$ingredient) {
                    throw new Exception("Ingrediente faltante en la receta de {$product->name}.");
                }

                $need = round($qty * (float) $item->qty_base, 4);
                $cost += $this->consumeFifo(
                    $placeId,
                    (int) $ingredient->id,
                    $need,
                    $saleLineId,
                    $ingredient->name,
                    $product->name
                );
            }

            return round($cost, 4);
        }

        return $this->consumeFifo(
            $placeId,
            (int) $product->id,
            $qtyBase,
            $saleLineId,
            $product->name,
            $presentation->name
        );
    }

    protected function consumeFifo(
        int $placeId,
        int $productId,
        float $qtyBase,
        int $saleLineId,
        string $productName,
        string $presentationName
    ): float {
        $remaining = $qtyBase;
        $cost = 0;

        $lots = StockLot::where('place_id', $placeId)
            ->where('product_id', $productId)
            ->where('qty_remaining', '>', 0)
            ->orderBy('purchased_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($lots as $lot) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, (float) $lot->qty_remaining);
            $lot->qty_remaining = round(((float) $lot->qty_remaining) - $take, 4);
            $lot->save();

            $saleLineLot = new \App\Models\SaleLineLot([
                'sale_line_id' => $saleLineId,
                'stock_lot_id' => $lot->id,
                'qty_base' => $take,
                'unit_cost' => (float) $lot->unit_cost,
            ]);
            $saleLineLot->save();

            $cost += $take * (float) $lot->unit_cost;
            $remaining = round($remaining - $take, 4);
        }

        if ($remaining > 0.0001) {
            throw new Exception(
                "Stock insuficiente de {$productName} ({$presentationName}). Faltan {$remaining} unidades base."
            );
        }

        return round($cost, 4);
    }

    protected function requireOpenSession(Station $station, $user): RegisterSession
    {
        $open = RegisterSession::where('station_id', $station->id)
            ->where('status', RegisterSession::STATUS_OPEN)
            ->lockForUpdate()
            ->latest('id')
            ->first();

        if (!$open) {
            throw new Exception('Abra la caja antes de vender.');
        }

        return $open;
    }
}
