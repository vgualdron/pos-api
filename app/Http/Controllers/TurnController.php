<?php

namespace App\Http\Controllers;

use App\Models\Turn;
use App\Services\TurnOperationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TurnController extends Controller
{
    protected TurnOperationService $service;

    public function __construct(TurnOperationService $service)
    {
        $this->service = $service;
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'service_id' => 'required|integer|exists:services,id',
                'patient_name' => 'nullable|string|max:255',
                'patient_document' => 'nullable|string|max:50',
                'patient_phone' => 'nullable|string|max:30',
                'patient_company' => 'nullable|string|max:255',
                'priority' => ['nullable', Rule::in(Turn::PRIORITIES)],
                'parent_turn_id' => 'nullable|integer|exists:turns,id',
            ]);

            $turn = $this->service->create($request->user(), $request->only([
                'service_id',
                'patient_name',
                'patient_document',
                'patient_phone',
                'patient_company',
                'priority',
                'parent_turn_id',
            ]));

            return response()->json(['message' => 'Turno generado con éxito', 'data' => $turn], 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al generar turno', 'message' => $e->getMessage()], 400);
        }
    }

    public function queue(Request $request): JsonResponse
    {
        try {
            $serviceId = $request->query('service_id');

            return response()->json(
                $this->service->queue($request->user(), $serviceId ? (int) $serviceId : null)
            );
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al obtener cola', 'message' => $e->getMessage()], 400);
        }
    }

    public function current(Request $request): JsonResponse
    {
        try {
            $turn = $this->service->currentOpen($request->user());

            return response()->json(['data' => $turn]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al obtener turno actual', 'message' => $e->getMessage()], 400);
        }
    }

    public function callNext(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'service_id' => 'nullable|integer',
            ]);

            $serviceId = $request->input('service_id');

            $turn = $this->service->callNext(
                $request->user(),
                $serviceId ? (int) $serviceId : null
            );

            return response()->json(['message' => 'Turno llamado con éxito', 'data' => $turn], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al llamar turno', 'message' => $e->getMessage()], 400);
        }
    }

    public function callByNumber(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'ticket_number' => 'required|string',
                'silent' => 'sometimes|boolean',
            ]);

            $turn = $this->service->callByNumber(
                $request->user(),
                $request->ticket_number,
                $request->boolean('silent')
            );

            return response()->json(['message' => 'Turno llamado con éxito', 'data' => $turn], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al llamar turno por número', 'message' => $e->getMessage()], 400);
        }
    }

    public function recall(Request $request, $id): JsonResponse
    {
        try {
            $turn = $this->service->recall($request->user(), (int) $id);

            return response()->json(['message' => 'Turno rellamado con éxito', 'data' => $turn], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al rellamar turno', 'message' => $e->getMessage()], 400);
        }
    }

    public function start(Request $request, $id): JsonResponse
    {
        try {
            $turn = $this->service->start($request->user(), (int) $id);

            return response()->json(['message' => 'Atención iniciada con éxito', 'data' => $turn], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al iniciar atención', 'message' => $e->getMessage()], 400);
        }
    }

    public function complete(Request $request, $id): JsonResponse
    {
        try {
            $turn = $this->service->complete($request->user(), (int) $id);

            return response()->json(['message' => 'Turno finalizado con éxito', 'data' => $turn], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al finalizar turno', 'message' => $e->getMessage()], 400);
        }
    }

    public function noShow(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'note' => 'nullable|string|max:500',
            ]);

            $turn = $this->service->noShow(
                $request->user(),
                (int) $id,
                $request->input('note')
            );

            return response()->json(['message' => 'Turno marcado como no asistió', 'data' => $turn], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al marcar no asistió', 'message' => $e->getMessage()], 400);
        }
    }

    public function cancel(Request $request, $id): JsonResponse
    {
        try {
            $turn = $this->service->cancel($request->user(), (int) $id);

            return response()->json(['message' => 'Turno cancelado con éxito', 'data' => $turn], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al cancelar turno', 'message' => $e->getMessage()], 400);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            return response()->json($this->service->find((int) $id));
        } catch (Exception $e) {
            return response()->json(['error' => "Error al obtener turno $id", 'message' => $e->getMessage()], 404);
        }
    }

    public function noShowsToday(Request $request): JsonResponse
    {
        try {
            return response()->json(['data' => $this->service->noShowsToday($request->user())]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al obtener no asistió', 'message' => $e->getMessage()], 400);
        }
    }

    public function display(Request $request): JsonResponse
    {
        try {
            $limit = (int) $request->query('limit', 10);

            return response()->json($this->service->display($request->user(), $limit));
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al obtener pantalla de llamados', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Proxy TTS para pantallas TV (el navegador de TV suele bloquear Google TTS directo
     * y el autoplay fuera del gesto del usuario).
     */
    public function tts(Request $request)
    {
        try {
            $request->validate([
                'text' => 'required|string|min:1|max:200',
            ]);

            $text = trim((string) $request->query('text'));
            $audio = $this->service->synthesizeSpeech($text);

            return response($audio, 200, [
                'Content-Type' => 'audio/mpeg',
                'Cache-Control' => 'public, max-age=300',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Error al sintetizar voz',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function history(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'date' => 'nullable|date_format:Y-m-d',
                'place_id' => 'nullable|integer|exists:places,id',
            ]);

            $turns = $this->service->history(
                $request->user(),
                $request->query('date'),
                $request->query('place_id') ? (int) $request->query('place_id') : null
            );

            return response()->json($turns);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al obtener historial de turnos', 'message' => $e->getMessage()], 400);
        }
    }

    /** @deprecated Usar GET /turns/history */
    public function today(Request $request): JsonResponse
    {
        return $this->history($request);
    }

    public function lookupPatient(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'document' => 'required|string|min:5|max:50',
            ]);

            $profile = $this->service->lookupPatient(
                $request->user(),
                (string) $request->query('document')
            );

            return response()->json(['data' => $profile]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al consultar el documento', 'message' => $e->getMessage()], 400);
        }
    }

    public function trackPublic(string $qrToken): JsonResponse
    {
        try {
            return response()->json($this->service->track($qrToken));
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al consultar turno', 'message' => $e->getMessage()], 404);
        }
    }

    public function ratePublic(Request $request, string $qrToken): JsonResponse
    {
        try {
            $request->validate([
                'rating' => 'required|integer|min:1|max:5',
                'comment' => 'nullable|string|max:500',
            ]);

            $data = $this->service->rateByQrToken(
                $qrToken,
                (int) $request->input('rating'),
                $request->input('comment')
            );

            return response()->json([
                'message' => 'Gracias por calificar nuestro servicio.',
                'data' => $data,
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error al calificar', 'message' => $e->getMessage()], 400);
        }
    }
}
