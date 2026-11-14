<?php

namespace App\Support;

use App\Models\Station;
use App\Models\User;
use Exception;

class PosStation
{
    /**
     * Resolve the register station for POS operations.
     * Prefer the user's assigned station; otherwise require station_id and authorize it.
     */
    public static function resolve(User $user, ?int $stationId = null): Station
    {
        $user->loadMissing('station');

        if ($user->station) {
            if ($stationId && (int) $stationId !== (int) $user->station->id) {
                throw new Exception('Su usuario ya tiene una caja asignada y no puede operar otra.');
            }

            return self::assertRegisterStation($user->station);
        }

        if (!$stationId) {
            throw new Exception('Seleccione una caja para continuar.');
        }

        PlaceScope::assertStation($user, $stationId, 'sales.allPlaces', 'stations.allPlaces');

        $station = Station::query()->find($stationId);
        if (!$station) {
            throw new Exception('La caja seleccionada no está disponible.');
        }

        return self::assertRegisterStation($station);
    }

    public static function tryResolve(User $user, ?int $stationId = null): ?Station
    {
        $user->loadMissing('station');

        if (!$user->station && !$stationId) {
            return null;
        }

        return self::resolve($user, $stationId);
    }

    protected static function assertRegisterStation(Station $station): Station
    {
        if ($station->type !== Station::TYPE_REGISTER) {
            throw new Exception('La estación seleccionada no es una caja.');
        }

        if ($station->status && $station->status !== 'activo') {
            throw new Exception('La caja seleccionada está inactiva.');
        }

        return $station;
    }
}
