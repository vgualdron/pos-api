<?php

namespace App\Support;

use App\Models\Place;
use App\Models\Station;
use App\Models\User;
use Exception;

class PlaceScope
{
    public static function sessionPlaceId(User $user): ?int
    {
        $user->loadMissing('station');

        if ($user->station?->place_id) {
            return (int) $user->station->place_id;
        }

        if ($user->place_id) {
            return (int) $user->place_id;
        }

        return null;
    }

    public static function assert(User $user, int $placeId, string ...$allPlacesPermissions): void
    {
        $place = Place::query()->find($placeId);
        if (! $place) {
            throw new Exception('La sede no está disponible.');
        }

        $ownPlaceId = self::sessionPlaceId($user);
        if ($ownPlaceId && (int) $ownPlaceId === (int) $placeId) {
            return;
        }

        $canAllPlaces = false;
        foreach ($allPlacesPermissions as $permission) {
            if ($permission && $user->can($permission)) {
                $canAllPlaces = true;
                break;
            }
        }

        if (! $canAllPlaces) {
            throw new Exception('No tiene permiso para ver o manipular otras sedes.');
        }

        if ($user->can('companies.allCompanies')) {
            return;
        }

        if (! $user->company_id || (int) $user->company_id !== (int) $place->company_id) {
            throw new Exception('No tiene permiso para gestionar esta sede.');
        }
    }

    public static function resolvePlaceId(User $user, ?int $requestedPlaceId, string $allPlacesPermission): int
    {
        if ($requestedPlaceId) {
            self::assert($user, $requestedPlaceId, $allPlacesPermission);

            return $requestedPlaceId;
        }

        $ownPlaceId = self::sessionPlaceId($user);
        if ($ownPlaceId) {
            return $ownPlaceId;
        }

        if ($user->can($allPlacesPermission)) {
            throw new Exception('Debe indicar una sede.');
        }

        throw new Exception('Su usuario no tiene una sede asignada.');
    }

    public static function assertStation(User $user, int $stationId, string ...$allPlacesPermissions): void
    {
        $station = Station::query()->find($stationId);
        if (! $station) {
            throw new Exception('La estación no está disponible.');
        }

        self::assert($user, (int) $station->place_id, ...$allPlacesPermissions);
    }
}
