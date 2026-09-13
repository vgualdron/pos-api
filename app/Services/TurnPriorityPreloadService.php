<?php

namespace App\Services;

use App\Models\Place;
use App\Models\Turn;
use App\Models\TurnPriorityPreload;
use App\Models\User;
use App\Repositories\TurnPriorityPreloadRepository;
use App\Support\PlaceScope;
use Exception;
use Illuminate\Support\Facades\DB;

class TurnPriorityPreloadService
{
    public function __construct(protected TurnPriorityPreloadRepository $repo)
    {
    }

    public function list(User $user, int $placeId, string $date)
    {
        $this->assertUserCanAccessPlace($user, $placeId);

        return $this->repo->listByPlaceAndDate($placeId, $date);
    }

    public function find(int $id): TurnPriorityPreload
    {
        return $this->repo->findById($id);
    }

    public function create(User $user, array $data): TurnPriorityPreload
    {
        $this->assertUserCanAccessPlace($user, (int) $data['place_id']);

        $payload = $this->normalizeRow($data);
        $payload['created_by'] = $user->id;
        $payload['status'] = TurnPriorityPreload::STATUS_PENDING;

        $existing = $this->repo->findPendingMatch(
            (int) $payload['place_id'],
            $payload['patient_document'],
            $payload['scheduled_date']
        );

        if ($existing) {
            throw new Exception('Ya existe un registro pendiente con ese documento para la fecha y sede.');
        }

        // También bloquear si existe used/cancelled mismo unique key
        $dup = TurnPriorityPreload::where('place_id', $payload['place_id'])
            ->whereDate('scheduled_date', $payload['scheduled_date'])
            ->where('patient_document', $payload['patient_document'])
            ->exists();

        if ($dup) {
            throw new Exception('Ya existe un registro con ese documento para la fecha y sede.');
        }

        return $this->repo->create($payload);
    }

    public function update(User $user, int $id, array $data): TurnPriorityPreload
    {
        $record = $this->repo->findById($id);
        $this->assertUserCanAccessPlace($user, (int) $record->place_id);

        if ($record->status === TurnPriorityPreload::STATUS_USED) {
            throw new Exception('No se puede editar un registro ya utilizado.');
        }

        $payload = $this->normalizeRow(array_merge($record->toArray(), $data), true);

        if (
            $payload['patient_document'] !== $record->patient_document
            || $payload['scheduled_date'] !== $record->scheduled_date->format('Y-m-d')
        ) {
            $dup = TurnPriorityPreload::where('place_id', $record->place_id)
                ->whereDate('scheduled_date', $payload['scheduled_date'])
                ->where('patient_document', $payload['patient_document'])
                ->where('id', '!=', $record->id)
                ->exists();

            if ($dup) {
                throw new Exception('Ya existe otro registro con ese documento para la fecha.');
            }
        }

        return $this->repo->update($record, $payload);
    }

    public function delete(User $user, int $id): void
    {
        $record = $this->repo->findById($id);
        $this->assertUserCanAccessPlace($user, (int) $record->place_id);
        $this->repo->delete($record);
    }

    /**
     * Carga masiva en memoria (sin guardar archivo).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{created: int, updated: int, skipped: int, errors: array<int, string>}
     */
    public function bulkUpsert(User $user, int $placeId, string $date, array $rows, ?string $priority = null): array
    {
        $this->assertUserCanAccessPlace($user, $placeId);

        $priority = $priority ?: Turn::PRIORITY_GENERAL;
        if (! in_array($priority, Turn::PRIORITIES, true) || $priority === Turn::PRIORITY_NORMAL) {
            $priority = Turn::PRIORITY_GENERAL;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        DB::transaction(function () use ($user, $placeId, $date, $rows, $priority, &$created, &$updated, &$skipped, &$errors) {
            foreach ($rows as $index => $row) {
                $line = $index + 1;

                try {
                    $payload = $this->normalizeRow([
                        'place_id' => $placeId,
                        'scheduled_date' => $date,
                        'patient_document' => $row['patient_document'] ?? $row['document'] ?? null,
                        'patient_name' => $row['patient_name'] ?? $row['name'] ?? null,
                        'patient_phone' => $row['patient_phone'] ?? $row['phone'] ?? null,
                        'patient_company' => $row['patient_company'] ?? $row['company'] ?? null,
                        'priority' => $priority,
                    ]);

                    $existing = TurnPriorityPreload::where('place_id', $placeId)
                        ->whereDate('scheduled_date', $date)
                        ->where('patient_document', $payload['patient_document'])
                        ->first();

                    if ($existing) {
                        if ($existing->status === TurnPriorityPreload::STATUS_USED) {
                            $skipped++;
                            $errors[] = "Fila {$line}: documento {$payload['patient_document']} ya fue usado.";
                            continue;
                        }

                        $this->repo->update($existing, [
                            'patient_name' => $payload['patient_name'],
                            'patient_phone' => $payload['patient_phone'],
                            'patient_company' => $payload['patient_company'],
                            'priority' => $payload['priority'],
                            'status' => TurnPriorityPreload::STATUS_PENDING,
                        ]);
                        $updated++;
                    } else {
                        $payload['created_by'] = $user->id;
                        $payload['status'] = TurnPriorityPreload::STATUS_PENDING;
                        $this->repo->create($payload);
                        $created++;
                    }
                } catch (Exception $e) {
                    $skipped++;
                    $errors[] = "Fila {$line}: ".$e->getMessage();
                }
            }
        });

        return compact('created', 'updated', 'skipped', 'errors');
    }

    public function lookup(User $user, string $document, ?int $placeId = null, ?string $date = null): ?TurnPriorityPreload
    {
        $stationPlaceId = $user->station_id
            ? (int) optional($user->station()->first())->place_id
            : null;

        $placeId = $placeId ?: $stationPlaceId ?: (int) $user->place_id;
        if (! $placeId) {
            throw new Exception('No se pudo determinar la sede para la consulta.');
        }

        $this->assertUserCanAccessPlace($user, $placeId);
        $date = $date ?: now()->toDateString();

        return $this->repo->findPendingMatch($placeId, $document, $date);
    }

    public function applyToTurnCreate(int $placeId, ?string $document, array &$data): ?TurnPriorityPreload
    {
        if (! filled($document)) {
            return null;
        }

        $preload = $this->repo->findPendingMatch($placeId, $document, now()->toDateString());
        if (! $preload) {
            return null;
        }

        // Prioridad de lista precargada
        $data['priority'] = $preload->priority ?: Turn::PRIORITY_GENERAL;

        if (! filled($data['patient_name'] ?? null) && filled($preload->patient_name)) {
            $data['patient_name'] = $preload->patient_name;
        }
        if (! filled($data['patient_phone'] ?? null) && filled($preload->patient_phone)) {
            $data['patient_phone'] = $preload->patient_phone;
        }
        if (! filled($data['patient_company'] ?? null) && filled($preload->patient_company)) {
            $data['patient_company'] = $preload->patient_company;
        }

        return $preload;
    }

    public function markUsed(TurnPriorityPreload $preload, int $turnId): void
    {
        $this->repo->update($preload, [
            'status' => TurnPriorityPreload::STATUS_USED,
            'used_at' => now(),
            'used_turn_id' => $turnId,
        ]);
    }

    protected function normalizeRow(array $data, bool $partial = false): array
    {
        $document = $this->repo->normalizeDocument($data['patient_document'] ?? null);
        $name = trim((string) ($data['patient_name'] ?? ''));

        if (! $partial || array_key_exists('patient_document', $data)) {
            if ($document === '') {
                throw new Exception('El documento es obligatorio.');
            }
        }

        if (! $partial || array_key_exists('patient_name', $data)) {
            if ($name === '') {
                throw new Exception('El nombre es obligatorio.');
            }
        }

        $priority = $data['priority'] ?? Turn::PRIORITY_GENERAL;
        if (! in_array($priority, Turn::PRIORITIES, true) || $priority === Turn::PRIORITY_NORMAL) {
            $priority = Turn::PRIORITY_GENERAL;
        }

        $date = $data['scheduled_date'] ?? null;
        if ($date instanceof \DateTimeInterface) {
            $date = $date->format('Y-m-d');
        }
        if (! $date || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date)) {
            throw new Exception('La fecha programada es obligatoria (YYYY-MM-DD).');
        }

        $phone = isset($data['patient_phone'])
            ? (preg_replace('/\D+/', '', (string) $data['patient_phone']) ?: null)
            : null;

        $payload = [
            'place_id' => (int) ($data['place_id'] ?? 0),
            'scheduled_date' => $date,
            'patient_document' => $document !== ''
                ? $document
                : $this->repo->normalizeDocument($data['patient_document'] ?? ''),
            'patient_name' => $name,
            'patient_phone' => $phone,
            'patient_company' => filled($data['patient_company'] ?? null)
                ? trim((string) $data['patient_company'])
                : null,
            'priority' => $priority,
        ];

        if (array_key_exists('status', $data) && filled($data['status'])) {
            $payload['status'] = $data['status'];
        }

        return $payload;
    }

    protected function assertUserCanAccessPlace(User $user, int $placeId): void
    {
        PlaceScope::assert($user, $placeId, 'turn_priority_preloads.allPlaces');

        $place = Place::find($placeId);
        if (! $place || $place->status !== 'activo') {
            throw new Exception('La sede no está disponible.');
        }
    }
}
