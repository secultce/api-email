<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImportRegistrationEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Dados da inscrição que será processada.
     *
     * @var array
     */
    public $registration;

    /**
     * Cria uma nova instância do evento.
     */
    public function __construct(array $registration)
    {
        $this->registration = $registration;
    }

    /**
     * Retorna os canais em que o evento seria transmitido.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
