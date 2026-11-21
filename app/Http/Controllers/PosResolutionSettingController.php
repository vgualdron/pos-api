<?php

namespace App\Http\Controllers;

use App\Services\PosResolutionSettingService;
use App\Support\CompanyScope;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosResolutionSettingController extends Controller
{
    public function __construct(protected PosResolutionSettingService $service)
    {
    }

    public function show(Request $request, $companyId): JsonResponse
    {
        try {
            CompanyScope::assert($request->user(), (int) $companyId);

            return response()->json($this->service->getForCompany((int) $companyId));
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Error al obtener la resolución POS',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function upsert(Request $request, $companyId): JsonResponse
    {
        try {
            CompanyScope::assert($request->user(), (int) $companyId);

            $placesData = $request->input('places', []);
            if (! is_array($placesData)) {
                throw new Exception('Datos de sedes inválidos.');
            }

            $result = $this->service->upsert((int) $companyId, $placesData);

            return response()->json([
                'message' => 'Resolución POS guardada',
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Error al guardar la resolución POS',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
