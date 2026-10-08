<?php

namespace App\Events;

use App\Models\Delivery;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// ShouldBroadcastNow — see the same note in App\Events\MessageSent. Location
// updates are also latency-sensitive (a queued, delayed GPS ping defeats the
// point of "live" tracking even once a worker exists).
class DeliveryLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Delivery $delivery)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('delivery.' . $this->delivery->order_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'lat'        => $this->delivery->current_lat,
            'lng'        => $this->delivery->current_lng,
            'accuracy'   => $this->delivery->current_accuracy,
            'updated_at' => $this->delivery->last_location_update?->toIso8601String(),
        ];
    }
}
