<?php

namespace App\Domain\Turn;

use App\Domain\Group\Group;
use App\Domain\User\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface TurnRepositoryInterface
{
    public function findById(int $id): ?Turn;
    
    public function create(array $data): Turn;
    
    public function update(Turn $turn, array $data): bool;
    
    public function delete(Turn $turn): bool;
    
    public function paginate(int $perPage = 15): LengthAwarePaginator;
    
    public function findByGroup(Group $group): array;
    
    public function findByUser(User $user): array;
    
    public function findActiveByGroup(Group $group): ?Turn;
    
    public function findCurrentTurn(Group $group): ?Turn;
    
    public function findByStatus(string $status): array;
    
    public function findGroupHistory(Group $group, int $limit = 50): array;
    
    public function findUserHistory(User $user, int $limit = 50): array;
    
    public function findExpiredTurns(): array;
    
    public function findLongRunningTurns(int $hoursThreshold = 24): array;
    
    public function getGroupStatistics(Group $group): array;
    
    public function getUserStatistics(User $user): array;
}
