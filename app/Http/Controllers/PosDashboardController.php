<?php

namespace App\Http\Controllers;

use App\Services\PosDashboardService;
use App\Support\PlaceScope;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosDashboardController extends Controller
{
    public function __construct(protected PosDashboardService $service)
    {
    }

    public function byPlace(Request $request, $place): JsonResponse
    {
        try {
            PlaceScope::assert(
                $request->user(),
                (int) $place,
                'pos-dashboard.allPlaces',
                'sales.allPlaces'
            );

            $data = $this->service->summaryForPlace(
                (int) $place,
                $request->query('date_from'),
                $request->query('date_to')
            );

            return response()->json(['data' => $data]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al cargar dashboard', 'message' => $e->getMessage()], 400);
        }
    }
}
