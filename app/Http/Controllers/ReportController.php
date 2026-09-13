<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    protected $service;

    public function __construct(ReportService $service)
    {
        $this->service = $service;
    }

    public function list(): JsonResponse
    {
        try {
            $userId = auth()->id();
            return response()->json($this->service->all($userId));
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al obtener registros', 'message' => $e->getMessage()], 500);
        }
    }

    public function execute($id): JsonResponse
    {
        try {
            return response()->json($this->service->execute($id));
        } catch (Exception $e) {
            return response()->json(['error' => "Error al ejecutar el reporte con ID $id", 'message' => $e->getMessage()], 404);
        }
    }

}
