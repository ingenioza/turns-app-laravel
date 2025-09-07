<?php

namespace App\Application\Services;

use App\Domain\Group\Group;
use App\Domain\Group\GroupRepositoryInterface;
use App\Domain\Turn\Turn;
use App\Domain\Turn\TurnRepositoryInterface;
use App\Domain\User\User;
use Illuminate\Validation\ValidationException;

class TurnService
{
    public function __construct(
        private TurnRepositoryInterface $turnRepository,
        private GroupRepositoryInterface $groupRepository
    ) {}

    public function startTurn(Group $group, User $user): Turn
    {
        // Validate user is a member
        if (! $this->groupRepository->isMember($group, $user)) {
            throw ValidationException::withMessages(['user' => 'User is not a member of this group']);
        }

        // Check if group is active
        if ($group->status !== 'active') {
            throw ValidationException::withMessages(['group' => 'Group is not active']);
        }

        // Check if there's already an active turn
        $activeTurn = $this->turnRepository->findActiveByGroup($group);
        if ($activeTurn) {
            throw ValidationException::withMessages(['turn' => 'There is already an active turn in this group']);
        }

        // Check if it's the user's turn
        $nextUser = $group->getNextUser();
        if ($nextUser && $nextUser->id !== $user->id) {
            throw ValidationException::withMessages(['turn' => 'It is not your turn']);
        }

        $turn = $this->turnRepository->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'started_at' => now(),
            'status' => 'active',
        ]);

        // Update group's current user and last turn time
        $this->groupRepository->update($group, [
            'current_user_id' => $user->id,
            'last_turn_at' => now(),
        ]);

        // Log turn start
        activity()
            ->performedOn($turn)
            ->causedBy($user)
            ->log('turn_started');

        return $turn;
    }

    public function completeTurn(Turn $turn, User $user, array $data = []): Turn
    {
        // Validate user can complete this turn
        if ($turn->user_id !== $user->id) {
            throw ValidationException::withMessages(['permission' => 'Can only complete your own turn']);
        }

        if ($turn->status !== 'active') {
            throw ValidationException::withMessages(['turn' => 'Turn is not active']);
        }

        $endTime = now();
        $duration = $endTime->diffInSeconds($turn->started_at);

        $updateData = [
            'ended_at' => $endTime,
            'status' => 'completed',
            'duration_seconds' => $duration,
        ];

        // Add optional data
        if (isset($data['notes'])) {
            $updateData['notes'] = $data['notes'];
        }

        if (isset($data['metadata'])) {
            $updateData['metadata'] = $data['metadata'];
        }

        $this->turnRepository->update($turn, $updateData);

        // Update group's turn history and move to next user
        $group = $turn->group;
        $this->updateGroupTurnHistory($group, $turn);
        $this->moveToNextUser($group);

        // Log turn completion
        activity()
            ->performedOn($turn)
            ->causedBy($user)
            ->withProperties(['duration_seconds' => $duration])
            ->log('turn_completed');

        return $turn->fresh();
    }

    public function skipTurn(Turn $turn, User $user, ?string $reason = null): Turn
    {
        // Validate user can skip this turn
        if ($turn->user_id !== $user->id) {
            throw ValidationException::withMessages(['permission' => 'Can only skip your own turn']);
        }

        if ($turn->status !== 'active') {
            throw ValidationException::withMessages(['turn' => 'Turn is not active']);
        }

        $this->turnRepository->update($turn, [
            'ended_at' => now(),
            'status' => 'skipped',
            'notes' => $reason,
            'duration_seconds' => now()->diffInSeconds($turn->started_at),
        ]);

        // Update group and move to next user
        $group = $turn->group;
        $this->updateGroupTurnHistory($group, $turn);
        $this->moveToNextUser($group);

        // Log turn skip
        activity()
            ->performedOn($turn)
            ->causedBy($user)
            ->withProperties(['reason' => $reason])
            ->log('turn_skipped');

        return $turn->fresh();
    }

    public function forceEndTurn(Turn $turn, User $admin, ?string $reason = null): Turn
    {
        $group = $turn->group;

        // Validate admin permissions
        if (! $this->groupRepository->isAdmin($group, $admin) && $group->creator_id !== $admin->id) {
            throw ValidationException::withMessages(['permission' => 'Not authorized to force end turns']);
        }

        if ($turn->status !== 'active') {
            throw ValidationException::withMessages(['turn' => 'Turn is not active']);
        }

        $this->turnRepository->update($turn, [
            'ended_at' => now(),
            'status' => 'expired',
            'notes' => $reason,
            'duration_seconds' => now()->diffInSeconds($turn->started_at),
        ]);

        // Update group and move to next user
        $this->updateGroupTurnHistory($group, $turn);
        $this->moveToNextUser($group);

        // Log forced end
        activity()
            ->performedOn($turn)
            ->causedBy($admin)
            ->withProperties([
                'reason' => $reason,
                'original_user_id' => $turn->user_id,
            ])
            ->log('turn_force_ended');

        return $turn->fresh();
    }

    public function getActiveTurn(Group $group): ?Turn
    {
        return $this->turnRepository->findActiveByGroup($group);
    }

    public function getCurrentTurn(Group $group): ?Turn
    {
        return $this->turnRepository->findCurrentTurn($group);
    }

    public function getGroupHistory(Group $group, int $limit = 50): array
    {
        return $this->turnRepository->findGroupHistory($group, $limit);
    }

    public function getUserHistory(User $user, int $limit = 50): array
    {
        return $this->turnRepository->findUserHistory($user, $limit);
    }

    public function getGroupStatistics(Group $group): array
    {
        return $this->turnRepository->getGroupStatistics($group);
    }

    public function getUserStatistics(User $user): array
    {
        return $this->turnRepository->getUserStatistics($user);
    }

    public function expireOldTurns(): int
    {
        $expiredTurns = $this->turnRepository->findExpiredTurns();
        $count = 0;

        foreach ($expiredTurns as $turnData) {
            $turn = $this->turnRepository->findById($turnData['id']);
            if ($turn && $turn->status === 'active') {
                $this->turnRepository->update($turn, [
                    'ended_at' => now(),
                    'status' => 'expired',
                    'duration_seconds' => now()->diffInSeconds($turn->started_at),
                    'notes' => 'Automatically expired after 24 hours',
                ]);

                // Move to next user
                $this->moveToNextUser($turn->group);
                $count++;

                // Log expiration
                activity()
                    ->performedOn($turn)
                    ->log('turn_auto_expired');
            }
        }

        return $count;
    }

    private function updateGroupTurnHistory(Group $group, Turn $turn): void
    {
        $history = $group->getTurnHistory();

        // Add this turn to history (keep last 100)
        $history[] = [
            'turn_id' => $turn->id,
            'user_id' => $turn->user_id,
            'started_at' => $turn->started_at->toISOString(),
            'ended_at' => $turn->ended_at?->toISOString(),
            'status' => $turn->status,
            'duration_seconds' => $turn->duration_seconds,
        ];

        // Keep only last 100 turns in history
        if (count($history) > 100) {
            $history = array_slice($history, -100);
        }

        $this->groupRepository->update($group, [
            'turn_history' => $history,
        ]);
    }

    private function moveToNextUser(Group $group): void
    {
        $nextUser = $group->getNextUser();

        $this->groupRepository->update($group, [
            'current_user_id' => $nextUser?->id,
        ]);
    }
}
