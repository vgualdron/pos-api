<?php

namespace App\Services;

use App\Models\RegisterSession;
use App\Models\Station;
use Carbon\Carbon;
use Exception;

class RegisterSessionService
{
    public function current($user): ?RegisterSession
    {
        $station = $this->requireStation($user);

        return RegisterSession::where('station_id', $station->id)
            ->where('status', RegisterSession::STATUS_OPEN)
            ->latest('id')
            ->first();
    }

    public function open($user, array $data): RegisterSession
    {
        $station = $this->requireStation($user);
        $existing = $this->current($user);
        if ($existing) {
            return $existing;
        }

        return RegisterSession::create([
            'place_id' => $station->place_id,
            'station_id' => $station->id,
            'user_id' => $user->id,
            'opened_at' => Carbon::now(),
            'opening_cash' => (float) ($data['opening_cash'] ?? 0),
            'status' => RegisterSession::STATUS_OPEN,
        ]);
    }

    public function close($user, array $data): RegisterSession
    {
        $open = $this->current($user);
        if (!$open) {
            throw new Exception('No hay una caja abierta.');
        }

        $open->update([
            'closed_at' => Carbon::now(),
            'closing_cash' => $data['closing_cash'] ?? null,
            'status' => RegisterSession::STATUS_CLOSED,
        ]);

        return $open->fresh();
    }

    protected function requireStation($user): Station
    {
        $user->loadMissing('station');
        if (!$user->station) {
            throw new Exception('El usuario no tiene una caja asignada.');
        }

        return $user->station;
    }
}
