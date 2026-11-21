<?php

namespace App\Services;

use App\Models\RegisterSession;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SaleReturn;
use App\Models\SaleReturnLine;
use App\Models\SaleReturnLineLot;
use App\Models\SaleReturnPayment;
use App\Models\Station;
use App\Models\StockLot;
use App\Support\PlaceScope;
use App\Support\PosStation;
use App\Support\SalePricing;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class SaleReturnService
{
    public function create($user, int $saleId, array $data): Sale
    {
        $station = PosStation::resolve($user, isset($data['station_id']) ? (int) $data['station_id'] : null);

        return DB::transaction(function () use ($user, $saleId, $data, $station) {
            $sale = Sale::with(['lines.lots', 'payments', 'returns.payments'])
                ->lockForUpdate()
                ->findOrFail($saleId);

            PlaceScope::assert($user, (int) $sale->place_id, 'sales.allPlaces');

            if ($sale->status === Sale::STATUS_CANCELLED) {
                throw new Exception('No se puede devolver una venta anulada.');
            }

            if ((int) $station->place_id !== (int) $sale->place_id) {
                throw new Exception('La caja no pertenece a la sede de la venta.');
            }

            $session = $this->requireOpenSession($station, $user);
            $requestLines = $data['lines'] ?? [];
            if (!is_array($requestLines) || count($requestLines) === 0) {
                throw new Exception('Seleccione al menos un ítem para devolver.');
            }

            $saleLines = $sale->lines->keyBy('id');
            $prepared = [];
            $subtotal = 0.0;
            $discountTotal = 0.0;
            $taxBase = 0.0;
            $taxTotal = 0.0;
            $payableTotal = 0.0;

            foreach ($requestLines as $row) {
                $saleLineId = (int) ($row['sale_line_id'] ?? 0);
                $qtyReturn = round((float) ($row['qty'] ?? 0), 4);
                /** @var SaleLine|null $saleLine */
                $saleLine = $saleLines->get($saleLineId);

                if (!$saleLine) {
                    throw new Exception('Una de las líneas no pertenece a esta venta.');
                }
                if ($qtyReturn <= 0) {
                    throw new Exception('La cantidad a devolver debe ser mayor que 0.');
                }

                $alreadyReturned = round((float) ($saleLine->qty_returned ?? 0), 4);
                $remaining = round((float) $saleLine->qty - $alreadyReturned, 4);
                if ($qtyReturn - $remaining > 0.0001) {
                    throw new Exception("No se puede devolver más de lo vendido en {$saleLine->product?->name}.");
                }

                $ratio = ((float) $saleLine->qty) > 0 ? ($qtyReturn / (float) $saleLine->qty) : 0;
                $lineDiscount = round((float) $saleLine->discount_amount * $ratio, 2);
                $lineGross = round($qtyReturn * (float) $saleLine->unit_price, 2);
                $linePayable = round(max(0, $lineGross - $lineDiscount), 2);
                $split = SalePricing::splitGross($linePayable, (float) $saleLine->tax_rate);
                $qtyBase = round($qtyReturn * (float) $saleLine->factor, 4);

                $prepared[] = [
                    'sale_line' => $saleLine,
                    'qty' => $qtyReturn,
                    'qty_base' => $qtyBase,
                    'line_discount' => $lineDiscount,
                    'line_gross' => $lineGross,
                    'line_payable' => $linePayable,
                    'line_base' => $split['base'],
                    'tax_amount' => $split['tax'],
                ];

                $subtotal = round($subtotal + $lineGross, 2);
                $discountTotal = round($discountTotal + $lineDiscount, 2);
                $taxBase = round($taxBase + $split['base'], 2);
                $taxTotal = round($taxTotal + $split['tax'], 2);
                $payableTotal = round($payableTotal + $linePayable, 2);
            }

            $refundPayments = $this->resolveRefundPayments($sale, $payableTotal, $data['payments'] ?? null);

            $saleReturn = SaleReturn::create([
                'sale_id' => $sale->id,
                'place_id' => $sale->place_id,
                'station_id' => $station->id,
                'user_id' => $user->id,
                'register_session_id' => $session->id,
                'returned_at' => Carbon::now(),
                'reason' => isset($data['reason']) ? trim((string) $data['reason']) : null,
                'subtotal' => $subtotal,
                'discount_amount' => $discountTotal,
                'tax_base' => $taxBase,
                'tax_total' => $taxTotal,
                'total' => $payableTotal,
                'cost_total' => 0,
            ]);

            $costTotal = 0.0;

            foreach ($prepared as $row) {
                /** @var SaleLine $saleLine */
                $saleLine = $row['sale_line'];

                $returnLine = SaleReturnLine::create([
                    'sale_return_id' => $saleReturn->id,
                    'sale_line_id' => $saleLine->id,
                    'qty' => $row['qty'],
                    'factor' => $saleLine->factor,
                    'qty_base' => $row['qty_base'],
                    'unit_price' => $saleLine->unit_price,
                    'tax_id' => $saleLine->tax_id,
                    'tax_rate' => $saleLine->tax_rate,
                    'tax_name' => $saleLine->tax_name,
                    'tax_code' => $saleLine->tax_code,
                    'discount_amount' => $row['line_discount'],
                    'line_base' => $row['line_base'],
                    'tax_amount' => $row['tax_amount'],
                    'line_total' => $row['line_payable'],
                    'cost_total' => 0,
                ]);

                $lineCost = $this->restoreStockLifo($saleLine, $row['qty_base'], $returnLine);
                $returnLine->update(['cost_total' => $lineCost]);
                $costTotal = round($costTotal + $lineCost, 4);

                $saleLine->qty_returned = round((float) ($saleLine->qty_returned ?? 0) + $row['qty'], 4);
                $saleLine->save();
            }

            $saleReturn->update(['cost_total' => $costTotal]);

            $order = 1;
            foreach ($refundPayments as $payment) {
                SaleReturnPayment::create([
                    'sale_return_id' => $saleReturn->id,
                    'payment_method' => $payment['payment_method'],
                    'amount' => $payment['amount'],
                    'sort_order' => $order++,
                ]);
            }

            $sale->returned_total = round((float) ($sale->returned_total ?? 0) + $payableTotal, 2);
            $sale->returned_cost_total = round((float) ($sale->returned_cost_total ?? 0) + $costTotal, 4);
            $sale->status = Sale::STATUS_PARTIALLY_RETURNED;
            $sale->save();

            return $sale->fresh([
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
        });
    }

    protected function restoreStockLifo(SaleLine $saleLine, float $qtyBaseReturn, SaleReturnLine $returnLine): float
    {
        $remaining = round($qtyBaseReturn, 4);
        $costTotal = 0.0;

        $consumption = $saleLine->lots()
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get();

        foreach ($consumption as $saleLineLot) {
            if ($remaining <= 0) {
                break;
            }

            $available = round((float) $saleLineLot->qty_base - (float) ($saleLineLot->qty_base_returned ?? 0), 4);
            if ($available <= 0) {
                continue;
            }

            $take = round(min($remaining, $available), 4);
            $lot = StockLot::lockForUpdate()->find($saleLineLot->stock_lot_id);
            if ($lot) {
                $lot->qty_remaining = round((float) $lot->qty_remaining + $take, 4);
                $lot->save();
            }

            $saleLineLot->qty_base_returned = round((float) ($saleLineLot->qty_base_returned ?? 0) + $take, 4);
            $saleLineLot->save();

            $unitCost = (float) $saleLineLot->unit_cost;
            SaleReturnLineLot::create([
                'sale_return_line_id' => $returnLine->id,
                'sale_line_lot_id' => $saleLineLot->id,
                'stock_lot_id' => $saleLineLot->stock_lot_id,
                'qty_base' => $take,
                'unit_cost' => $unitCost,
            ]);

            $costTotal = round($costTotal + ($take * $unitCost), 4);
            $remaining = round($remaining - $take, 4);
        }

        if ($remaining > 0.0001) {
            throw new Exception('No se pudo restaurar el stock completo de la devolución.');
        }

        return $costTotal;
    }

    /**
     * @return array<int, array{payment_method:string,amount:float}>
     */
    protected function resolveRefundPayments(Sale $sale, float $refundTotal, $rawPayments): array
    {
        $refundTotal = round($refundTotal, 2);
        if ($refundTotal <= 0) {
            throw new Exception('El monto a devolver debe ser mayor que 0.');
        }

        $paidByMethod = [];
        foreach ($sale->payments as $payment) {
            $method = $payment->payment_method;
            $paidByMethod[$method] = round(($paidByMethod[$method] ?? 0) + (float) $payment->amount, 2);
        }

        if (!$paidByMethod) {
            $method = in_array($sale->payment_method, Sale::PAYMENT_METHODS, true)
                ? $sale->payment_method
                : Sale::PAYMENT_CASH;
            $paidByMethod[$method] = round((float) $sale->total, 2);
        }

        $alreadyRefunded = [];
        foreach ($sale->returns as $previous) {
            foreach ($previous->payments as $payment) {
                $method = $payment->payment_method;
                $alreadyRefunded[$method] = round(($alreadyRefunded[$method] ?? 0) + (float) $payment->amount, 2);
            }
        }

        $refundable = [];
        foreach ($paidByMethod as $method => $paid) {
            $refundable[$method] = round(max(0, $paid - ($alreadyRefunded[$method] ?? 0)), 2);
        }

        $normalized = [];
        if (is_array($rawPayments) && count($rawPayments) > 0) {
            foreach ($rawPayments as $row) {
                $method = $row['payment_method'] ?? null;
                $amount = round((float) ($row['amount'] ?? 0), 2);
                if ($amount <= 0) {
                    continue;
                }
                if (!in_array($method, Sale::PAYMENT_METHODS, true)) {
                    throw new Exception('Método de reembolso inválido.');
                }
                $normalized[$method] = round(($normalized[$method] ?? 0) + $amount, 2);
            }
        } else {
            // Default: allocate refund across original tenders (cash → card → transfer).
            $remaining = $refundTotal;
            foreach (Sale::PAYMENT_METHODS as $method) {
                $cap = $refundable[$method] ?? 0;
                if ($cap <= 0 || $remaining <= 0) {
                    continue;
                }
                $share = round(min($cap, $remaining), 2);
                if ($share > 0) {
                    $normalized[$method] = $share;
                    $remaining = round($remaining - $share, 2);
                }
            }
            if ($remaining > 0.01) {
                throw new Exception('No hay saldo suficiente en los medios de pago originales para reembolsar.');
            }
        }

        if (!$normalized) {
            throw new Exception('Defina cómo se reembolsa la devolución.');
        }

        $sum = 0.0;
        $payments = [];
        foreach (Sale::PAYMENT_METHODS as $method) {
            if (!isset($normalized[$method])) {
                continue;
            }
            $amount = round($normalized[$method], 2);
            $cap = $refundable[$method] ?? 0;
            if ($amount - $cap > 0.01) {
                throw new Exception("El reembolso en {$method} supera lo pagado originalmente.");
            }
            $payments[] = [
                'payment_method' => $method,
                'amount' => $amount,
            ];
            $sum = round($sum + $amount, 2);
        }

        if (abs($sum - $refundTotal) > 0.01) {
            throw new Exception('La suma del reembolso debe ser igual al total de la devolución.');
        }

        return $payments;
    }

    protected function requireOpenSession(Station $station, $user): RegisterSession
    {
        $open = RegisterSession::where('station_id', $station->id)
            ->where('status', RegisterSession::STATUS_OPEN)
            ->lockForUpdate()
            ->latest('id')
            ->first();

        if (!$open) {
            throw new Exception('Abra la caja antes de registrar una devolución.');
        }

        return $open;
    }
}
