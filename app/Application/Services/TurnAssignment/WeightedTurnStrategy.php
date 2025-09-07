<?php

namespace App\Application\Services\TurnAssignment;

use App\Models\Group;
use App\Models\User;
use Carbon\Carbon;

class WeightedTurnStrategy implements TurnAssignmentStrategyInterface
{
    private array $config = [
        'time_weight' => 0.4,      // Weight for time since last turn
        'completion_weight' => 0.3, // Weight for completion rate
        'skip_weight' => 0.3,      // Weight for skip frequency (inverse)
        'min_hours_since_turn' => 1, // Minimum hours since last turn
    ];

    public function getNextUser(Group $group): ?User
    {
        // Load active members and their turn history
        $group->loadMissing(['activeMembers', 'turns']);

        $members = $group->activeMembers;

        if ($members->isEmpty()) {
            return null;
        }

        // Exclude user with active turn
        $activeTurn = $group->turns()->where('status', 'active')->first();
        if ($activeTurn) {
            $members = $members->reject(function ($member) use ($activeTurn) {
                return $member->id === $activeTurn->user_id;
            });
        }

        if ($members->isEmpty()) {
            return null;
        }

        // Calculate weights for each member
        $memberWeights = $members->map(function ($member) use ($group) {
            return [
                'user' => $member,
                'weight' => $this->calculateMemberWeight($member, $group),
            ];
        });

        // Sort by weight (descending) and return highest weighted member
        $sortedMembers = $memberWeights->sortByDesc('weight');

        return $sortedMembers->first()['user'];
    }

    private function calculateMemberWeight(User $user, Group $group): float
    {
        $userTurns = $group->turns()->where('user_id', $user->id)->get();

        $timeWeight = $this->calculateTimeWeight($user, $userTurns);
        $completionWeight = $this->calculateCompletionWeight($userTurns);
        $skipWeight = $this->calculateSkipWeight($userTurns);

        return ($timeWeight * $this->config['time_weight']) +
               ($completionWeight * $this->config['completion_weight']) +
               ($skipWeight * $this->config['skip_weight']);
    }

    private function calculateTimeWeight(User $user, $userTurns): float
    {
        $lastTurn = $userTurns
            ->whereIn('status', ['completed', 'skipped'])
            ->sortByDesc('updated_at')
            ->first();

        if (! $lastTurn) {
            // Never had a turn, highest weight
            return 1.0;
        }

        $hoursSinceLastTurn = Carbon::parse($lastTurn->updated_at)->diffInHours(now());

        // Minimum threshold - if less than min hours, return 0
        if ($hoursSinceLastTurn < $this->config['min_hours_since_turn']) {
            return 0.0;
        }

        // Normalize to 0-1 scale (24 hours = max weight)
        return min(1.0, $hoursSinceLastTurn / 24.0);
    }

    private function calculateCompletionWeight($userTurns): float
    {
        if ($userTurns->isEmpty()) {
            // No history, neutral weight
            return 0.5;
        }

        $completedTurns = $userTurns->where('status', 'completed')->count();
        $totalTurns = $userTurns->whereIn('status', ['completed', 'skipped'])->count();

        if ($totalTurns === 0) {
            return 0.5;
        }

        // Higher completion rate = higher weight (reward reliability)
        return $completedTurns / $totalTurns;
    }

    private function calculateSkipWeight($userTurns): float
    {
        if ($userTurns->isEmpty()) {
            // No history, neutral weight
            return 0.5;
        }

        $skippedTurns = $userTurns->where('status', 'skipped')->count();
        $totalTurns = $userTurns->whereIn('status', ['completed', 'skipped'])->count();

        if ($totalTurns === 0) {
            return 0.5;
        }

        // Lower skip rate = higher weight (inverse relationship)
        $skipRate = $skippedTurns / $totalTurns;

        return 1.0 - $skipRate;
    }

    public function getName(): string
    {
        return 'weighted';
    }

    public function getDescription(): string
    {
        return 'Assigns turns based on weighted factors: time since last turn, completion rate, and skip frequency';
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
