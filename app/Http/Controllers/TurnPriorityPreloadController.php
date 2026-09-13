<?php

namespace App\Http\Controllers;

use App\Models\Turn;
use App\Services\TurnPriorityPreloadService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TurnPriorityPreloadController extends Controller
{
    public function __construct(protected TurnPriorityPreloadService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'place_id' => 'required|integer|exists:places,id',
                'date' => 'required|date_format:Y-m-d',
            ]);

            $rows = $this->service->list(
                $request->user(),
                (int) $request->query('place_id'),
                $request->query('date')
            );

            return response()->json($rows);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al listar precargas', 'message' => $e->getMessage()], 400);
        }
    }

    public function lookup(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'document' => 'required|string|max:50',
                'place_id' => 'nullable|integer|exists:places,id',
                'date' => 'nullable|date_format:Y-m-d',
            ]);

            $row = $this->service->lookup(
                $request->user(),
                $request->query('document'),
                $request->query('place_id') ? (int) $request->query('place_id') : null,
                $request->query('date')
            );

            return response()->json(['data' => $row]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al consultar precarga', 'message' => $e->getMessage()], 400);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'place_id' => 'required|integer|exists:places,id',
                'scheduled_date' => 'required|date_format:Y-m-d',
                'patient_document' => 'required|string|max:50',
                'patient_name' => 'required|string|max:255',
                'patient_phone' => 'nullable|string|max:30',
                'patient_company' => 'nullable|string|max:255',
                'priority' => ['nullable', Rule::in(Turn::PRIORITIES)],
            ]);

            $record = $this->service->create($request->user(), $request->only([
                'place_id',
                'scheduled_date',
                'patient_document',
                'patient_name',
                'patient_phone',
                'patient_company',
                'priority',
            ]));

            return response()->json(['message' => 'Registro creado', 'data' => $record], 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al crear registro', 'message' => $e->getMessage()], 400);
        }
    }

    public function bulk(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'place_id' => 'required|integer|exists:places,id',
                'scheduled_date' => 'required|date_format:Y-m-d',
                'priority' => ['nullable', Rule::in(Turn::PRIORITIES)],
                'rows' => 'required|array|min:1|max:5000',
                'rows.*.patient_document' => 'nullable|string|max:50',
                'rows.*.document' => 'nullable|string|max:50',
                'rows.*.patient_name' => 'nullable|string|max:255',
                'rows.*.name' => 'nullable|string|max:255',
                'rows.*.patient_phone' => 'nullable|string|max:30',
                'rows.*.phone' => 'nullable|string|max:30',
                'rows.*.patient_company' => 'nullable|string|max:255',
                'rows.*.company' => 'nullable|string|max:255',
            ]);

            $result = $this->service->bulkUpsert(
                $request->user(),
                (int) $request->input('place_id'),
                $request->input('scheduled_date'),
                $request->input('rows', []),
                $request->input('priority')
            );

            return response()->json([
                'message' => 'Carga procesada',
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error en carga masiva', 'message' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'scheduled_date' => 'nullable|date_format:Y-m-d',
                'patient_document' => 'nullable|string|max:50',
                'patient_name' => 'nullable|string|max:255',
                'patient_phone' => 'nullable|string|max:30',
                'patient_company' => 'nullable|string|max:255',
                'priority' => ['nullable', Rule::in(Turn::PRIORITIES)],
                'status' => 'nullable|in:pending,cancelled',
            ]);

            $record = $this->service->update(
                $request->user(),
                (int) $id,
                $request->only([
                    'scheduled_date',
                    'patient_document',
                    'patient_name',
                    'patient_phone',
                    'patient_company',
                    'priority',
                    'status',
                ])
            );

            return response()->json(['message' => 'Registro actualizado', 'data' => $record]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al actualizar', 'message' => $e->getMessage()], 400);
        }
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        try {
            $this->service->delete($request->user(), (int) $id);

            return response()->json(['message' => 'Registro eliminado']);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al eliminar', 'message' => $e->getMessage()], 400);
        }
    }
}
