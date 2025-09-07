<?php

namespace App\Listeners;

use App\Events\TurnAssigned;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendTurnAssignedNotification implements ShouldQueue
{
    use InteractsWithQueue;

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
    public function handle(TurnAssigned $event): void
    {
        // TODO: Implement notification sending logic
        // This will be expanded in Phase 4 with device registry and push notifications
        
        Log::info('Turn assigned to user', [
            'turn_id' => $event->turn->id,
            'user_id' => $event->turn->user_id,
            'group_id' => $event->turn->group_id,
        ]);
    }
}
