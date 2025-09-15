<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use App\Events\OpinionManagementEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

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
    }

    protected function getRegistration($registrations): array
    {
        $regis = [];
        // dump($registrations->registration['registrations']);
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
