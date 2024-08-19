<?php

use App\Jobs\NotificationAccountability;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::call(function () {
    $response = Http::withHeaders([
        'access_token' => env('JWT_SECRET')
    ])->get(env('MAPA_URL') . 'bigsheet/infoForNotificationsAccountability');
    $infos = $response->json();

    NotificationAccountability::dispatch($infos);
})->dailyAt('07:00');
