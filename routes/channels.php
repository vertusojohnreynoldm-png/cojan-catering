<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\User;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.{id1}.{id2}', function (User $user, $id1, $id2) {
    return $user->id == $id1 || $user->id == $id2;
});