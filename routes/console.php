<?php

use App\Jobs\NotificationAccountability;
use App\Mail\DeadlineForAccountability;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::call(function () {
    $response = Http::get( config('app.mapa_url') . 'bigsheet/infoForNotificationsAccountability', [
        'access_token' => config('jwt.secret')
    ]);
    $infos = $response->json();

    NotificationAccountability::dispatch($infos);
})->dailyAt('06:00');
