<?php

namespace App\Http\Controllers;

use App\Models\DisplayAlert;
use App\Services\DisplayAlertService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DisplayAlertController extends Controller
{
    public function __construct(protected DisplayAlertService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'place_id' => 'required|integer|exists:places,id',
            ]);

            $rows = $this->service->list(
                $request->user(),
                (int) $request->query('place_id')
            );

            return response()->json($rows);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al listar alertas', 'message' => $e->getMessage()], 400);
        }
    }

    public function targets(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'place_id' => 'required|integer|exists:places,id',
            ]);

            $rows = $this->service->listTargets(
                $request->user(),
                (int) $request->query('place_id')
            );

            return response()->json($rows);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al listar pantallas', 'message' => $e->getMessage()], 400);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate($this->rules());

            $record = $this->service->create($request->user(), $request->only([
                'place_id',
                'name',
                'message',
                'type',
                'status',
                'duration_seconds',
                'repeat_count',
                'speak_enabled',
                'show_on_screen',
            ]));

            return response()->json(['message' => 'Alerta creada', 'data' => $record], 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al crear alerta', 'message' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $request->validate($this->rules(true));

            $record = $this->service->update(
                $request->user(),
                (int) $id,
                $request->only([
                    'name',
                    'message',
                    'type',
                    'status',
                    'duration_seconds',
                    'repeat_count',
                    'speak_enabled',
                    'show_on_screen',
                    'display_order',
                ])
            );

            return response()->json(['message' => 'Alerta actualizada', 'data' => $record]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al actualizar alerta', 'message' => $e->getMessage()], 400);
        }
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        try {
            $this->service->delete($request->user(), (int) $id);

            return response()->json(['message' => 'Alerta eliminada']);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al eliminar alerta', 'message' => $e->getMessage()], 400);
        }
    }

    public function play(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'station_ids' => 'required|array|min:1',
                'station_ids.*' => 'integer|exists:stations,id',
            ]);

            $record = $this->service->play(
                $request->user(),
                (int) $id,
                $request->input('station_ids', [])
            );

            return response()->json([
                'message' => 'Alerta enviada a las pantallas de TV',
                'data' => $record,
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al reproducir alerta', 'message' => $e->getMessage()], 400);
        }
    }

    protected function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'place_id' => $partial ? 'nullable|integer|exists:places,id' : 'required|integer|exists:places,id',
            'name' => "$required|string|max:80",
            'message' => "$required|string|max:200",
            'type' => ['nullable', Rule::in(DisplayAlert::TYPES)],
            'status' => ['nullable', Rule::in(DisplayAlert::STATUSES)],
            'duration_seconds' => 'nullable|integer|min:5|max:120',
            'repeat_count' => 'nullable|integer|min:1|max:5',
            'speak_enabled' => 'sometimes|boolean',
            'show_on_screen' => 'sometimes|boolean',
            'display_order' => 'nullable|integer|min:0',
        ];
    }
}
