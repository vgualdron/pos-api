<?php

namespace App\Repositories;

use App\Events\DisplayTurnUpdated;
use App\Models\Turn;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TurnRepository
{
    public function findById($id)
    {
        return Turn::findOrFail($id);
    }

    public function findByQrToken(string $token)
    {
        $token = trim(urldecode($token));
        // Enlaces de SMS/WhatsApp a veces agregan puntuación al final
        $token = rtrim($token, " \t\n\r\0\x0B.,;:)>]}\"'");

        $turn = Turn::with([
            'service',
            'place.company',
            'calledByStation',
            'station',
        ])->where('qr_token', $token)->first();

        if (! $turn) {
            throw new \Exception('No se encontró el turno. Verifique el enlace o escanee nuevamente el código QR.');
        }

        return $turn;
    }

    public function findActiveParentTurn(int $placeId, int|array $parentServiceIds, string $patientDocument): ?Turn
    {
        $normalized = preg_replace('/\D+/', '', $patientDocument) ?? '';
        $normalized = trim($normalized);
        $parentServiceIds = array_values(array_unique(array_map('intval', (array) $parentServiceIds)));

        if ($normalized === '' || empty($parentServiceIds)) {
            return null;
        }

        [$start, $end] = $this->todayBounds();

        return Turn::query()
            ->where('place_id', $placeId)
            ->whereIn('service_id', $parentServiceIds)
            ->where('patient_document', $normalized)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->whereNotIn('status', [Turn::STATUS_CANCELLED, Turn::STATUS_NO_SHOW])
            ->latest('id')
            ->first();
    }

    /**
     * Serializa la asignación de número diario (varios kioscos / doble clic).
     *
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    public function withSequenceLock(int $placeId, int $serviceId, string $priority, callable $callback)
    {
        $lockName = sprintf(
            'turn-seq-%d-%d-%s-%s',
            $placeId,
            $serviceId,
            $this->sequenceBucket($priority),
            Carbon::today()->toDateString()
        );

        $got = DB::selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lockName]);
        if (!(int) ($got->acquired ?? 0)) {
            throw new \Exception('No se pudo asignar el número de turno. Intente de nuevo.');
        }

        try {
            return $callback();
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
        }
    }

    public function findActiveForPatientService(int $placeId, int $serviceId, string $patientDocument): ?Turn
    {
        $normalized = trim($patientDocument);
        if ($normalized === '') {
            return null;
        }

        [$start, $end] = $this->todayBounds();

        return Turn::query()
            ->where('place_id', $placeId)
            ->where('service_id', $serviceId)
            ->where('patient_document', $normalized)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->whereIn('status', Turn::ACTIVE_STATUSES)
            ->lockForUpdate()
            ->orderByDesc('id')
            ->first();
    }

    public function nextDailySequence(int $placeId, int $serviceId, string $priority = Turn::PRIORITY_NORMAL): int
    {
        [$start, $end] = $this->todayBounds();

        $query = Turn::where('place_id', $placeId)
            ->where('service_id', $serviceId)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end);

        $bucket = $this->sequenceBucket($priority);
        if ($bucket === 'super') {
            $query->where('priority', Turn::PRIORITY_SUPER);
        } elseif ($bucket === 'priority') {
            $query->where('priority', '!=', Turn::PRIORITY_NORMAL)
                ->where('priority', '!=', Turn::PRIORITY_SUPER);
        } else {
            $query->where(function ($inner) {
                $inner->where('priority', Turn::PRIORITY_NORMAL)->orWhereNull('priority');
            });
        }

        $lastSequence = $query->max('daily_sequence');

        return ((int) $lastSequence) + 1;
    }

    protected function sequenceBucket(string $priority): string
    {
        if ($priority === Turn::PRIORITY_SUPER) {
            return 'super';
        }

        return Turn::isPriorityTurn($priority) ? 'priority' : 'normal';
    }

    public function create(array $data): Turn
    {
        $turn = Turn::create($data);

        if (!empty($turn->place_id)) {
            event(new DisplayTurnUpdated((int) $turn->place_id));
        }

        return $turn;
    }

    public function update(Turn $turn, array $data): Turn
    {
        $turn->update($data);
        $fresh = $turn->fresh(['service', 'station', 'calledByStation', 'parentTurn', 'childTurns']);

        $affectsDisplay = array_key_exists('status', $data) || array_key_exists('called_at', $data);
        if ($affectsDisplay && !$fresh->silent_call) {
            event(new DisplayTurnUpdated((int) $fresh->place_id));
        }

        return $fresh;
    }

    public function queueForServices(array $serviceIds, int $placeId)
    {
        return Turn::with(['service', 'station'])
            ->where('place_id', $placeId)
            ->whereIn('service_id', $serviceIds)
            ->where('status', Turn::STATUS_WAITING)
            ->whereDate('created_at', Carbon::today())
            ->orderByRaw(Turn::priorityQueueFieldSql())
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Turnos abiertos hoy (llamado / en atención), indexados por documento.
     *
     * @return array<string, Turn>
     */
    public function openTurnsByPatientDocument(int $placeId): array
    {
        $turns = Turn::with(['calledByStation', 'service'])
            ->where('place_id', $placeId)
            ->whereIn('status', [Turn::STATUS_CALLED, Turn::STATUS_RECALLED, Turn::STATUS_IN_PROGRESS])
            ->whereDate('created_at', Carbon::today())
            ->whereNotNull('patient_document')
            ->where('patient_document', '!=', '')
            ->orderByDesc('called_at')
            ->get();

        $byDocument = [];
        foreach ($turns as $turn) {
            $document = trim((string) $turn->patient_document);
            if ($document === '' || isset($byDocument[$document])) {
                continue;
            }
            $byDocument[$document] = $turn;
        }

        return $byDocument;
    }

    public function nextWaitingTurn(array $serviceIds, int $placeId): ?Turn
    {
        $busyDocuments = array_keys($this->openTurnsByPatientDocument($placeId));

        $query = Turn::where('place_id', $placeId)
            ->whereIn('service_id', $serviceIds)
            ->where('status', Turn::STATUS_WAITING)
            ->whereDate('created_at', Carbon::today())
            ->orderByRaw(Turn::priorityQueueFieldSql())
            ->orderBy('created_at');

        if (!empty($busyDocuments)) {
            $query->where(function ($inner) use ($busyDocuments) {
                $inner->whereNull('patient_document')
                    ->orWhereRaw("TRIM(patient_document) = ''")
                    ->orWhereNotIn(
                        DB::raw('TRIM(patient_document)'),
                        $busyDocuments
                    );
            });
        }

        return $query->lockForUpdate()->first();
    }

    public function findByTicketNumber(int $placeId, string $ticketNumber): ?Turn
    {
        return Turn::where('place_id', $placeId)
            ->where('ticket_number', $ticketNumber)
            ->whereDate('created_at', Carbon::today())
            ->latest('id')
            ->first();
    }

    public function noShowsToday(array $serviceIds, int $placeId)
    {
        if (empty($serviceIds)) {
            return collect();
        }

        return Turn::with(['service', 'calledByStation', 'noShowStation'])
            ->where('place_id', $placeId)
            ->whereIn('service_id', $serviceIds)
            ->where('status', Turn::STATUS_NO_SHOW)
            ->whereDate('created_at', Carbon::today())
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->get();
    }

    public function recentlyCalled(array $serviceIds, int $placeId, int $limit = 10)
    {
        return Turn::with(['service', 'calledByStation'])
            ->where('place_id', $placeId)
            ->whereIn('service_id', $serviceIds)
            ->whereIn('status', [Turn::STATUS_CALLED, Turn::STATUS_RECALLED, Turn::STATUS_IN_PROGRESS])
            ->where('silent_call', false)
            ->whereDate('created_at', Carbon::today())
            ->orderByDesc('called_at')
            ->limit($limit)
            ->get();
    }

    public function findOpenTurnsForStation(int $stationId)
    {
        return Turn::with(['service', 'calledByStation', 'childTurns'])
            ->where('called_by_station_id', $stationId)
            ->whereIn('status', [Turn::STATUS_CALLED, Turn::STATUS_RECALLED, Turn::STATUS_IN_PROGRESS])
            ->whereDate('created_at', Carbon::today())
            ->orderBy('called_at')
            ->orderBy('id')
            ->get();
    }

    public function countOpenTurnsForStation(int $stationId): int
    {
        return Turn::where('called_by_station_id', $stationId)
            ->whereIn('status', [Turn::STATUS_CALLED, Turn::STATUS_RECALLED, Turn::STATUS_IN_PROGRESS])
            ->whereDate('created_at', Carbon::today())
            ->count();
    }

    public function findOpenTurnForStation(int $stationId): ?Turn
    {
        return $this->findOpenTurnsForStation($stationId)->last();
    }

    /**
     * Personas en espera por delante en la consulta QR.
     * Solo el mismo prefijo de tiquete (LAB vs LABP se ven como colas distintas).
     * No cambia el orden real de llamado en consultorio.
     */
    public function countWaitingAhead(Turn $turn): int
    {
        $prefix = Turn::ticketPrefixOf($turn->ticket_number);
        if ($prefix === '') {
            return 0;
        }

        return Turn::where('place_id', $turn->place_id)
            ->where('service_id', $turn->service_id)
            ->where('status', Turn::STATUS_WAITING)
            ->whereDate('created_at', Carbon::today())
            ->where('id', '!=', $turn->id)
            ->where('ticket_number', 'like', $prefix.'-%')
            ->where(function ($query) use ($turn) {
                $query->where('created_at', '<', $turn->created_at)
                    ->orWhere(function ($inner) use ($turn) {
                        $inner->where('created_at', $turn->created_at)
                            ->where('id', '<', $turn->id);
                    });
            })
            ->count();
    }

    public function findByFilters(?int $companyId, ?int $placeId, string $date)
    {
        $query = Turn::with(['service', 'station', 'calledByStation', 'place'])
            ->whereDate('created_at', $date)
            ->orderByRaw('started_at IS NULL')
            ->orderBy('started_at');

        if ($placeId) {
            $query->where('place_id', $placeId);
        } elseif ($companyId) {
            $query->whereHas('place', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }

        return $query->get();
    }

    public function findLatestPatientProfile(string $document, int $placeId, ?int $companyId = null): ?array
    {
        $normalized = preg_replace('/\D+/', '', $document) ?? '';
        $normalized = trim($normalized);
        if ($normalized === '' || strlen($normalized) < 5) {
            return null;
        }

        $query = Turn::query()
            ->select(['id', 'place_id', 'patient_name', 'patient_phone', 'patient_company'])
            ->where('patient_document', $normalized)
            ->orderByDesc('id')
            ->limit(5);

        $turns = (clone $query)->where('place_id', $placeId)->get();

        if ($turns->isEmpty() && $companyId) {
            $turns = (clone $query)
                ->whereIn('place_id', function ($placeQuery) use ($companyId) {
                    $placeQuery->select('id')->from('places')->where('company_id', $companyId);
                })
                ->get();
        }

        if ($turns->isEmpty()) {
            return null;
        }

        $profile = [
            'patient_name' => null,
            'patient_phone' => null,
            'patient_company' => null,
        ];

        foreach ($turns as $turn) {
            if (! $profile['patient_name'] && filled($turn->patient_name)) {
                $profile['patient_name'] = trim((string) $turn->patient_name);
            }
            if (! $profile['patient_phone'] && filled($turn->patient_phone)) {
                $profile['patient_phone'] = preg_replace('/\D+/', '', (string) $turn->patient_phone) ?: null;
            }
            if (! $profile['patient_company'] && filled($turn->patient_company)) {
                $profile['patient_company'] = trim((string) $turn->patient_company);
            }
            if ($profile['patient_name'] && $profile['patient_phone'] && $profile['patient_company']) {
                break;
            }
        }

        if (! $profile['patient_name'] && ! $profile['patient_phone'] && ! $profile['patient_company']) {
            return null;
        }

        return $profile;
    }

    public function findTodayByDocument(string $document, int $placeId)
    {
        $normalized = preg_replace('/\D+/', '', $document) ?? '';
        $normalized = trim($normalized);
        if ($normalized === '' || strlen($normalized) < 5) {
            return collect();
        }

        [$start, $end] = $this->todayBounds();

        return Turn::query()
            ->select([
                'id',
                'service_id',
                'ticket_number',
                'status',
                'qr_token',
                'patient_name',
                'patient_document',
                'patient_phone',
                'patient_company',
                'created_at',
            ])
            ->with(['service:id,name'])
            ->where('place_id', $placeId)
            ->where('patient_document', $normalized)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->where('status', '!=', Turn::STATUS_CANCELLED)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function todayBounds(): array
    {
        $start = Carbon::today()->startOfDay();

        return [$start, $start->copy()->addDay()];
    }
}
