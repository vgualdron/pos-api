<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\Purchase;
use App\Models\StockLot;
use App\Models\Supplier;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    public function allByPlace(
        int $placeId,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?int $supplierId = null,
        ?string $search = null
    ) {
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

        $query = Purchase::with(['lines.product', 'lines.presentation', 'lines.stockLot', 'supplier', 'user'])
            ->where('place_id', $placeId)
            ->whereBetween('purchased_at', [$from, $to]);

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        if ($search) {
            $term = trim($search);
            if ($term !== '') {
                $query->where(function ($inner) use ($term) {
                    $like = '%' . $term . '%';
                    $inner->where('supplier_name', 'like', $like)
                        ->orWhere('notes', 'like', $like)
                        ->orWhereHas('supplier', function ($supplier) use ($like) {
                            $supplier->where('name', 'like', $like);
                        })
                        ->orWhereHas('lines.product', function ($product) use ($like) {
                            $product->where('name', 'like', $like);
                        });
                });
            }
        }

        return $query
            ->orderByDesc('purchased_at')
            ->orderByDesc('id')
            ->limit(1000)
            ->get();
    }

    public function find(int $id): Purchase
    {
        return Purchase::with(['lines.product', 'lines.presentation', 'lines.stockLot', 'supplier', 'user'])
            ->findOrFail($id);
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

            $supplierId = isset($data['supplier_id']) ? (int) $data['supplier_id'] : null;
            $supplierName = $data['supplier_name'] ?? null;

            if ($supplierId) {
                $supplier = Supplier::findOrFail($supplierId);
                if ((int) $supplier->place_id !== $placeId) {
                    throw new Exception('El proveedor no pertenece a esta sede.');
                }
                if ($supplier->status !== 'activo') {
                    throw new Exception('El proveedor está inactivo.');
                }
                $supplierName = $supplier->name;
            }

            $purchase = Purchase::create([
                'place_id' => $placeId,
                'supplier_id' => $supplierId,
                'station_id' => $user->station_id,
                'user_id' => $user->id,
                'purchased_at' => $purchasedAt,
                'supplier_name' => $supplierName,
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

            return $purchase->fresh(['lines.product', 'lines.presentation', 'lines.stockLot', 'supplier', 'user']);
        });
    }
}
