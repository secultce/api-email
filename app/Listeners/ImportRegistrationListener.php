<?php

namespace App\Listeners;

use App\Events\ImportRegistrationEvent;
use App\Services\ImportRegistrationProcessor;

class ImportRegistrationListener
{
    public function __construct(
        protected ImportRegistrationProcessor $processor
    ) {}

    public function handle(ImportRegistrationEvent $event): void
    {
        $data = $event->registration;

        /*
         * Compatibilidade com o formato antigo do listener.
         */
        if (!isset($data['opportunity_id']) && isset($data['opp_id'])) {
            $data['opportunity_id'] = $data['opp_id'];
        }

        if (!isset($data['opportunity_name']) && isset($data['opp_name'])) {
            $data['opportunity_name'] = $data['opp_name'];
        }

        $this->processor->process($data);
    }
}
