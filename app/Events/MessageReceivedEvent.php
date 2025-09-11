<?php

namespace App\Events;

use Illuminate\Support\Facades\Log;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class MessageReceivedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $data;
    public $queue;
    /**
     * Create a new event instance.
     */
    public function __construct(array $data, string $queue)
    {
        $this->data = $data;
        $this->queue = $queue;
        Log::info('Email enviado para ' . $this->data['agent_email']);
    }

    /**
     * Get the channels the event should broadcast on.
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
