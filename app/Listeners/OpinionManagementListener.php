<?php

namespace App\Listeners;

use App\Models\MessageAudit;
use App\Models\EmailDispatch;
use App\Mail\OpinionManagementMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Events\OpinionManagementEvent;

class OpinionManagementListener
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
    public function handle(OpinionManagementEvent $event): void
    {

        $regis = $this->getRegistration($event);
        foreach ($regis as $registration) {
            Log::info('Enviando e-mail para: ' . $registration['agent']['email']);
            // Cria o Mailable
            $mailable = new OpinionManagementMail($registration);

            // Audita em EmailDispatch com o conteúdo renderizado
            EmailDispatch::create([
                'to' => $registration['agent']['name'],
                'subject' => $mailable->subject,
                'content' => $mailable->render(),
                'mailable_type' => OpinionManagementMail::class,
                'meta' => [
                    'number' => $registration['number'],
                    'agent' => $registration['agent']['name'],
                    'opportunity' => $registration['opportunity'],
                    'link' => $registration['url'],
                ],
                'dispatched_at' => now(),
            ]);

            Mail::to($registration['agent']['email'])->send(new OpinionManagementMail($registration));
            Log::info('Email enviado e auditado para: ' . $registration['agent']['email']);
        }
    }

    protected function getRegistration($registrations): array
    {
        $regis = [];
        foreach ($registrations->registration['registrations'] as $registration) {
            if (
                !isset(
                    $registration['number'],
                    $registration['url'],
                    $registration['agent']['email'],
                    $registration['agent']['name']
                )
            ) {
                Log::error('Chaves obrigatórias ausentes na inscrição: ' . json_encode($registration));
                continue;
            }

            $regis[] = array_merge($registration, [
                'opportunity' => $registrations->registration['opportunity']['name']
            ]);

        }
        return $regis;
    }
}
