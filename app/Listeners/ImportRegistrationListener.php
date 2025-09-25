<?php

namespace App\Listeners;

use App\Events\ImportRegistrationEvent;
USE App\Mail\ImporteRegistrationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class ImportRegistrationListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(ImportRegistrationEvent $event): void
    {
        foreach ($event->registration as $registration) {
            Log::info('Enviando e-mail para: ' . $registration['agent']['email']);
            Mail::to($registration['agent_email'])->send(new ImporteRegistrationMail($registration));
            Log::info('E-mail enviado para '.$registration['agent_email']);
        }
    }
}
