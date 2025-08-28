<?php

namespace App\Listeners;

use App\Models\MessageAudit;
use App\Events\MessageReceivedEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class AuditMessageListener implements ShouldQueue
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
    public function handle(MessageReceivedEvent $event)
    {
        MessageAudit::create([
            'queue' => $event->queue,
            'payload' => json_encode($event->data),
            'received_at' => now(),
            'user_id' => auth()->id() ?? null,
        ]);
    }
}
