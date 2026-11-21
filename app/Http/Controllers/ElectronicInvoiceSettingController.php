<?php

namespace App\Http\Controllers;

use App\Services\ElectronicInvoiceSettingService;
use App\Support\CompanyScope;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ElectronicInvoiceSettingController extends Controller
{
    public function __construct(protected ElectronicInvoiceSettingService $service)
    {
    }

    public function show(Request $request, $companyId): JsonResponse
    {
        try {
            CompanyScope::assert($request->user(), (int) $companyId);

            return response()->json($this->service->getForCompany((int) $companyId));
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Error al obtener la configuración de facturación electrónica',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function upsert(Request $request, $companyId): JsonResponse
    {
        try {
            CompanyScope::assert($request->user(), (int) $companyId);

            $companyData = $request->input('company', []);
            $placesData = $request->input('places', []);

            if (! is_array($companyData)) {
                throw new Exception('Datos de empresa inválidos.');
            }
            if (! is_array($placesData)) {
                throw new Exception('Datos de sedes inválidos.');
            }

            $result = $this->service->upsert((int) $companyId, $companyData, $placesData);

            return response()->json([
                'message' => 'Configuración de facturación electrónica guardada',
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Error al guardar la configuración',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function test(Request $request, $companyId): JsonResponse
    {
        try {
            CompanyScope::assert($request->user(), (int) $companyId);

            $result = $this->service->testConnection((int) $companyId);

            return response()->json([
                'message' => 'Conexión con Factus correcta',
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'No se pudo conectar con Factus',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function numberingRanges(Request $request, $companyId): JsonResponse
    {
        try {
            CompanyScope::assert($request->user(), (int) $companyId);

            return response()->json([
                'data' => $this->service->numberingRanges((int) $companyId),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Error al listar rangos de numeración',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
