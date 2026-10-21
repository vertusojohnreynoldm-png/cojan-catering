<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Order;
use App\Models\User;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.{id1}.{id2}', function (User $user, $id1, $id2) {
    return $user->id == $id1 || $user->id == $id2;
});

Broadcast::channel('delivery.{orderId}', function (User $user, $orderId) {
    return Order::where('id', $orderId)->where('user_id', $user->id)->exists();
});

// Shared by every admin (unlike App.Models.User.{id}, which is per-recipient)
// — the activity log's live-update prompt and the dashboard's Recent
// Activity widget both need to hear about every new entry regardless of
// which admin account is viewing, not just one specific admin's own channel.
Broadcast::channel('admin.activity', function (User $user) {
    return $user->role === 'admin';
});