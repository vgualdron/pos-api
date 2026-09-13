<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('place.{placeId}.display', function ($user, $placeId) {
    $user->loadMissing('station');

    if ($user->station && (int) $user->station->place_id === (int) $placeId) {
        return true;
    }

    return (int) ($user->place_id ?? 0) === (int) $placeId;
});
