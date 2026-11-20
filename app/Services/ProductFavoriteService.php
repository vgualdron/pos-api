<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductFavorite;
use Exception;
use Illuminate\Support\Facades\DB;

class ProductFavoriteService
{
    public function byPlace(int $placeId)
    {
        return ProductFavorite::query()
            ->with(['product' => fn ($q) => $q->withSum('lots', 'qty_remaining')])
            ->where('place_id', $placeId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function favoriteProductIds(int $placeId): array
    {
        return ProductFavorite::query()
            ->where('place_id', $placeId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function toggle(int $placeId, int $productId): array
    {
        $product = Product::findOrFail($productId);
        if ((int) $product->place_id !== $placeId) {
            throw new Exception('El producto no pertenece a esta sede.');
        }

        $existing = ProductFavorite::query()
            ->where('place_id', $placeId)
            ->where('product_id', $productId)
            ->first();

        if ($existing) {
            $existing->delete();
            return [
                'is_favorite' => false,
                'product_ids' => $this->favoriteProductIds($placeId),
            ];
        }

        $maxSort = (int) ProductFavorite::query()->where('place_id', $placeId)->max('sort_order');
        ProductFavorite::create([
            'place_id' => $placeId,
            'product_id' => $productId,
            'sort_order' => $maxSort + 1,
        ]);

        return [
            'is_favorite' => true,
            'product_ids' => $this->favoriteProductIds($placeId),
        ];
    }

    public function sync(int $placeId, array $productIds): array
    {
        return DB::transaction(function () use ($placeId, $productIds) {
            $ids = array_values(array_unique(array_map('intval', $productIds)));
            $validIds = Product::query()
                ->where('place_id', $placeId)
                ->whereIn('id', $ids)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            ProductFavorite::query()->where('place_id', $placeId)->delete();

            foreach ($validIds as $index => $productId) {
                ProductFavorite::create([
                    'place_id' => $placeId,
                    'product_id' => $productId,
                    'sort_order' => $index + 1,
                ]);
            }

            return $this->favoriteProductIds($placeId);
        });
    }
}
