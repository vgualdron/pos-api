<?php

namespace App\Events;

use App\Models\DisplayAlert;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DisplayAlertTriggered implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public DisplayAlert $alert,
        public string $playedAt,
        public array $stationIds = [],
    ) {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('place.'.$this->alert->place_id.'.display');
    }

    public function broadcastAs(): string
    {
        return 'display.alert';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->alert->id,
            'place_id' => (int) $this->alert->place_id,
            'station_ids' => array_values(array_map('intval', $this->stationIds)),
            'name' => $this->alert->name,
            'message' => $this->alert->message,
            'type' => $this->alert->type,
            'duration_seconds' => (int) $this->alert->duration_seconds,
            'repeat_count' => (int) $this->alert->repeat_count,
            'speak_enabled' => (bool) $this->alert->speak_enabled,
            'show_on_screen' => (bool) $this->alert->show_on_screen,
            'played_at' => $this->playedAt,
        ];
    }
}
