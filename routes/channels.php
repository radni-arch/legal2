<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Private channel for user-specific job notifications
Broadcast::channel('user.{userId}.jobs', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
