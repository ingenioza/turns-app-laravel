<?php

namespace App\Domain\Group;

use App\Domain\User\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface GroupRepositoryInterface
{
    public function findById(int $id): ?Group;
    
    public function findByInviteCode(string $inviteCode): ?Group;
    
    public function create(array $data): Group;
    
    public function update(Group $group, array $data): bool;
    
    public function delete(Group $group): bool;
    
    public function paginate(int $perPage = 15): LengthAwarePaginator;
    
    public function findByUser(User $user): array;
    
    public function findByCreator(User $creator): array;
    
    public function findActiveGroups(): array;
    
    public function findByStatus(string $status): array;
    
    public function search(string $query): array;
    
    public function addMember(Group $group, User $user, array $options = []): bool;
    
    public function removeMember(Group $group, User $user): bool;
    
    public function updateMemberRole(Group $group, User $user, string $role): bool;
    
    public function isMember(Group $group, User $user): bool;
    
    public function isAdmin(Group $group, User $user): bool;
}
