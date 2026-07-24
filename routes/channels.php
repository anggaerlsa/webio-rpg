<?php

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Notifikasi pribadi (permintaan teman, dll) ke user tertentu.
Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return (int) $user->id === $id;
});

// DM: hanya kedua pihak pertemanan (yang sudah diterima) boleh mendengarkan.
Broadcast::channel('chat.dm.{friendship}', function (User $user, int $friendship) {
    $f = Friendship::find($friendship);

    return $f && $f->status === 'accepted' && $f->involves($user);
});
