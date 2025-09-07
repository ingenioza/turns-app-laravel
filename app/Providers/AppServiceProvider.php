<?php

namespace App\Providers;

use App\Events\GroupJoined;
use App\Events\TurnAssigned;
use App\Events\TurnCompleted;
use App\Listeners\SendGroupJoinedNotification;
use App\Listeners\SendTurnAssignedNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register event listeners
        Event::listen(
            TurnAssigned::class,
            SendTurnAssignedNotification::class,
        );

        Event::listen(
            GroupJoined::class,
            SendGroupJoinedNotification::class,
        );
    }
}
