<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Service;
use App\Models\Station;
use App\Models\Turn;
use App\Models\User;
use App\Models\File;
use App\Repositories\TurnRepository;
use App\Support\PlaceScope;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TurnOperationService
{
    protected TurnRepository $repo;
    protected TurnNotificationService $notifier;
    protected TurnPriorityPreloadService $priorityPreloads;

    public function __construct(
        TurnRepository $repo,
        TurnNotificationService $notifier,
        TurnPriorityPreloadService $priorityPreloads
    ) {
        $this->repo = $repo;
        $this->notifier = $notifier;
        $this->priorityPreloads = $priorityPreloads;
    }

    public function buildStationContext(?Station $station): array
    {
        if (!$station) {
            return [
                'station' => null,
                'services_to_generate' => [],
                'services_to_call' => [],
                'services_to_display' => [],
                'ui_mode' => 'admin',
            ];
        }

        $station->loadMissing(['services.followUpServices', 'services.precedingServices']);

        $servicesToGenerate = $station->services
            ->filter(fn ($service) => (bool) $service->pivot->can_generate)
            ->sortBy([
                ['order', 'asc'],
                ['name', 'asc'],
            ])
            ->values()
            ->map(fn ($service) => $this->mapService($service))
            ->all();

        $servicesToCall = $station->services
            ->filter(fn ($service) => (bool) $service->pivot->can_call)
            ->sortBy([
                ['order', 'asc'],
                ['name', 'asc'],
            ])
            ->values()
            ->map(fn ($service) => $this->mapService($service))
            ->all();

        $servicesToDisplay = $station->services
            ->filter(fn ($service) => (bool) $service->pivot->can_display)
            ->sortBy([
                ['order', 'asc'],
                ['name', 'asc'],
            ])
            ->values()
            ->map(fn ($service) => $this->mapService($service))
            ->all();

        return [
            'station' => [
                'id' => $station->id,
                'place_id' => $station->place_id,
                'name' => $station->name,
                'code' => $station->code,
                'type' => $station->type,
                'can_generate_tickets' => $station->can_generate_tickets,
                'can_call_tickets' => $station->can_call_tickets,
                'max_open_turns' => $station->maxOpenTurnsLimit(),
                'show_priority_option' => (bool) ($station->show_priority_option ?? true),
                'issues_super_priority' => $station->issuesSuperPriority(),
                'printer_ip' => $station->printer_ip,
                'status' => $station->status,
                'display_video_url' => $this->resolveDisplayVideoUrl($station->id),
                'print_api_url' => $station->place?->print_api_url,
            ],
            'services_to_generate' => $servicesToGenerate,
            'services_to_call' => $servicesToCall,
            'services_to_display' => $servicesToDisplay,
            'ui_mode' => $this->resolveUiMode($station),
        ];
    }

    public function create(User $user, array $data): Turn
    {
        $station = $this->requireStation($user);

        if (!$station->can_generate_tickets) {
            throw new Exception('Esta estación no puede generar turnos.');
        }

        $service = Service::findOrFail($data['service_id']);
        $this->assertServiceBelongsToPlace($service, $station->place_id);
        $this->assertServiceCanGenerate($station, $service->id);

        $patientDocument = isset($data['patient_document'])
            ? preg_replace('/\D+/', '', (string) $data['patient_document'])
            : null;
        if ($patientDocument === '') {
            $patientDocument = null;
        }
        $parentTurn = null;
        $parentTurnId = isset($data['parent_turn_id']) ? (int) $data['parent_turn_id'] : null;

        // Un servicio puede ser siguiente de otro y a la vez generarse solo en kiosco.
        // El padre solo se exige si el front lo envía (consultorio: generar siguientes).
        if ($parentTurnId) {
            $parentTurn = $this->resolveExplicitParentTurn($station, $service, $parentTurnId);
        }

        $matchedPreload = null;
        $created = DB::transaction(function () use ($station, $service, &$data, $patientDocument, $parentTurn, &$matchedPreload) {
            $parentIsSuper = $parentTurn && $parentTurn->priority === Turn::PRIORITY_SUPER;
            $forceSuper = $station->issuesSuperPriority() || $parentIsSuper;

            $matchedPreload = null;
            if (!$forceSuper) {
                $matchedPreload = $this->priorityPreloads->applyToTurnCreate(
                    (int) $station->place_id,
                    $patientDocument,
                    $data
                );
            }

            if ($forceSuper) {
                $data['priority'] = Turn::PRIORITY_SUPER;
            } else {
                $priority = $data['priority'] ?? Turn::PRIORITY_NORMAL;
                if ($priority === Turn::PRIORITY_SUPER) {
                    throw new Exception('Esta estación no puede generar turnos de superprioridad.');
                }
            }

            $priority = $data['priority'] ?? Turn::PRIORITY_NORMAL;

            return $this->repo->withSequenceLock(
                (int) $station->place_id,
                (int) $service->id,
                $priority,
                function () use ($station, $service, $data, $patientDocument, $parentTurn, &$matchedPreload, $priority) {
                    if ($patientDocument) {
                        $existing = $this->repo->findActiveForPatientService(
                            (int) $station->place_id,
                            (int) $service->id,
                            $patientDocument
                        );
                        if ($existing) {
                            return $existing;
                        }
                    }

                    $sequence = $this->repo->nextDailySequence($station->place_id, $service->id, $priority);
                    $ticketPrefix = Turn::ticketPrefixFor((string) $service->prefix, $priority);
                    $ticketNumber = sprintf('%s-%03d', $ticketPrefix, $sequence);

                    $turn = $this->repo->create([
                        'place_id' => $station->place_id,
                        'service_id' => $service->id,
                        'station_id' => $station->id,
                        'parent_turn_id' => $parentTurn?->id,
                        'ticket_number' => $ticketNumber,
                        'daily_sequence' => $sequence,
                        'patient_name' => $data['patient_name'] ?? null,
                        'patient_document' => $patientDocument,
                        'patient_phone' => $data['patient_phone'] ?? null,
                        'patient_company' => $data['patient_company'] ?? null,
                        'priority' => $priority,
                        'status' => Turn::STATUS_WAITING,
                        'qr_token' => Str::uuid()->toString(),
                    ]);

                    if ($matchedPreload) {
                        $this->priorityPreloads->markUsed($matchedPreload, (int) $turn->id);
                    }

                    return $turn;
                }
            );
        });

        $wasCreatedNow = $created->wasRecentlyCreated;
        if ($wasCreatedNow) {
            $this->notifier->notifyRemindersForQueue((int) $created->place_id, (int) $created->service_id);
        }

        return $created;
    }

    public function queue(User $user, ?int $serviceId = null)
    {
        $station = $this->requireStation($user);

        if (!$station->can_call_tickets) {
            throw new Exception('Esta estación no puede consultar colas de llamado.');
        }

        $serviceIds = $this->callableServiceIds($station, $serviceId);

        $queue = $this->repo->queueForServices($serviceIds, $station->place_id);
        $busyByDocument = $this->repo->openTurnsByPatientDocument($station->place_id);

        return $queue->map(function ($turn) use ($busyByDocument) {
            $document = trim((string) ($turn->patient_document ?? ''));
            $busy = $document !== '' ? ($busyByDocument[$document] ?? null) : null;

            if ($busy && (int) $busy->id !== (int) $turn->id) {
                $turn->setAttribute('busy_elsewhere', [
                    'ticket_number' => $busy->ticket_number,
                    'station_name' => $busy->calledByStation?->name ?? $busy->station?->name,
                    'service_name' => $busy->service?->name,
                    'status' => $busy->status,
                ]);
            } else {
                $turn->setAttribute('busy_elsewhere', null);
            }

            return $turn;
        })->values();
    }

    public function callNext(User $user, ?int $serviceId = null): Turn
    {
        $station = $this->requireStation($user);

        if (!$station->can_call_tickets) {
            throw new Exception('Esta estación no puede llamar turnos.');
        }

        $this->assertStationHasCallCapacity($station);

        $callableIds = $this->callableServiceIds($station);

        if ($serviceId === null && count($callableIds) > 1) {
            throw new Exception('Debe seleccionar un servicio para llamar el siguiente turno.');
        }

        $serviceIds = $this->callableServiceIds($station, $serviceId);

        $updated = DB::transaction(function () use ($station, $serviceIds) {
            $turn = $this->repo->nextWaitingTurn($serviceIds, $station->place_id);

            if (!$turn) {
                throw new Exception(
                    'No hay turnos en espera disponibles. '
                    . 'Si hay gente en cola, puede estar siendo atendida ahora en otro módulo.'
                );
            }

            return $this->repo->update($turn, [
                'status' => Turn::STATUS_CALLED,
                'called_by_station_id' => $station->id,
                'called_at' => now(),
                'silent_call' => false,
            ]);
        });

        $this->notifier->notifyCalled($updated);
        $this->notifier->notifyRemindersForQueue((int) $updated->place_id, (int) $updated->service_id);

        return $updated;
    }

    public function callByNumber(User $user, string $ticketNumber, bool $silent = false): Turn
    {
        $station = $this->requireStation($user);

        if (!$station->can_call_tickets) {
            throw new Exception('Esta estación no puede llamar turnos.');
        }

        $turn = $this->repo->findByTicketNumber($station->place_id, $ticketNumber);

        if (!$turn) {
            throw new Exception('No se encontró el turno indicado.');
        }

        $this->assertServiceCanCall($station, $turn->service_id);

        $callableStatuses = [Turn::STATUS_WAITING, Turn::STATUS_NO_SHOW];

        if (!in_array($turn->status, $callableStatuses, true)) {
            throw new Exception('El turno no puede ser llamado manualmente. Solo se permiten turnos en espera o no asistió.');
        }

        $this->assertPatientNotBusyElsewhere($turn);

        $this->assertStationHasCallCapacity($station);

        $updated = $this->repo->update($turn, [
            'status' => Turn::STATUS_CALLED,
            'called_by_station_id' => $station->id,
            'called_at' => now(),
            'completed_at' => null,
            'silent_call' => $silent,
        ]);

        $this->notifier->notifyCalled($updated);
        $this->notifier->notifyRemindersForQueue((int) $updated->place_id, (int) $updated->service_id);

        return $updated;
    }

    public function recall(User $user, int $turnId): Turn
    {
        $turn = $this->assertTurnCallableByStation($user, $turnId);

        if (!in_array($turn->status, [Turn::STATUS_CALLED, Turn::STATUS_RECALLED, Turn::STATUS_IN_PROGRESS], true)) {
            throw new Exception('El turno no puede ser rellamado.');
        }

        $updated = $this->repo->update($turn, [
            'status' => Turn::STATUS_RECALLED,
            'called_by_station_id' => $user->station_id,
            'called_at' => now(),
        ]);

        $this->notifier->notifyCalled($updated);

        return $updated;
    }

    public function start(User $user, int $turnId): Turn
    {
        $turn = $this->assertTurnCallableByStation($user, $turnId);

        if (!in_array($turn->status, [Turn::STATUS_CALLED, Turn::STATUS_RECALLED], true)) {
            throw new Exception('El turno debe estar llamado para iniciar atención.');
        }

        return $this->repo->update($turn, [
            'status' => Turn::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);
    }

    public function complete(User $user, int $turnId): Turn
    {
        $turn = $this->assertTurnCallableByStation($user, $turnId);

        if ($turn->status !== Turn::STATUS_IN_PROGRESS) {
            throw new Exception('El turno debe estar en atención para finalizar.');
        }

        $this->assertFollowUpsGeneratedBeforeComplete($user, $turn);

        $updated = $this->repo->update($turn, [
            'status' => Turn::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $this->notifier->notifyRatingInvite($updated);
        $this->notifier->notifyRemindersForQueue((int) $updated->place_id, (int) $updated->service_id);

        return $updated;
    }

    /**
     * Si el servicio tiene siguientes y esta estación puede generarlos,
     * exige al menos un turno siguiente generado antes de finalizar.
     */
    protected function assertFollowUpsGeneratedBeforeComplete(User $user, Turn $turn): void
    {
        $station = $this->requireStation($user);
        $requiredIds = $this->requiredFollowUpServiceIdsForStation($station, $turn);
        if (empty($requiredIds)) {
            return;
        }

        $generatedIds = $this->resolveGeneratedFollowUpServiceIds($turn, $requiredIds);
        if (empty($generatedIds)) {
            throw new Exception(
                'Debe generar al menos un turno siguiente antes de finalizar la atención.'
            );
        }
    }

    /**
     * @return list<int>
     */
    protected function requiredFollowUpServiceIdsForStation(Station $station, Turn $turn): array
    {
        $service = $turn->service;
        if (!$service) {
            return [];
        }

        $service->loadMissing('followUpServices');
        $followUpIds = $service->follow_up_service_ids;
        if (empty($followUpIds)) {
            return [];
        }

        $generatableIds = $station->services
            ->filter(fn ($item) => (bool) $item->pivot->can_generate)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_intersect(
            array_map('intval', $followUpIds),
            $generatableIds
        ));
    }

    /**
     * Turnos siguientes ya generados: por parent_turn_id o, como respaldo,
     * mismo documento del paciente el mismo día.
     *
     * @param  list<int>|null  $serviceIds
     * @return list<int>
     */
    public function resolveGeneratedFollowUpServiceIds(Turn $turn, ?array $serviceIds = null): array
    {
        $serviceIds = $serviceIds !== null
            ? array_values(array_unique(array_map('intval', $serviceIds)))
            : null;

        $byParent = Turn::query()
            ->where('parent_turn_id', $turn->id)
            ->when($serviceIds !== null, fn ($q) => $q->whereIn('service_id', $serviceIds))
            ->pluck('service_id');

        $ids = $byParent->map(fn ($id) => (int) $id)->all();

        $document = trim((string) ($turn->patient_document ?? ''));
        if ($document !== '') {
            $followUpIds = $serviceIds;
            if ($followUpIds === null) {
                $turn->loadMissing('service.followUpServices');
                $followUpIds = array_map('intval', $turn->service?->follow_up_service_ids ?? []);
            }

            if (!empty($followUpIds)) {
                $byDocument = Turn::query()
                    ->where('place_id', $turn->place_id)
                    ->whereIn('service_id', $followUpIds)
                    ->whereRaw('TRIM(patient_document) = ?', [$document])
                    ->whereDate('created_at', Carbon::today())
                    ->where('id', '!=', $turn->id)
                    ->whereNotIn('status', [Turn::STATUS_CANCELLED, Turn::STATUS_NO_SHOW])
                    ->pluck('service_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $ids = array_merge($ids, $byDocument);
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Vincula un siguiente al turno en atención cuando el front envía parent_turn_id.
     */
    protected function resolveExplicitParentTurn(Station $station, Service $service, int $parentTurnId): Turn
    {
        $parentTurn = Turn::with(['service.followUpServices'])->find($parentTurnId);

        if (!$parentTurn) {
            throw new Exception('El turno principal indicado no existe.');
        }

        if ((int) $parentTurn->place_id !== (int) $station->place_id) {
            throw new Exception('El turno principal no pertenece a esta sede.');
        }

        if (in_array($parentTurn->status, [Turn::STATUS_CANCELLED, Turn::STATUS_NO_SHOW], true)) {
            throw new Exception('El turno principal está cancelado o marcado como no asistió.');
        }

        $parentTurn->service?->loadMissing('followUpServices');
        $followUpIds = array_map('intval', $parentTurn->service?->follow_up_service_ids ?? []);

        if (!in_array((int) $service->id, $followUpIds, true)) {
            throw new Exception(
                'Este servicio no está configurado como generable después del turno en atención.'
            );
        }

        return $parentTurn;
    }

    public function noShow(User $user, int $turnId, ?string $note = null): Turn
    {
        $station = $this->requireStation($user);
        $turn = $this->assertTurnCallableByStation($user, $turnId);

        if (!in_array($turn->status, [Turn::STATUS_CALLED, Turn::STATUS_RECALLED], true)) {
            throw new Exception('Solo se puede marcar no asistió en turnos llamados.');
        }

        $note = trim((string) $note);

        $updated = $this->repo->update($turn, [
            'status' => Turn::STATUS_NO_SHOW,
            'completed_at' => now(),
            'no_show_note' => $note !== '' ? mb_substr($note, 0, 500) : null,
            'no_show_station_id' => $station->id,
        ]);

        $this->notifier->notifyRemindersForQueue((int) $updated->place_id, (int) $updated->service_id);

        return $updated->load('noShowStation');
    }

    public function cancel(User $user, int $turnId): Turn
    {
        $turn = $this->repo->findById($turnId);
        $station = $this->requireStation($user);

        if ($turn->place_id !== $station->place_id) {
            throw new Exception('El turno no pertenece a la sede de la estación.');
        }

        if (in_array($turn->status, [Turn::STATUS_COMPLETED, Turn::STATUS_CANCELLED, Turn::STATUS_NO_SHOW], true)) {
            throw new Exception('El turno no puede ser cancelado.');
        }

        $updated = $this->repo->update($turn, [
            'status' => Turn::STATUS_CANCELLED,
            'completed_at' => now(),
        ]);

        $this->notifier->notifyRemindersForQueue((int) $updated->place_id, (int) $updated->service_id);

        return $updated;
    }

    public function track(string $qrToken): array
    {
        $turn = $this->repo->findByQrToken($qrToken);
        $company = $turn->place?->company;

        $ratingScore = null;
        $ratedAt = null;
        $ratingComment = null;

        try {
            $ratingScore = $turn->getAttribute('rating');
            $ratedAt = $turn->getAttribute('rated_at');
            $ratingComment = $turn->getAttribute('rating_comment');
        } catch (\Throwable $e) {
            // Columnas de calificación aún no migradas
        }

        $alreadyRated = filled($ratedAt) || (is_numeric($ratingScore) && (int) $ratingScore > 0);
        $canRate = $turn->status === Turn::STATUS_COMPLETED && ! $alreadyRated;

        return [
            'turn' => $turn,
            'waiting_ahead' => $turn->status === Turn::STATUS_WAITING
                ? $this->repo->countWaitingAhead($turn)
                : 0,
            'can_rate' => $canRate,
            'rating' => $alreadyRated ? [
                'score' => (int) $ratingScore,
                'comment' => $ratingComment,
                'rated_at' => $ratedAt ? optional($ratedAt)->toIso8601String() : null,
            ] : null,
            'company' => $this->mapTrackCompany($company),
            'place' => $turn->place ? [
                'id' => $turn->place->id,
                'name' => $turn->place->name,
            ] : null,
        ];
    }

    public function rateByQrToken(string $qrToken, int $score, ?string $comment = null): array
    {
        $turn = $this->repo->findByQrToken($qrToken);

        if ($turn->status !== Turn::STATUS_COMPLETED) {
            throw new Exception('La calificación estará disponible cuando finalice su atención.');
        }

        if ($turn->rated_at || $turn->rating) {
            throw new Exception('Este turno ya fue calificado. Gracias por su opinión.');
        }

        if ($score < 1 || $score > 5) {
            throw new Exception('La calificación debe ser entre 1 y 5.');
        }

        $comment = filled($comment) ? trim(mb_substr((string) $comment, 0, 500)) : null;

        $updated = $this->repo->update($turn, [
            'rating' => $score,
            'rating_comment' => $comment,
            'rated_at' => now(),
        ]);

        return $this->track($updated->qr_token);
    }

    public function find(int $id): Turn
    {
        return $this->repo->findById($id);
    }

    public function display(User $user, int $limit = 10)
    {
        $station = $this->requireStation($user);
        $serviceIds = $station->services
            ->filter(fn ($service) => (bool) $service->pivot->can_display)
            ->pluck('id')
            ->all();

        if (empty($serviceIds)) {
            throw new Exception('Esta estación no tiene servicios configurados para visualización.');
        }

        return $this->repo->recentlyCalled($serviceIds, $station->place_id, $limit);
    }

    public function noShowsToday(User $user)
    {
        $station = $this->requireStation($user);

        if (!$station->can_call_tickets) {
            throw new Exception('Esta estación no puede consultar turnos de no asistió.');
        }

        $serviceIds = $station->services
            ->filter(fn ($service) => (bool) $service->pivot->can_call)
            ->pluck('id')
            ->all();

        if (empty($serviceIds)) {
            return [];
        }

        return $this->repo->noShowsToday($serviceIds, $station->place_id);
    }

    /**
     * Sintetiza voz (MP3) vía Google Translate TTS desde el servidor.
     * Evita bloqueos de CORS/autoplay del navegador de la TV.
     */
    public function synthesizeSpeech(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ($text === '') {
            throw new Exception('Texto vacío para sintetizar.');
        }

        if (mb_strlen($text) > 200) {
            $text = mb_substr($text, 0, 200);
        }

        $query = http_build_query([
            'ie' => 'UTF-8',
            'client' => 'tw-ob',
            'tl' => 'es',
            'q' => $text,
        ]);

        $url = 'https://translate.google.com/translate_tts?'.$query;

        $response = \Illuminate\Support\Facades\Http::timeout(12)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                'Accept' => '*/*',
                'Referer' => 'https://translate.google.com/',
            ])
            ->get($url);

        if (!$response->successful() || empty($response->body())) {
            // Reintento con client=gtx
            usleep(250000);
            $queryGtx = http_build_query([
                'ie' => 'UTF-8',
                'client' => 'gtx',
                'tl' => 'es',
                'q' => $text,
            ]);
            $response = \Illuminate\Support\Facades\Http::timeout(12)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                    'Accept' => '*/*',
                    'Referer' => 'https://translate.google.com/',
                ])
                ->get('https://translate.google.com/translate_tts?'.$queryGtx);
        }

        // Un tercer intento breve (Google a veces responde 429/5xx intermitente)
        if (!$response->successful() || empty($response->body())) {
            usleep(500000);
            $response = \Illuminate\Support\Facades\Http::timeout(12)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                    'Accept' => '*/*',
                    'Referer' => 'https://translate.google.com/',
                ])
                ->get($url);
        }

        if (!$response->successful() || empty($response->body())) {
            throw new Exception('No se pudo obtener audio TTS (HTTP '.$response->status().').');
        }

        return $response->body();
    }

    public function currentOpen(User $user)
    {
        $station = $this->requireStation($user);
        $turns = $this->repo->findOpenTurnsForStation($station->id);

        return $turns->map(function (Turn $turn) use ($station) {
            $requiredIds = $this->requiredFollowUpServiceIdsForStation($station, $turn);
            $generatedIds = $this->resolveGeneratedFollowUpServiceIds(
                $turn,
                $requiredIds ?: null
            );
            $turn->setAttribute('generated_follow_up_service_ids', $generatedIds);

            return $turn;
        })->values();
    }

    public function history(User $user, ?string $date = null, ?int $placeId = null)
    {
        $date = $date ?: now()->toDateString();

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new Exception('La fecha debe tener formato YYYY-MM-DD.');
        }

        $placeId = PlaceScope::resolvePlaceId($user, $placeId, 'turns.allPlaces');

        return $this->repo->findByFilters(null, $placeId, $date);
    }

    public function lookupPatient(User $user, string $document): ?array
    {
        $document = preg_replace('/\D+/', '', $document) ?? '';
        $document = trim($document);
        if ($document === '' || strlen($document) < 5) {
            return null;
        }

        $placeId = PlaceScope::sessionPlaceId($user);
        if (! $placeId && $user->place_id) {
            $placeId = (int) $user->place_id;
        }
        if (! $placeId) {
            return null;
        }

        $turnsToday = $this->repo->findTodayByDocument($document, (int) $placeId);
        $profile = $this->repo->findLatestPatientProfile(
            $document,
            (int) $placeId,
            $user->company_id ? (int) $user->company_id : null
        ) ?: [];

        foreach ($turnsToday as $turn) {
            if (empty($profile['patient_name']) && filled($turn->patient_name)) {
                $profile['patient_name'] = trim((string) $turn->patient_name);
            }
            if (empty($profile['patient_phone']) && filled($turn->patient_phone)) {
                $profile['patient_phone'] = preg_replace('/\D+/', '', (string) $turn->patient_phone) ?: null;
            }
            if (empty($profile['patient_company']) && filled($turn->patient_company)) {
                $profile['patient_company'] = trim((string) $turn->patient_company);
            }
        }

        $preload = null;
        try {
            $preload = $this->priorityPreloads->lookup($user, $document, (int) $placeId);
        } catch (Exception $e) {
            $preload = null;
        }

        if ($preload) {
            if (empty($profile['patient_name']) && filled($preload->patient_name)) {
                $profile['patient_name'] = trim((string) $preload->patient_name);
            }
            if (empty($profile['patient_phone']) && filled($preload->patient_phone)) {
                $profile['patient_phone'] = preg_replace('/\D+/', '', (string) $preload->patient_phone) ?: null;
            }
            if (empty($profile['patient_company']) && filled($preload->patient_company)) {
                $profile['patient_company'] = trim((string) $preload->patient_company);
            }
        }

        return [
            'patient_name' => $profile['patient_name'] ?? null,
            'patient_phone' => $profile['patient_phone'] ?? null,
            'patient_company' => $profile['patient_company'] ?? null,
            'turns_today' => $turnsToday->values(),
            'priority' => $preload?->priority,
            'priority_matched' => (bool) $preload,
        ];
    }

    protected function resolveParentTurn(
        Station $station,
        Service $service,
        ?string $patientDocument,
        ?int $parentTurnId
    ): ?Turn {
        $requiredParentIds = $service->parent_service_ids;

        if (empty($requiredParentIds)) {
            return null;
        }

        if ($parentTurnId) {
            $parentTurn = Turn::query()->find($parentTurnId);

            if (!$parentTurn) {
                throw new Exception('El turno principal indicado no existe.');
            }

            if ((int) $parentTurn->place_id !== (int) $station->place_id) {
                throw new Exception('El turno principal no pertenece a esta sede.');
            }

            if (!in_array((int) $parentTurn->service_id, $requiredParentIds, true)) {
                throw new Exception(
                    'Este servicio no está configurado como generable después del turno en atención.'
                );
            }

            if (in_array($parentTurn->status, [Turn::STATUS_CANCELLED, Turn::STATUS_NO_SHOW], true)) {
                throw new Exception('El turno principal está cancelado o marcado como no asistió.');
            }

            return $parentTurn;
        }

        if (!$patientDocument) {
            throw new Exception('Debe indicar el documento del paciente para generar este turno.');
        }

        return $this->repo->findActiveParentTurn(
            (int) $station->place_id,
            $requiredParentIds,
            $patientDocument
        );
    }

    protected function requireStation(User $user): Station
    {
        if (!$user->station_id) {
            throw new Exception('El usuario no tiene una estación asignada.');
        }

        $station = Station::with('services')->find($user->station_id);

        if (!$station || $station->status !== 'activo') {
            throw new Exception('La estación asignada no está activa.');
        }

        return $station;
    }

    protected function assertTurnCallableByStation(User $user, int $turnId): Turn
    {
        $station = $this->requireStation($user);
        $turn = $this->repo->findById($turnId);

        if ($turn->place_id !== $station->place_id) {
            throw new Exception('El turno no pertenece a la sede de la estación.');
        }

        $this->assertServiceCanCall($station, $turn->service_id);

        return $turn;
    }

    protected function callableServiceIds(Station $station, ?int $serviceId = null): array
    {
        $serviceIds = $station->services
            ->filter(fn ($service) => (bool) $service->pivot->can_call)
            ->pluck('id')
            ->all();

        if ($serviceId) {
            if (!in_array($serviceId, $serviceIds, true)) {
                throw new Exception('La estación no puede llamar turnos de ese servicio.');
            }

            return [$serviceId];
        }

        if (empty($serviceIds)) {
            throw new Exception('La estación no tiene servicios configurados para llamado.');
        }

        return $serviceIds;
    }

    protected function assertServiceBelongsToPlace(Service $service, int $placeId): void
    {
        if ($service->place_id !== $placeId) {
            throw new Exception('El servicio no pertenece a la sede de la estación.');
        }
    }

    protected function assertServiceCanGenerate(Station $station, int $serviceId): void
    {
        $allowed = $station->services->first(function ($service) use ($serviceId) {
            return $service->id === $serviceId && (bool) $service->pivot->can_generate;
        });

        if (!$allowed) {
            throw new Exception('La estación no puede generar turnos para este servicio.');
        }
    }

    protected function assertServiceCanCall(Station $station, int $serviceId): void
    {
        $allowed = $station->services->first(function ($service) use ($serviceId) {
            return $service->id === $serviceId && (bool) $service->pivot->can_call;
        });

        if (!$allowed) {
            throw new Exception('La estación no puede llamar turnos de este servicio.');
        }
    }

    protected function assertPatientNotBusyElsewhere(Turn $turn): void
    {
        $document = trim((string) ($turn->patient_document ?? ''));
        if ($document === '') {
            return;
        }

        $busyByDocument = $this->repo->openTurnsByPatientDocument((int) $turn->place_id);
        $busy = $busyByDocument[$document] ?? null;

        if (!$busy || (int) $busy->id === (int) $turn->id) {
            return;
        }

        $stationName = $busy->calledByStation?->name ?? $busy->station?->name ?? 'otro módulo';
        $serviceName = $busy->service?->name ? " ({$busy->service->name})" : '';
        $stateLabel = match ($busy->status) {
            Turn::STATUS_IN_PROGRESS => 'en atención',
            Turn::STATUS_RECALLED => 'rellamada',
            default => 'llamada',
        };

        throw new Exception(
            "Esta persona está {$stateLabel} ahora en {$stationName}{$serviceName}, "
            . "turno {$busy->ticket_number}. Espere a que finalice para llamarla en este servicio."
        );
    }

    protected function assertStationHasCallCapacity(Station $station): void
    {
        $max = $station->maxOpenTurnsLimit();
        $openCount = $this->repo->countOpenTurnsForStation($station->id);

        if ($openCount >= $max) {
            throw new Exception(
                $max === 1
                    ? 'Debe finalizar el turno actual antes de llamar otro. Use Finalizar, No asistió o Cancelar.'
                    : "Esta estación puede atender hasta {$max} turnos a la vez. Libere un cupo (Finalizar, No asistió o Cancelar) para llamar otro."
            );
        }
    }

    protected function resolveDisplayVideoUrl(int $stationId): ?string
    {
        $file = File::where('name', 'DISPLAY_VIDEO')
            ->where('model_name', 'stations')
            ->where('model_id', $stationId)
            ->latest('id')
            ->first();

        return $file?->full_url;
    }

    protected function mapTrackCompany(?Company $company): ?array
    {
        if (!$company) {
            return null;
        }

        $logo = File::where('name', 'PHOTO_LOGO')
            ->where('model_name', 'companies')
            ->where('model_id', $company->id)
            ->latest('id')
            ->first();

        $configuration = $company->configuration;
        if (is_string($configuration)) {
            $decoded = json_decode($configuration, true);
            $configuration = is_array($decoded) ? $decoded : [];
        }

        return [
            'id' => $company->id,
            'name' => $company->name,
            'logo_url' => $logo?->full_url,
            'template_color' => $configuration['template_color']
                ?? $configuration['color']
                ?? null,
            'domain' => $configuration['domain'] ?? null,
        ];
    }

    protected function mapService(Service $service): array
    {
        $service->loadMissing(['followUpServices', 'precedingServices']);

        return [
            'id' => $service->id,
            'name' => $service->name,
            'code' => $service->code,
            'prefix' => $service->prefix,
            'order' => (int) ($service->order ?? 0),
            'parent_service_ids' => $service->parent_service_ids,
            'follow_up_service_ids' => $service->follow_up_service_ids,
            'follow_up_services' => $service->followUpServices
                ->sortBy([
                    ['order', 'asc'],
                    ['name', 'asc'],
                ])
                ->values()
                ->map(fn ($followUp) => [
                    'id' => $followUp->id,
                    'name' => $followUp->name,
                    'code' => $followUp->code,
                    'prefix' => $followUp->prefix,
                ])
                ->all(),
            'display_order' => $service->pivot->display_order,
        ];
    }

    protected function resolveUiMode(Station $station): string
    {
        if ($station->type === Station::TYPE_REGISTER) {
            return 'register';
        }

        if ($station->type === Station::TYPE_DISPLAY) {
            return 'display';
        }

        if ($station->can_generate_tickets && !$station->can_call_tickets) {
            return 'kiosk';
        }

        if ($station->can_call_tickets && !$station->can_generate_tickets) {
            return 'consultory';
        }

        if ($station->can_generate_tickets && $station->can_call_tickets) {
            return 'hybrid';
        }

        return 'admin';
    }
}
