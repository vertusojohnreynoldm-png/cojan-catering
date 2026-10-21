<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// Fires on every message send (either direction) so the admin inbox list can
// update live even when that specific customer's thread isn't open — the
// per-pair "chat.{id1}.{id2}" channel MessageSent broadcasts on only reaches
// an admin who already has that one conversation open. This broadcasts to
// the receiver's own already-authorized App.Models.User.{id} channel instead,
// which the admin dashboard subscribes to unconditionally on page load.
class AdminInboxUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Message $message;
    public int $receiverId;
    public int $unreadCount;

    public function __construct(Message $message, int $receiverId, int $unreadCount)
    {
        $this->message = $message->load('sender', 'receiver');
        $this->receiverId = $receiverId;
        $this->unreadCount = $unreadCount;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.' . $this->receiverId)];
    }

    public function broadcastWith(): array
    {
        return [
            'customer_id'   => $this->message->sender_id === $this->receiverId
                ? $this->message->receiver_id
                : $this->message->sender_id,
            'customer_name' => $this->message->sender_id === $this->receiverId
                ? $this->message->receiver->name
                : $this->message->sender->name,
            'preview'       => \Illuminate\Support\Str::limit($this->message->body, 60),
            'created_at'    => $this->message->created_at->format('h:i A'),
            'timestamp'     => $this->message->created_at->toIso8601String(),
            'unread_count'  => $this->unreadCount,
            'from_customer' => $this->message->sender_id !== $this->receiverId,
        ];
    }
}
