<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pesan chat fana: bersihkan yang lebih dari 30 menit (juga dipangkas saat dibaca).
Schedule::command('chat:prune')->everyFiveMinutes();
