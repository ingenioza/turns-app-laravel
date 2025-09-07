<?php

namespace App\Application\Services\TurnAssignment;

use App\Models\Group;
use App\Models\User;

interface TurnAssignmentStrategyInterface
{
    /**
     * Get the next user who should take a turn in the group
     */
    public function getNextUser(Group $group): ?User;

    /**
     * Get the strategy name for identification
     */
    public function getName(): string;

    /**
     * Get the strategy description
     */
    public function getDescription(): string;

    /**
     * Get configuration parameters for the strategy
     */
    public function getConfiguration(): array;

    /**
     * Set configuration parameters for the strategy
     */
    public function setConfiguration(array $config): self;
}
