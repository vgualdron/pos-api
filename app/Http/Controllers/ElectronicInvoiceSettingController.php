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
            $document = $request->query('document', '21');

            return response()->json([
                'data' => $this->service->numberingRanges((int) $companyId, is_string($document) ? $document : '21'),
                'document' => $document,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Error al listar rangos de numeración',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function dianNumberingRanges(Request $request, $companyId): JsonResponse
    {
        try {
            CompanyScope::assert($request->user(), (int) $companyId);

            return response()->json([
                'data' => $this->service->dianNumberingRanges((int) $companyId),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Error al consultar rangos DIAN',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function createNumberingRange(Request $request, $companyId): JsonResponse
    {
        try {
            CompanyScope::assert($request->user(), (int) $companyId);

            $result = $this->service->createNumberingRange((int) $companyId, $request->all());

            return response()->json([
                'message' => 'Rango de numeración creado en Factus',
                'data' => $result,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Error al crear el rango',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function updateNumberingRangeCurrent(Request $request, $companyId, $rangeId): JsonResponse
    {
        try {
            CompanyScope::assert($request->user(), (int) $companyId);

            if (! $request->filled('current')) {
                throw new Exception('Debe indicar el consecutivo actual (current).');
            }

            $result = $this->service->updateNumberingRangeCurrent(
                (int) $companyId,
                (int) $rangeId,
                $request->input('current')
            );

            return response()->json([
                'message' => 'Consecutivo actualizado',
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Error al actualizar consecutivo',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function toggleNumberingRangeStatus(Request $request, $companyId, $rangeId): JsonResponse
    {
        try {
            CompanyScope::assert($request->user(), (int) $companyId);

            $result = $this->service->toggleNumberingRangeStatus((int) $companyId, (int) $rangeId);

            return response()->json([
                'message' => 'Estado del rango actualizado',
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Error al cambiar estado del rango',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
