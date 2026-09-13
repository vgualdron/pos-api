<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DisplayTurnUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $placeId)
    {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('place.'.$this->placeId.'.display');
    }

    public function broadcastAs(): string
    {
        return 'display.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'place_id' => $this->placeId,
        ];
    }
}
