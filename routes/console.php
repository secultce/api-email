<?php

use App\Mail\DeadlineForAccountability;
use GuzzleHttp\Client;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::call(function () {
    $client = new Client([
        'base_uri' => 'http://172.18.3.108:8088/'
    ]);

    $response = $client->request('GET', 'bigsheet/infoForNotificationsAccountability');
    $infos = json_decode($response->getBody()->getContents());

    foreach ($infos as $info) {
        Mail::to($info->user_email)->send(new DeadlineForAccountability($info));
    }
})->everyMinute();
