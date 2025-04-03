<?php

namespace App\Jobs;

use App\Mail\DeadlineForAccountability;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class NotificationAccountability implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(protected $infos) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        foreach ($this->infos as $info) {
            $info = (object) $info;
            $emailSent = Mail::to($info->user_email)->send(new DeadlineForAccountability($info));
            // $info->is_last_notification, último dia para enviar a notificação
            if ($emailSent instanceof \Illuminate\Mail\SentMessage && $info->is_last_notification) {
                Http::post(config('app.mapa_url').'/bigsheet/updateNotificationStatus', [
                    'registration_number' => $info->registration_number,
                    'access_token' => config('jwt.secret'),
                ]);
            }
        }
    }
}
