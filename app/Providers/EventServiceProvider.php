<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Events\MessageReceivedEvent;
use App\Listeners\AuditMessageListener;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        MessageReceivedEvent::class => [
            AuditMessageListener::class,
        ],
    ];
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
