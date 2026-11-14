<?php

namespace App\Services;

use App\Models\ProductPresentation;
use App\Models\RegisterSession;
use App\Models\Sale;
use App\Models\Station;
use App\Models\StockLot;
use App\Support\PlaceScope;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class SaleService
{
    public function catalogForUser($user)
    {
        $station = $this->requireStation($user);

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

        return Sale::with(['lines.lots', 'station', 'user'])
            ->where('place_id', $placeId)
            ->whereBetween('sold_at', [$from, $to])
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->limit(1000)
            ->get();
    }

    public function find(int $id): Sale
    {
        return Sale::with(['lines.lots', 'lines.presentation', 'lines.product', 'station', 'user'])
            ->findOrFail($id);
    }

    public function create($user, array $data): Sale
    {
        $station = $this->requireStation($user);
        $session = $this->requireOpenSession($station, $user);
        $lines = $data['lines'] ?? [];

        if (count($lines) === 0) {
            throw new Exception('La venta necesita al menos un producto.');
        }

        $paymentMethod = $data['payment_method'] ?? Sale::PAYMENT_CASH;
        if (!in_array($paymentMethod, Sale::PAYMENT_METHODS, true)) {
            throw new Exception('Método de pago inválido.');
        }

        return DB::transaction(function () use ($station, $user, $session, $lines, $paymentMethod, $data) {
            $sale = Sale::create([
                'place_id' => $station->place_id,
                'station_id' => $station->id,
                'user_id' => $user->id,
                'register_session_id' => $session->id,
                'sold_at' => Carbon::now(),
                'subtotal' => 0,
                'total' => 0,
                'cost_total' => 0,
                'status' => Sale::STATUS_COMPLETED,
                'payment_method' => $paymentMethod,
            ]);

            $subtotal = 0;
            $costTotal = 0;

            foreach ($lines as $row) {
                $presentation = ProductPresentation::with('product')->findOrFail($row['product_presentation_id']);
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
                $unitPrice = (float) $presentation->sale_price;
                $lineTotal = round($qty * $unitPrice, 2);

                $saleLine = $sale->lines()->create([
                    'product_id' => $product->id,
                    'product_presentation_id' => $presentation->id,
                    'qty' => $qty,
                    'factor' => $factor,
                    'qty_base' => $qtyBase,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'cost_total' => 0,
                ]);

                $lineCost = $this->consumeFifo(
                    (int) $station->place_id,
                    (int) $product->id,
                    $qtyBase,
                    $saleLine->id,
                    $product->name,
                    $presentation->name
                );

                $saleLine->update(['cost_total' => $lineCost]);
                $subtotal += $lineTotal;
                $costTotal += $lineCost;
            }

            $total = round($subtotal, 2);
            [$amountReceived, $changeAmount] = $this->resolvePaymentAmounts(
                $paymentMethod,
                $total,
                array_key_exists('amount_received', $data) ? (float) $data['amount_received'] : null
            );

            $sale->update([
                'subtotal' => $total,
                'total' => $total,
                'cost_total' => round($costTotal, 4),
                'amount_received' => $amountReceived,
                'change_amount' => $changeAmount,
            ]);

            return $sale->fresh(['lines.lots', 'lines.presentation', 'lines.product', 'station', 'user']);
        });
    }

    public function cancel($user, int $saleId, ?string $reason = null): Sale
    {
        return DB::transaction(function () use ($user, $saleId, $reason) {
            $sale = Sale::with(['lines.lots'])->lockForUpdate()->findOrFail($saleId);
            PlaceScope::assert($user, (int) $sale->place_id, 'sales.allPlaces');

            if ($sale->status === Sale::STATUS_CANCELLED) {
                throw new Exception('La venta ya está anulada.');
            }

            foreach ($sale->lines as $line) {
                foreach ($line->lots as $saleLineLot) {
                    $lot = StockLot::lockForUpdate()->find($saleLineLot->stock_lot_id);
                    if (!$lot) {
                        continue;
                    }
                    $lot->qty_remaining = round(((float) $lot->qty_remaining) + (float) $saleLineLot->qty_base, 4);
                    $lot->save();
                }
            }

            $sale->update([
                'status' => Sale::STATUS_CANCELLED,
                'cancelled_at' => Carbon::now(),
                'cancelled_by' => $user->id,
                'cancel_reason' => $reason ? trim($reason) : null,
            ]);

            return $sale->fresh(['lines.lots', 'lines.presentation', 'lines.product', 'station', 'user']);
        });
    }

    protected function resolvePaymentAmounts(string $method, float $total, ?float $amountReceived): array
    {
        if ($method === Sale::PAYMENT_CASH) {
            $received = $amountReceived ?? $total;
            if ($received + 0.0001 < $total) {
                throw new Exception('El monto recibido es menor al total de la venta.');
            }

            return [round($received, 2), round($received - $total, 2)];
        }

        return [round($total, 2), 0.0];
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

    protected function requireStation($user): Station
    {
        $user->loadMissing('station');
        if (!$user->station) {
            throw new Exception('El usuario no tiene una caja asignada.');
        }

        return $user->station;
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
