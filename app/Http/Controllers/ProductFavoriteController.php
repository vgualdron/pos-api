<?php

namespace App\Http\Controllers;

use App\Services\ProductFavoriteService;
use App\Support\PlaceScope;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductFavoriteController extends Controller
{
    public function __construct(protected ProductFavoriteService $service)
    {
    }

    public function byPlace(Request $request, $place): JsonResponse
    {
        try {
            PlaceScope::assert($request->user(), (int) $place, 'products.allPlaces', 'sales.allPlaces');

            return response()->json([
                'data' => $this->service->byPlace((int) $place),
                'product_ids' => $this->service->favoriteProductIds((int) $place),
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al listar favoritos', 'message' => $e->getMessage()], 400);
        }
    }

    public function toggle(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'place_id' => 'required|integer|exists:places,id',
                'product_id' => 'required|integer|exists:products,id',
            ]);
            PlaceScope::assert($request->user(), (int) $data['place_id'], 'products.allPlaces', 'sales.allPlaces');
            $result = $this->service->toggle((int) $data['place_id'], (int) $data['product_id']);

            return response()->json([
                'message' => $result['is_favorite'] ? 'Agregado a favoritos' : 'Quitado de favoritos',
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al actualizar favorito', 'message' => $e->getMessage()], 400);
        }
    }

    public function sync(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'place_id' => 'required|integer|exists:places,id',
                'product_ids' => 'required|array',
                'product_ids.*' => 'integer|exists:products,id',
            ]);
            PlaceScope::assert($request->user(), (int) $data['place_id'], 'products.allPlaces', 'sales.allPlaces');
            $ids = $this->service->sync((int) $data['place_id'], $data['product_ids']);

            return response()->json([
                'message' => 'Favoritos actualizados',
                'data' => ['product_ids' => $ids],
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al sincronizar favoritos', 'message' => $e->getMessage()], 400);
        }
    }
}
