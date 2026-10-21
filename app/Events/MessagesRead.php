<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// Fired when the admin opens a customer's thread and their unread messages
// get marked read — lets that customer's chat widget flip "Sent" to "Seen"
// on their own messages live, without a refresh.
class MessagesRead implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $readerId;
    public int $otherUserId;
    public string $upto;

    public function __construct(int $readerId, int $otherUserId, string $upto)
    {
        $this->readerId = $readerId;
        $this->otherUserId = $otherUserId;
        $this->upto = $upto;
    }

    public function broadcastOn(): array
    {
        $ids = [$this->readerId, $this->otherUserId];
        sort($ids);
        return [new PrivateChannel('chat.' . $ids[0] . '.' . $ids[1])];
    }

    public function broadcastWith(): array
    {
        return [
            'reader_id' => $this->readerId,
            'upto'      => $this->upto,
        ];
    }
}
