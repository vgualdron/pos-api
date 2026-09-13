<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\Purchase;
use App\Models\StockLot;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    public function allByPlace(int $placeId)
    {
        return Purchase::with(['lines.product', 'lines.presentation'])
            ->where('place_id', $placeId)
            ->orderByDesc('purchased_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();
    }

    public function create($user, array $data): Purchase
    {
        $lines = $data['lines'] ?? [];
        if (count($lines) === 0) {
            throw new Exception('La compra necesita al menos una línea.');
        }

        return DB::transaction(function () use ($user, $data, $lines) {
            $placeId = (int) $data['place_id'];
            $purchasedAt = !empty($data['purchased_at'])
                ? Carbon::parse($data['purchased_at'])
                : Carbon::now();

            $purchase = Purchase::create([
                'place_id' => $placeId,
                'station_id' => $user->station_id,
                'user_id' => $user->id,
                'purchased_at' => $purchasedAt,
                'supplier_name' => $data['supplier_name'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'completed',
            ]);

            foreach ($lines as $row) {
                $presentation = ProductPresentation::findOrFail($row['product_presentation_id']);
                $product = Product::findOrFail($presentation->product_id);

                if ((int) $product->place_id !== $placeId) {
                    throw new Exception("El producto {$product->name} no pertenece a esta sede.");
                }

                $qtyPresentations = (float) ($row['qty'] ?? 0);
                if ($qtyPresentations <= 0) {
                    throw new Exception('La cantidad comprada debe ser mayor que 0.');
                }

                $factor = (float) $presentation->factor;
                $qtyBase = round($qtyPresentations * $factor, 4);
                $unitCost = (float) ($row['unit_cost'] ?? 0);
                if ($unitCost < 0) {
                    throw new Exception('El costo unitario no puede ser negativo.');
                }

                $lot = StockLot::create([
                    'place_id' => $placeId,
                    'product_id' => $product->id,
                    'purchase_id' => $purchase->id,
                    'lot_code' => $row['lot_code'] ?? null,
                    'purchased_at' => $purchasedAt,
                    'expires_at' => $row['expires_at'] ?? null,
                    'unit_cost' => $unitCost,
                    'qty_initial' => $qtyBase,
                    'qty_remaining' => $qtyBase,
                ]);

                $purchase->lines()->create([
                    'product_id' => $product->id,
                    'product_presentation_id' => $presentation->id,
                    'stock_lot_id' => $lot->id,
                    'qty_presentations' => $qtyPresentations,
                    'factor' => $factor,
                    'qty_base' => $qtyBase,
                    'unit_cost' => $unitCost,
                ]);
            }

            return $purchase->fresh(['lines.product', 'lines.presentation', 'lines.stockLot']);
        });
    }
}
