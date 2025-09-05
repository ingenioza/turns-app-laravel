<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Group\Group;
use App\Domain\Turn\Turn;
use App\Domain\Turn\TurnRepositoryInterface;
use App\Domain\User\User;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentTurnRepository implements TurnRepositoryInterface
{
    public function findById(int $id): ?Turn
    {
        return Turn::find($id);
    }
    
    public function create(array $data): Turn
    {
        return Turn::create($data);
    }
    
    public function update(Turn $turn, array $data): bool
    {
        return $turn->update($data);
    }
    
    public function delete(Turn $turn): bool
    {
        return $turn->delete();
    }
    
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Turn::paginate($perPage);
    }
    
    public function findByGroup(Group $group): array
    {
        return Turn::where('group_id', $group->id)
            ->orderBy('started_at', 'desc')
            ->get()
            ->toArray();
    }
    
    public function findByUser(User $user): array
    {
        return Turn::where('user_id', $user->id)
            ->orderBy('started_at', 'desc')
            ->get()
            ->toArray();
    }
    
    public function findActiveByGroup(Group $group): ?Turn
    {
        return Turn::where('group_id', $group->id)
            ->where('status', 'active')
            ->first();
    }
    
    public function findCurrentTurn(Group $group): ?Turn
    {
        return Turn::where('group_id', $group->id)
            ->whereIn('status', ['active'])
            ->orderBy('started_at', 'desc')
            ->first();
    }
    
    public function findByStatus(string $status): array
    {
        return Turn::where('status', $status)->get()->toArray();
    }
    
    public function findGroupHistory(Group $group, int $limit = 50): array
    {
        return Turn::where('group_id', $group->id)
            ->orderBy('started_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }
    
    public function findUserHistory(User $user, int $limit = 50): array
    {
        return Turn::where('user_id', $user->id)
            ->orderBy('started_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }
    
    public function findExpiredTurns(): array
    {
        // Find turns that have been active for more than 24 hours
        return Turn::where('status', 'active')
            ->where('started_at', '<', now()->subHours(24))
            ->get()
            ->toArray();
    }
    
    public function findLongRunningTurns(int $hoursThreshold = 24): array
    {
        return Turn::where('status', 'active')
            ->where('started_at', '<', now()->subHours($hoursThreshold))
            ->get()
            ->toArray();
    }
    
    public function getGroupStatistics(Group $group): array
    {
        $turns = Turn::where('group_id', $group->id);
        
        return [
            'total_turns' => $turns->count(),
            'completed_turns' => $turns->where('status', 'completed')->count(),
            'active_turns' => $turns->where('status', 'active')->count(),
            'skipped_turns' => $turns->where('status', 'skipped')->count(),
            'average_duration' => $turns->where('status', 'completed')
                ->whereNotNull('duration_seconds')
                ->avg('duration_seconds'),
            'last_turn_at' => $turns->orderBy('started_at', 'desc')->first()?->started_at,
        ];
    }
    
    public function getUserStatistics(User $user): array
    {
        $turns = Turn::where('user_id', $user->id);
        
        return [
            'total_turns' => $turns->count(),
            'completed_turns' => $turns->where('status', 'completed')->count(),
            'active_turns' => $turns->where('status', 'active')->count(),
            'skipped_turns' => $turns->where('status', 'skipped')->count(),
            'average_duration' => $turns->where('status', 'completed')
                ->whereNotNull('duration_seconds')
                ->avg('duration_seconds'),
            'last_turn_at' => $turns->orderBy('started_at', 'desc')->first()?->started_at,
        ];
    }
}
