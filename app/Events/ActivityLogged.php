<?php

namespace App\Events;

use App\Models\ActivityLog;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// Broadcasts every new activity-log entry to the shared admin.activity
// channel (not App.Models.User.{id} — this needs to reach every admin
// viewing the log or the dashboard, not one specific recipient).
class ActivityLogged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ActivityLog $log;

    public function __construct(ActivityLog $log)
    {
        $this->log = $log;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin.activity')];
    }

    public function broadcastWith(): array
    {
        return [
            'id'            => $this->log->id,
            'actor_name'    => $this->log->actor_name,
            'actor_role'    => $this->log->actor_role,
            'category'      => $this->log->category,
            'description'   => $this->log->description,
            'subject_label' => $this->log->subject_label,
            'created_at'    => $this->log->created_at->format('h:i A'),
            'timestamp'     => $this->log->created_at->toIso8601String(),
        ];
    }
}
