<?php

namespace App\Listeners;

use App\Events\GroupJoined;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendGroupJoinedNotification implements ShouldQueue
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
    public function handle(GroupJoined $event): void
    {
        // TODO: Implement notification sending logic
        // This will be expanded in Phase 4 with device registry and push notifications
        
        Log::info('User joined group', [
            'user_id' => $event->user->id,
            'group_id' => $event->group->id,
            'group_name' => $event->group->name,
        ]);
    }
}
