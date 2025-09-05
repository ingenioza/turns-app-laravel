<?php

namespace App\Providers;

use App\Domain\Group\GroupRepositoryInterface;
use App\Domain\Turn\TurnRepositoryInterface;
use App\Domain\User\UserRepositoryInterface;
use App\Infrastructure\Repositories\EloquentGroupRepository;
use App\Infrastructure\Repositories\EloquentTurnRepository;
use App\Infrastructure\Repositories\EloquentUserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(GroupRepositoryInterface::class, EloquentGroupRepository::class);
        $this->app->bind(TurnRepositoryInterface::class, EloquentTurnRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
