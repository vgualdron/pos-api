<?php

namespace App\Services;

use App\Events\DisplayAlertTriggered;
use App\Models\DisplayAlert;
use App\Models\Place;
use App\Models\Station;
use App\Models\User;
use App\Repositories\DisplayAlertRepository;
use App\Support\PlaceScope;
use Exception;

class DisplayAlertService
{
    public function __construct(protected DisplayAlertRepository $repo)
    {
    }

    public function list(User $user, int $placeId)
    {
        $this->assertUserCanAccessPlace($user, $placeId);

        return $this->repo->listByPlace($placeId);
    }

    public function listTargets(User $user, int $placeId)
    {
        $this->assertUserCanAccessPlace($user, $placeId);

        return Station::query()
            ->where('place_id', $placeId)
            ->where('type', Station::TYPE_DISPLAY)
            ->where('status', 'activo')
            ->orderBy('name')
            ->get(['id', 'place_id', 'name', 'code', 'type', 'status']);
    }

    public function create(User $user, array $data): DisplayAlert
    {
        $payload = $this->normalize($data);
        $this->assertUserCanAccessPlace($user, (int) $payload['place_id']);
        $payload['created_by'] = $user->id;
        $payload['display_order'] = $this->repo->nextDisplayOrder((int) $payload['place_id']);

        return $this->repo->create($payload);
    }

    public function update(User $user, int $id, array $data): DisplayAlert
    {
        $record = $this->repo->findById($id);
        $this->assertUserCanAccessPlace($user, (int) $record->place_id);

        $payload = $this->normalize(array_merge($record->toArray(), $data), true);
        unset($payload['place_id'], $payload['created_by'], $payload['display_order']);

        if (array_key_exists('display_order', $data) && $data['display_order'] !== null) {
            $payload['display_order'] = max(0, (int) $data['display_order']);
        }

        return $this->repo->update($record, $payload);
    }

    public function delete(User $user, int $id): void
    {
        $record = $this->repo->findById($id);
        $this->assertUserCanAccessPlace($user, (int) $record->place_id);
        $this->repo->delete($record);
    }

    public function play(User $user, int $id, array $stationIds = []): DisplayAlert
    {
        $record = $this->repo->findById($id);
        $this->assertUserCanAccessPlace($user, (int) $record->place_id);

        if ($record->status !== DisplayAlert::STATUS_ACTIVE) {
            throw new Exception('Solo se pueden reproducir alertas activas.');
        }

        if (! $record->speak_enabled && ! $record->show_on_screen) {
            throw new Exception('Active la voz o la pantalla en esta alerta para poder reproducirla.');
        }

        $user->loadMissing('station');
        $station = $user->station;
        if (! $station || ! $station->can_call_tickets) {
            throw new Exception('Las alertas de TV solo se pueden enviar desde consultorio o un módulo híbrido.');
        }

        if ((int) $station->place_id !== (int) $record->place_id) {
            throw new Exception('El módulo no pertenece a la misma sede de la alerta.');
        }

        $stationIds = array_values(array_unique(array_map('intval', $stationIds)));
        if ($stationIds === []) {
            throw new Exception('Seleccione al menos una pantalla de TV.');
        }

        $validIds = Station::query()
            ->where('place_id', $record->place_id)
            ->where('type', Station::TYPE_DISPLAY)
            ->whereIn('id', $stationIds)
            ->pluck('id')
            ->map(fn ($value) => (int) $value)
            ->all();

        if (count($validIds) !== count($stationIds)) {
            throw new Exception('Una o más pantallas no pertenecen a esta sede o no son TV.');
        }

        event(new DisplayAlertTriggered($record, now()->toIso8601String(), $validIds));

        return $record;
    }

    protected function normalize(array $data, bool $partial = false): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $message = trim(preg_replace('/\s+/u', ' ', (string) ($data['message'] ?? '')) ?? '');

        if (! $partial || array_key_exists('name', $data)) {
            if ($name === '') {
                throw new Exception('El nombre es obligatorio.');
            }
            if (mb_strlen($name) > 80) {
                throw new Exception('El nombre no puede superar 80 caracteres.');
            }
        }

        if (! $partial || array_key_exists('message', $data)) {
            if ($message === '') {
                throw new Exception('El mensaje es obligatorio.');
            }
            if (mb_strlen($message) > 200) {
                throw new Exception('El mensaje no puede superar 200 caracteres (límite de voz en TV).');
            }
        }

        $type = $data['type'] ?? DisplayAlert::TYPE_INFO;
        if (! in_array($type, DisplayAlert::TYPES, true)) {
            throw new Exception('Tipo de alerta no válido.');
        }

        $status = $data['status'] ?? DisplayAlert::STATUS_ACTIVE;
        if (! in_array($status, DisplayAlert::STATUSES, true)) {
            throw new Exception('Estado no válido.');
        }

        $duration = (int) ($data['duration_seconds'] ?? 15);
        if ($duration < 5 || $duration > 120) {
            throw new Exception('La duración en pantalla debe estar entre 5 y 120 segundos.');
        }

        $repeat = (int) ($data['repeat_count'] ?? 1);
        if ($repeat < 1 || $repeat > 5) {
            throw new Exception('Las repeticiones de voz deben estar entre 1 y 5.');
        }

        return [
            'place_id' => (int) ($data['place_id'] ?? 0),
            'name' => $name,
            'message' => $message,
            'type' => $type,
            'status' => $status,
            'duration_seconds' => $duration,
            'repeat_count' => $repeat,
            'speak_enabled' => array_key_exists('speak_enabled', $data)
                ? (bool) $data['speak_enabled']
                : true,
            'show_on_screen' => array_key_exists('show_on_screen', $data)
                ? (bool) $data['show_on_screen']
                : true,
        ];
    }

    protected function assertUserCanAccessPlace(User $user, int $placeId): void
    {
        PlaceScope::assert($user, $placeId, 'display_alerts.allPlaces');

        $place = Place::find($placeId);
        if (! $place || $place->status !== 'activo') {
            throw new Exception('La sede no está disponible.');
        }
    }
}
