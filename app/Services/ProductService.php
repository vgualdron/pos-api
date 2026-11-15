<?php

namespace App\Services;

use App\Models\File;
use App\Models\Product;
use App\Models\ProductPresentation;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public const IMAGE_NAME = 'PHOTO_PRODUCT';

    public function all()
    {
        return $this->attachImageUrls(
            Product::withSum('lots', 'qty_remaining')
                ->orderBy('place_id')
                ->orderBy('name')
                ->get()
        );
    }

    public function find($id): Product
    {
        $product = Product::withSum('lots', 'qty_remaining')->findOrFail($id);

        return $this->attachImageUrls(collect([$product]))->first();
    }

    public function findByPlace($placeId)
    {
        return $this->attachImageUrls(
            Product::withSum('lots', 'qty_remaining')
                ->where('place_id', $placeId)
                ->where('status', 'activo')
                ->orderBy('name')
                ->get()
        );
    }

    public function catalog(int $placeId)
    {
        return $this->findByPlace($placeId);
    }

    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $presentations = $data['presentations'] ?? [];
            unset($data['presentations']);

            if (empty($data['sku'])) {
                $data['sku'] = null;
            }

            $product = Product::create($data);
            $this->syncPresentations($product, $presentations);

            return $this->find($product->id);
        });
    }

    public function update($id, array $data): Product
    {
        return DB::transaction(function () use ($id, $data) {
            $product = Product::findOrFail($id);
            $presentations = $data['presentations'] ?? null;
            unset($data['presentations']);

            if (array_key_exists('sku', $data) && $data['sku'] === '') {
                $data['sku'] = null;
            }

            $product->update($data);

            if (is_array($presentations)) {
                $this->syncPresentations($product, $presentations);
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

            $payload = [
                'name' => $row['name'] ?? '',
                'factor' => $factor,
                'sale_price' => (float) ($row['sale_price'] ?? 0),
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
            $product->setAttribute('image_url', $file?->url);
            $product->makeVisible(['image_url']);

            return $product;
        });
    }
}
