<?php

namespace App\Application\Services\TurnAssignment;

use App\Models\Group;
use App\Models\User;

class RandomTurnStrategy implements TurnAssignmentStrategyInterface
{
    private array $config = [
        'seed' => null,
        'exclude_current_user' => true,
    ];

    public function getNextUser(Group $group): ?User
    {
        // Load active members
        $group->loadMissing('activeMembers');

        $eligibleMembers = $group->activeMembers;

        if ($eligibleMembers->isEmpty()) {
            return null;
        }

        // Exclude user with active turn if configured
        if ($this->config['exclude_current_user']) {
            $activeTurn = $group->turns()->where('status', 'active')->first();

            if ($activeTurn) {
                $eligibleMembers = $eligibleMembers->reject(function ($member) use ($activeTurn) {
                    return $member->id === $activeTurn->user_id;
                });
            }
        }

        if ($eligibleMembers->isEmpty()) {
            return null;
        }

        // Set seed for reproducible testing
        if ($this->config['seed'] !== null) {
            mt_srand($this->config['seed']);
        }

        // Get random member
        $randomIndex = mt_rand(0, $eligibleMembers->count() - 1);

        return $eligibleMembers->values()->get($randomIndex);
    }

    public function getName(): string
    {
        return 'random';
    }

    public function getDescription(): string
    {
        return 'Randomly selects the next user from eligible group members';
    }

    public function getConfiguration(): array
    {
        return $this->config;
    }

    public function setConfiguration(array $config): self
    {
        $this->config = array_merge($this->config, $config);

        return $this;
    }
}
