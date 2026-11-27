<?php

namespace App\Http\Controllers;

use App\Services\ProductService;
use App\Support\PlaceScope;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(protected ProductService $service)
    {
    }

    public function index(): JsonResponse
    {
        try {
            return response()->json($this->service->all());
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al listar productos', 'message' => $e->getMessage()], 500);
        }
    }

    public function byPlace(Request $request, $place): JsonResponse
    {
        try {
            PlaceScope::assert($request->user(), (int) $place, 'products.allPlaces');
            $onlyActive = filter_var($request->query('active', false), FILTER_VALIDATE_BOOLEAN);

            return response()->json($this->service->findByPlace((int) $place, $onlyActive));
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al listar productos', 'message' => $e->getMessage()], 400);
        }
    }

    public function show(Request $request, $id): JsonResponse
    {
        try {
            $record = $this->service->find($id);
            PlaceScope::assert($request->user(), (int) $record->place_id, 'products.allPlaces');

            return response()->json($record);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al obtener producto', 'message' => $e->getMessage()], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'place_id' => 'required|integer|exists:places,id',
                'product_category_id' => 'nullable|integer|exists:product_categories,id',
                'name' => 'required|string|max:255',
                'sku' => 'nullable|string|max:80',
                'barcode' => 'nullable|string|max:80',
                'base_unit_name' => 'required|string|max:40',
                'inventory_mode' => 'nullable|string|in:standard,recipe',
                'min_stock' => 'nullable|numeric|min:0',
                'status' => 'nullable|string|max:20',
                // Opening inventory for businesses that already have stock (no purchase yet).
                'initial_qty_base' => 'nullable|numeric|min:0',
                'initial_unit_cost' => 'nullable|numeric|min:0',
                'presentations' => 'required|array|min:1',
                'presentations.*.id' => 'nullable|integer',
                'presentations.*.name' => 'required|string|max:80',
                'presentations.*.factor' => 'required|numeric|min:0.0001',
                'presentations.*.sale_price' => 'required|numeric|min:0',
                'presentations.*.tax_id' => 'nullable|integer|exists:taxes,id',
                'presentations.*.barcode' => 'nullable|string|max:80',
                'presentations.*.is_default' => 'nullable|boolean',
                'presentations.*.status' => 'nullable|string|max:20',
                'recipe_items' => 'nullable|array',
                'recipe_items.*.id' => 'nullable|integer',
                'recipe_items.*.ingredient_product_id' => 'required_with:recipe_items|integer|exists:products,id',
                'recipe_items.*.qty_base' => 'required_with:recipe_items|numeric|min:0.0001',
                'recipe_items.*.sort_order' => 'nullable|integer|min:0',
            ]);
            PlaceScope::assert($request->user(), (int) $data['place_id'], 'products.allPlaces');
            $record = $this->service->create($data);

            return response()->json(['message' => 'Producto creado', 'data' => $record], 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al crear producto', 'message' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $record = $this->service->find($id);
            PlaceScope::assert($request->user(), (int) $record->place_id, 'products.allPlaces');
            $data = $request->validate([
                'place_id' => 'sometimes|integer|exists:places,id',
                'product_category_id' => 'nullable|integer|exists:product_categories,id',
                'name' => 'sometimes|string|max:255',
                'sku' => 'nullable|string|max:80',
                'barcode' => 'nullable|string|max:80',
                'base_unit_name' => 'sometimes|string|max:40',
                'inventory_mode' => 'nullable|string|in:standard,recipe',
                'min_stock' => 'nullable|numeric|min:0',
                'status' => 'nullable|string|max:20',
                'presentations' => 'sometimes|array|min:1',
                'presentations.*.id' => 'nullable|integer',
                'presentations.*.name' => 'required_with:presentations|string|max:80',
                'presentations.*.factor' => 'required_with:presentations|numeric|min:0.0001',
                'presentations.*.sale_price' => 'required_with:presentations|numeric|min:0',
                'presentations.*.tax_id' => 'nullable|integer|exists:taxes,id',
                'presentations.*.barcode' => 'nullable|string|max:80',
                'presentations.*.is_default' => 'nullable|boolean',
                'presentations.*.status' => 'nullable|string|max:20',
                'recipe_items' => 'nullable|array',
                'recipe_items.*.id' => 'nullable|integer',
                'recipe_items.*.ingredient_product_id' => 'required_with:recipe_items|integer|exists:products,id',
                'recipe_items.*.qty_base' => 'required_with:recipe_items|numeric|min:0.0001',
                'recipe_items.*.sort_order' => 'nullable|integer|min:0',
            ]);
            $record = $this->service->update($id, $data);

            return response()->json(['message' => 'Producto actualizado', 'data' => $record]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al actualizar producto', 'message' => $e->getMessage()], 400);
        }
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        try {
            $record = $this->service->find($id);
            PlaceScope::assert($request->user(), (int) $record->place_id, 'products.allPlaces');
            $this->service->delete($id);

            return response()->json(['message' => 'Producto eliminado']);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al eliminar producto', 'message' => $e->getMessage()], 400);
        }
    }
}
