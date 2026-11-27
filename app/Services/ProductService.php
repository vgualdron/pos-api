<?php

namespace App\Services;

use App\Models\File;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPresentation;
use App\Models\ProductRecipeItem;
use App\Models\Station;
use App\Models\StockLot;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public const IMAGE_NAME = 'PHOTO_PRODUCT';

    public function all()
    {
        return $this->attachImageUrls(
            $this->applyRecipeStock(
                Product::withSum('lots', 'qty_remaining')
                    ->orderBy('place_id')
                    ->orderBy('name')
                    ->get()
            )
        );
    }

    public function find($id): Product
    {
        $product = Product::withSum('lots', 'qty_remaining')->findOrFail($id);

        return $this->attachImageUrls(
            $this->applyRecipeStock(collect([$product]))
        )->first();
    }

    public function findByPlace($placeId, bool $onlyActive = true)
    {
        $query = Product::withSum('lots', 'qty_remaining')
            ->where('place_id', $placeId)
            ->orderBy('name');

        if ($onlyActive) {
            $query->where('status', 'activo');
        }

        return $this->attachImageUrls($this->applyRecipeStock($query->get()));
    }

    public function catalog(int $placeId)
    {
        $products = $this->findByPlace($placeId, true);
        $favoriteIds = app(ProductFavoriteService::class)->favoriteProductIds($placeId);
        $favoriteIndex = array_flip($favoriteIds);

        return $products->map(function (Product $product) use ($favoriteIndex) {
            $isFavorite = array_key_exists($product->id, $favoriteIndex);
            $product->setAttribute('is_favorite', $isFavorite);
            $product->setAttribute(
                'favorite_sort',
                $isFavorite ? ((int) $favoriteIndex[$product->id] + 1) : null
            );

            return $product;
        });
    }

    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $presentations = $data['presentations'] ?? [];
            $recipeItems = $data['recipe_items'] ?? [];
            $initialQtyBase = (float) ($data['initial_qty_base'] ?? 0);
            $initialUnitCost = (float) ($data['initial_unit_cost'] ?? 0);
            unset(
                $data['presentations'],
                $data['recipe_items'],
                $data['initial_qty_base'],
                $data['initial_unit_cost']
            );

            if (empty($data['sku'])) {
                $data['sku'] = null;
            }

            if (array_key_exists('product_category_id', $data) && !$data['product_category_id']) {
                $data['product_category_id'] = null;
            }
            if (array_key_exists('kitchen_station_id', $data) && !$data['kitchen_station_id']) {
                $data['kitchen_station_id'] = null;
            }

            $data['inventory_mode'] = $this->normalizeInventoryMode($data['inventory_mode'] ?? null);

            $this->assertCategoryBelongsToPlace($data);
            $this->assertKitchenStationBelongsToPlace($data);

            $product = Product::create($data);
            $this->syncPresentations($product, $presentations);
            $this->syncRecipeItems($product, $recipeItems);

            if ($product->usesRecipeInventory()) {
                // Composed products do not hold their own stock.
            } else {
                $this->createOpeningLot($product, $initialQtyBase, $initialUnitCost);
            }

            return $this->find($product->id);
        });
    }

    /**
     * Opening inventory when the business already has stock (no purchase document).
     */
    protected function createOpeningLot(Product $product, float $qtyBase, float $unitCost = 0): void
    {
        $qtyBase = round($qtyBase, 4);
        if ($qtyBase <= 0) {
            return;
        }

        StockLot::create([
            'place_id' => $product->place_id,
            'product_id' => $product->id,
            'purchase_id' => null,
            'lot_code' => 'OPENING-'.$product->id,
            'purchased_at' => now(),
            'expires_at' => null,
            'unit_cost' => round(max(0, $unitCost), 4),
            'qty_initial' => $qtyBase,
            'qty_remaining' => $qtyBase,
        ]);
    }

    public function update($id, array $data): Product
    {
        return DB::transaction(function () use ($id, $data) {
            $product = Product::findOrFail($id);
            $presentations = $data['presentations'] ?? null;
            $recipeItems = array_key_exists('recipe_items', $data) ? $data['recipe_items'] : null;
            unset($data['presentations'], $data['recipe_items']);

            if (array_key_exists('sku', $data) && $data['sku'] === '') {
                $data['sku'] = null;
            }

            if (array_key_exists('product_category_id', $data) && !$data['product_category_id']) {
                $data['product_category_id'] = null;
            }
            if (array_key_exists('kitchen_station_id', $data) && !$data['kitchen_station_id']) {
                $data['kitchen_station_id'] = null;
            }

            if (array_key_exists('inventory_mode', $data)) {
                $data['inventory_mode'] = $this->normalizeInventoryMode($data['inventory_mode']);
            }

            $placeScoped = [
                ...$data,
                'place_id' => $data['place_id'] ?? $product->place_id,
            ];
            $this->assertCategoryBelongsToPlace($placeScoped);
            $this->assertKitchenStationBelongsToPlace($placeScoped);

            $product->update($data);
            $product->refresh();

            if (is_array($presentations)) {
                $this->syncPresentations($product, $presentations);
            }

            if (is_array($recipeItems)) {
                $this->syncRecipeItems($product, $recipeItems);
            } elseif ($product->usesRecipeInventory() && $product->recipeItems()->count() === 0) {
                throw new Exception('Un producto con receta necesita al menos un ingrediente.');
            }

            return $this->find($product->id);
        });
    }

    public function delete($id): void
    {
        $product = Product::findOrFail($id);
        if ($product->lots()->where('qty_remaining', '>', 0)->exists()) {
            throw new Exception('No se puede eliminar un producto con stock.');
        }
        $product->delete();
    }

    protected function normalizeInventoryMode(?string $mode): string
    {
        $mode = $mode ?: Product::INVENTORY_STANDARD;
        if (!in_array($mode, [Product::INVENTORY_STANDARD, Product::INVENTORY_RECIPE], true)) {
            throw new Exception('Modo de inventario inválido.');
        }

        return $mode;
    }

    protected function syncRecipeItems(Product $product, array $recipeItems): void
    {
        if (!$product->usesRecipeInventory()) {
            $product->recipeItems()->delete();

            return;
        }

        if (count($recipeItems) === 0) {
            throw new Exception('Un producto con receta necesita al menos un ingrediente.');
        }

        $product->loadMissing('presentations');
        foreach ($product->presentations as $presentation) {
            if (abs((float) $presentation->factor - 1.0) > 0.0001) {
                throw new Exception(
                    'Los productos con receta deben usar factor 1 en todas las presentaciones (la receta se mide por unidad vendida).'
                );
            }
        }

        $keepIds = [];
        $seenIngredients = [];

        foreach ($recipeItems as $index => $row) {
            $ingredientId = (int) ($row['ingredient_product_id'] ?? 0);
            $qtyBase = round((float) ($row['qty_base'] ?? 0), 4);

            if ($ingredientId <= 0) {
                throw new Exception('Cada ítem de receta necesita un ingrediente.');
            }

            if ($ingredientId === (int) $product->id) {
                throw new Exception('Un producto no puede ser ingrediente de sí mismo.');
            }

            if ($qtyBase <= 0) {
                throw new Exception('La cantidad de cada ingrediente debe ser mayor que 0.');
            }

            if (isset($seenIngredients[$ingredientId])) {
                throw new Exception('No repita el mismo ingrediente en la receta.');
            }
            $seenIngredients[$ingredientId] = true;

            $ingredient = Product::find($ingredientId);
            if (!$ingredient) {
                throw new Exception('El ingrediente no existe.');
            }

            if ((int) $ingredient->place_id !== (int) $product->place_id) {
                throw new Exception("El ingrediente {$ingredient->name} no pertenece a esta sede.");
            }

            if ($ingredient->usesRecipeInventory()) {
                throw new Exception(
                    "{$ingredient->name} también es un producto con receta. Use solo ingredientes con stock propio."
                );
            }

            $payload = [
                'ingredient_product_id' => $ingredientId,
                'qty_base' => $qtyBase,
                'sort_order' => (int) ($row['sort_order'] ?? $index),
            ];

            if (!empty($row['id'])) {
                $item = ProductRecipeItem::where('product_id', $product->id)
                    ->where('id', $row['id'])
                    ->firstOrFail();
                $item->update($payload);
            } else {
                $item = $product->recipeItems()->create($payload);
            }

            $keepIds[] = $item->id;
        }

        $product->recipeItems()->whereNotIn('id', $keepIds)->delete();
    }

    /**
     * For recipe products, stock_on_hand = how many sale units can be produced.
     */
    protected function applyRecipeStock(Collection $products): Collection
    {
        $recipeProducts = $products->filter(fn (Product $product) => $product->usesRecipeInventory());
        if ($recipeProducts->isEmpty()) {
            return $products;
        }

        $recipeProducts->loadMissing('recipeItems');
        $ingredientIds = $recipeProducts
            ->flatMap(fn (Product $product) => $product->recipeItems->pluck('ingredient_product_id'))
            ->unique()
            ->filter()
            ->values()
            ->all();

        $ingredientStocks = collect();
        if (count($ingredientIds) > 0) {
            $ingredientStocks = Product::query()
                ->whereIn('id', $ingredientIds)
                ->withSum('lots', 'qty_remaining')
                ->get()
                ->keyBy('id');
        }

        foreach ($recipeProducts as $product) {
            $product->setAttribute(
                'lots_sum_qty_remaining',
                $this->producibleQty($product, $ingredientStocks)
            );
        }

        return $products;
    }

    protected function producibleQty(Product $product, Collection $ingredientStocks): float
    {
        $items = $product->recipeItems;
        if ($items->isEmpty()) {
            return 0.0;
        }

        $min = null;
        foreach ($items as $item) {
            $need = (float) $item->qty_base;
            if ($need <= 0) {
                return 0.0;
            }

            $ingredient = $ingredientStocks->get($item->ingredient_product_id);
            $have = $ingredient ? (float) $ingredient->stock_on_hand : 0.0;
            $canMake = floor($have / $need);
            $min = $min === null ? $canMake : min($min, $canMake);
        }

        return (float) ($min ?? 0);
    }

    protected function syncPresentations(Product $product, array $presentations): void
    {
        if (count($presentations) === 0) {
            throw new Exception('El producto necesita al menos una presentación.');
        }

        $keepIds = [];
        $hasDefault = false;

        foreach ($presentations as $index => $row) {
            $factor = (float) ($row['factor'] ?? 0);
            if ($factor <= 0) {
                throw new Exception('El factor de cada presentación debe ser mayor que 0.');
            }

            if ($product->usesRecipeInventory() && abs($factor - 1.0) > 0.0001) {
                throw new Exception(
                    'Los productos con receta deben usar factor 1 en todas las presentaciones.'
                );
            }

            $tax = \App\Support\SalePricing::resolveTax(
                isset($row['tax_id']) && $row['tax_id'] !== '' && $row['tax_id'] !== null
                    ? (int) $row['tax_id']
                    : null
            );

            $payload = [
                'name' => $row['name'] ?? '',
                'factor' => $factor,
                'sale_price' => (float) ($row['sale_price'] ?? 0),
                'tax_id' => $tax->id,
                'tax_rate' => round((float) $tax->rate, 2),
                'barcode' => $row['barcode'] ?? null,
                'is_default' => (bool) ($row['is_default'] ?? false),
                'status' => $row['status'] ?? 'activo',
            ];

            if ($payload['name'] === '') {
                throw new Exception('Cada presentación necesita un nombre.');
            }

            if (!empty($row['id'])) {
                $presentation = ProductPresentation::where('product_id', $product->id)
                    ->where('id', $row['id'])
                    ->firstOrFail();
                $presentation->update($payload);
            } else {
                $presentation = $product->presentations()->create($payload);
            }

            $keepIds[] = $presentation->id;
            if ($presentation->is_default) {
                $hasDefault = true;
            }
        }

        $product->presentations()->whereNotIn('id', $keepIds)->delete();

        if (!$hasDefault) {
            $first = $product->presentations()->orderBy('id')->first();
            if ($first) {
                $first->update(['is_default' => true]);
            }
        }
    }

    protected function assertCategoryBelongsToPlace(array $data): void
    {
        if (!array_key_exists('product_category_id', $data) || $data['product_category_id'] === null) {
            return;
        }

        $category = ProductCategory::find((int) $data['product_category_id']);
        if (!$category) {
            throw new Exception('La categoría no existe.');
        }

        $placeId = (int) ($data['place_id'] ?? 0);
        if ($placeId && (int) $category->place_id !== $placeId) {
            throw new Exception('La categoría no pertenece a esta sede.');
        }
    }

    protected function assertKitchenStationBelongsToPlace(array $data): void
    {
        if (! array_key_exists('kitchen_station_id', $data) || $data['kitchen_station_id'] === null) {
            return;
        }

        $station = Station::find((int) $data['kitchen_station_id']);
        if (! $station) {
            throw new Exception('La estación de cocina no existe.');
        }
        if (($station->type ?? Station::TYPE_REGISTER) !== Station::TYPE_KITCHEN) {
            throw new Exception('La estación asignada debe ser de tipo cocina.');
        }

        $placeId = (int) ($data['place_id'] ?? 0);
        if ($placeId && (int) $station->place_id !== $placeId) {
            throw new Exception('La estación de cocina no pertenece a esta sede.');
        }
    }

    protected function attachImageUrls(Collection $products): Collection
    {
        if ($products->isEmpty()) {
            return $products;
        }

        $files = File::query()
            ->where('model_name', 'products')
            ->where('name', self::IMAGE_NAME)
            ->whereIn('model_id', $products->pluck('id')->all())
            ->orderByDesc('id')
            ->get()
            ->unique('model_id')
            ->keyBy('model_id');

        return $products->map(function (Product $product) use ($files) {
            $file = $files->get($product->id);
            // Relative storage path; the frontend prefixes URL_FILES.
            // Cache-bust with file id so re-uploads with stable filenames refresh in the UI.
            $product->setAttribute(
                'image_url',
                $file?->url ? ($file->url.'?v='.$file->id) : null
            );
            $product->makeVisible(['image_url']);

            return $product;
        });
    }
}
