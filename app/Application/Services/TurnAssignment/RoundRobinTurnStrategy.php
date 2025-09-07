<?php

namespace App\Application\Services\TurnAssignment;

use App\Models\Group;
use App\Models\User;

class RoundRobinTurnStrategy implements TurnAssignmentStrategyInterface
{
    private array $config = [
        'reset_on_cycle_complete' => true,
    ];

    public function getNextUser(Group $group): ?User
    {
        // Load active members with pivot data (turn_order)
        $group->loadMissing('activeMembers');

        $members = $group->activeMembers->sortBy('pivot.turn_order');

        if ($members->isEmpty()) {
            return null;
        }

        // Get the last completed turn to determine next in sequence
        $lastTurn = $group->turns()
            ->whereIn('status', ['completed', 'skipped'])
            ->latest('updated_at')
            ->first();

        if (! $lastTurn) {
            // No previous turns, start with first member in order
            return $members->first();
        }

        // Find current user's position and get next
        $currentUserOrder = $members->firstWhere('id', $lastTurn->user_id)?->pivot->turn_order;

        if ($currentUserOrder === null) {
            // Previous user no longer in group, start from beginning
            return $members->first();
        }

        // Find next user in sequence
        $nextMember = $members->firstWhere(function ($member) use ($currentUserOrder) {
            return $member->pivot->turn_order > $currentUserOrder;
        });

        // If no next member found, cycle back to first (if configured)
        if (! $nextMember && $this->config['reset_on_cycle_complete']) {
            return $members->first();
        }

        return $nextMember;
    }

    public function getName(): string
    {
        return 'round_robin';
    }

    public function getDescription(): string
    {
        return 'Cycles through group members in order based on their turn_order';
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
