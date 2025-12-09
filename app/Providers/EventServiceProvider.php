<?php

namespace App\Providers;

use App\Events\OpinionManagementEvent;
use App\Events\ImportRegistrationEvent;
use App\Listeners\AuditMessageListener;
use App\Listeners\OpinionManagementListener;
use App\Listeners\ImportRegistrationListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        OpinionManagementEvent::class => [
            OpinionManagementListener::class,
        ],
        ImportRegistrationEvent::class => [
            ImportRegistrationListener::class,
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
