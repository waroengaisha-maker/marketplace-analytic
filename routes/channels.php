<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('imports.{userId}', function (User $user, int $userId): bool {
    return $user->id === $userId;
});

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id): bool {
    return $user->id === $id;
});
