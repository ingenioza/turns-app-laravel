<?php

namespace App\Events;

use App\Models\Turn;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TurnCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Turn $turn;

    /**
     * Create a new event instance.
     */
    public function __construct(Turn $turn)
    {
        $this->turn = $turn;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('group.' . $this->turn->group_id),
        ];
    }
}
