<?php

namespace App\Events;

use App\Models\Delivery;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeliveryLocationUpdated implements ShouldBroadcast
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
            'updated_at' => $this->delivery->last_location_update?->toIso8601String(),
        ];
    }
}
