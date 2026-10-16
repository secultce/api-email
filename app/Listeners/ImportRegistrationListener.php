<?php

namespace App\Listeners;

use App\Models\EmailDispatch;
use App\Mail\ImporteRegistrationMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Events\ImportRegistrationEvent;

class ImportRegistrationListener
{
    /**
     * Cria uma nova instância do listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Processa uma única inscrição.
     */
    public function handle(ImportRegistrationEvent $event): void
    {
        $registration = $event->registration;

        // Valida os dados da inscrição.
        if (!$this->isValidRegistration($registration)) {
            Log::error(
                'Chaves obrigatórias ausentes na inscrição: '
                . json_encode($registration)
            );

            return;
        }

        Log::info(
            'Enviando e-mail para: ' . $registration['agent_email']
        );

        // Cria o Mailable para a inscrição.
        $mailable = new ImporteRegistrationMail($registration);

        // Registra a auditoria do e-mail em EmailDispatch.
        EmailDispatch::create([
            'to' => $registration['agent_name'],
            'subject' => $mailable->envelope()->subject,
            'content' => $mailable->render(),
            'mailable_type' => ImporteRegistrationMail::class,
            'meta' => [
                'registration' => $registration['registration'],
                'opp_id' => $registration['opp_id'],
                'opp_name' => $registration['opp_name'],
                'number' => $registration['number'],
                'agent_name' => $registration['agent_name'],
                'agent_email' => $registration['agent_email'],
            ],
            'dispatched_at' => now(),
        ]);

        // Envia o e-mail para o responsável pela inscrição.
        Mail::to($registration['agent_email'])->send($mailable);

        Log::info(
            'E-mail enviado e auditado para: '
            . $registration['agent_email']
        );
    }

    /**
     * Valida se a inscrição possui todas as chaves obrigatórias.
     */
    protected function isValidRegistration(array $registration): bool
    {
        return isset(
            $registration['registration'],
            $registration['opp_id'],
            $registration['opp_name'],
            $registration['number'],
            $registration['agent_name'],
            $registration['agent_email']
        );
    }
}
