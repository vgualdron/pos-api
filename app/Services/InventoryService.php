<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockLot;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function lotsForPlace(int $placeId, ?string $filter = null, ?string $search = null)
    {
        $today = Carbon::today();
        $soon = Carbon::today()->addDays(30);

        $query = StockLot::with(['product'])
            ->where('place_id', $placeId)
            ->where('qty_remaining', '>', 0);

        if ($filter === 'expired') {
            $query->whereNotNull('expires_at')->whereDate('expires_at', '<', $today);
        } elseif ($filter === 'expiring') {
            $query->whereNotNull('expires_at')
                ->whereDate('expires_at', '>=', $today)
                ->whereDate('expires_at', '<=', $soon);
        } elseif ($filter === 'low') {
            $query->whereHas('product', function ($q) {
                $q->whereColumn('stock_lots.qty_remaining', '>', 0);
            });
        }

        if ($search) {
            $term = '%' . trim($search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('lot_code', 'like', $term)
                    ->orWhereHas('product', function ($pq) use ($term) {
                        $pq->where('name', 'like', $term)
                            ->orWhere('sku', 'like', $term)
                            ->orWhere('barcode', 'like', $term);
                    });
            });
        }

        $lots = $query->orderBy('expires_at')
            ->orderBy('purchased_at')
            ->orderBy('id')
            ->limit(1000)
            ->get();

        if ($filter === 'low') {
            $productStock = Product::withSum('lots', 'qty_remaining')
                ->where('place_id', $placeId)
                ->get()
                ->filter(function (Product $product) {
                    $stock = (float) ($product->lots_sum_qty_remaining ?? $product->stock_on_hand);
                    $min = (float) ($product->min_stock ?? 0);

                    return $min > 0 && $stock <= $min;
                })
                ->pluck('id')
                ->all();

            $lots = $lots->filter(fn (StockLot $lot) => in_array((int) $lot->product_id, $productStock, true))->values();
        }

        return $lots->map(function (StockLot $lot) use ($today, $soon) {
            $expiresAt = $lot->expires_at ? Carbon::parse($lot->expires_at)->startOfDay() : null;
            $status = 'ok';
            if ($expiresAt && $expiresAt->lt($today)) {
                $status = 'expired';
            } elseif ($expiresAt && $expiresAt->lte($soon)) {
                $status = 'expiring';
            }

            $lot->setAttribute('expiry_status', $status);

            return $lot;
        });
    }

    public function productsForPlace(int $placeId)
    {
        return Product::withSum('lots', 'qty_remaining')
            ->where('place_id', $placeId)
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) {
                $stock = (float) ($product->lots_sum_qty_remaining ?? 0);
                $min = (float) ($product->min_stock ?? 0);
                $product->setAttribute('stock_on_hand', $stock);
                $product->setAttribute('is_low_stock', $min > 0 && $stock <= $min);

                return $product;
            });
    }

    public function summaryForPlace(int $placeId): array
    {
        $today = Carbon::today();
        $soon = Carbon::today()->addDays(30);

        $products = $this->productsForPlace($placeId);
        $lowStock = $products->filter(fn (Product $p) => (bool) $p->is_low_stock)->values();

        $lots = StockLot::with('product')
            ->where('place_id', $placeId)
            ->where('qty_remaining', '>', 0)
            ->whereNotNull('expires_at')
            ->get();

        $expired = $lots->filter(fn (StockLot $lot) => Carbon::parse($lot->expires_at)->lt($today))->values();
        $expiring = $lots->filter(function (StockLot $lot) use ($today, $soon) {
            $expires = Carbon::parse($lot->expires_at);

            return $expires->gte($today) && $expires->lte($soon);
        })->values();

        return [
            'low_stock_count' => $lowStock->count(),
            'expiring_count' => $expiring->count(),
            'expired_count' => $expired->count(),
            'low_stock' => $lowStock->take(20)->values(),
            'expiring' => $expiring->take(20)->values(),
            'expired' => $expired->take(20)->values(),
        ];
    }

    public function adjust($user, array $data): StockLot
    {
        return DB::transaction(function () use ($user, $data) {
            $lot = StockLot::with('product')->lockForUpdate()->findOrFail((int) $data['stock_lot_id']);
            $type = $data['type'];
            $qtyInput = round((float) $data['qty_base'], 4);

            if (!in_array($type, StockAdjustment::TYPES, true)) {
                throw new Exception('Tipo de ajuste inválido.');
            }
            if ($qtyInput < 0) {
                throw new Exception('La cantidad no puede ser negativa.');
            }

            $before = round((float) $lot->qty_remaining, 4);

            if ($type === StockAdjustment::TYPE_RECOUNT) {
                $after = $qtyInput;
                $delta = round($after - $before, 4);
            } elseif ($type === StockAdjustment::TYPE_ADJUSTMENT_IN) {
                if ($qtyInput <= 0) {
                    throw new Exception('Indique la cantidad a ingresar.');
                }
                $delta = $qtyInput;
                $after = round($before + $delta, 4);
            } else {
                // adjustment_out | shrinkage
                if ($qtyInput <= 0) {
                    throw new Exception('Indique la cantidad a descontar.');
                }
                if ($qtyInput > $before + 0.0001) {
                    throw new Exception('No hay suficiente stock en el lote.');
                }
                $delta = -1 * $qtyInput;
                $after = round($before + $delta, 4);
            }

            if ($after < 0) {
                throw new Exception('El stock resultante no puede ser negativo.');
            }

            $lot->qty_remaining = $after;
            if (array_key_exists('expires_at', $data)) {
                $lot->expires_at = $data['expires_at'] ?: null;
            }
            $lot->save();

            StockAdjustment::create([
                'place_id' => $lot->place_id,
                'product_id' => $lot->product_id,
                'stock_lot_id' => $lot->id,
                'user_id' => $user->id,
                'type' => $type,
                'qty_before' => $before,
                'qty_delta' => $delta,
                'qty_after' => $after,
                'notes' => isset($data['notes']) ? trim((string) $data['notes']) : null,
            ]);

            return $lot->fresh(['product']);
        });
    }

    public function updateLotExpiry($user, int $lotId, ?string $expiresAt): StockLot
    {
        $lot = StockLot::findOrFail($lotId);
        $lot->expires_at = $expiresAt ?: null;
        $lot->save();

        return $lot->fresh(['product']);
    }
}
