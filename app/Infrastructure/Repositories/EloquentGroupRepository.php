<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Group\Group;
use App\Domain\Group\GroupRepositoryInterface;
use App\Domain\User\User;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentGroupRepository implements GroupRepositoryInterface
{
    public function findById(int $id): ?Group
    {
        return Group::find($id);
    }

    public function findByInviteCode(string $inviteCode): ?Group
    {
        return Group::where('invite_code', $inviteCode)->first();
    }

    public function create(array $data): Group
    {
        return Group::create($data);
    }

    public function update(Group $group, array $data): bool
    {
        return $group->update($data);
    }

    public function delete(Group $group): bool
    {
        return $group->delete();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Group::paginate($perPage);
    }

    public function findByUser(User $user): array
    {
        return $user->groups()->get()->toArray();
    }

    public function findByCreator(User $creator): array
    {
        return Group::where('creator_id', $creator->id)->get()->toArray();
    }

    public function findActiveGroups(): array
    {
        return Group::where('status', 'active')->get()->toArray();
    }

    public function findByStatus(string $status): array
    {
        return Group::where('status', $status)->get()->toArray();
    }

    public function search(string $query): array
    {
        return Group::where('name', 'like', "%{$query}%")
            ->orWhere('description', 'like', "%{$query}%")
            ->get()
            ->toArray();
    }

    public function addMember(Group $group, User $user, array $options = []): bool
    {
        $data = array_merge([
            'role' => 'member',
            'is_active' => true,
            'joined_at' => now(),
        ], $options);

        return $group->members()->attach($user->id, $data) !== null;
    }

    public function removeMember(Group $group, User $user): bool
    {
        return $group->members()->detach($user->id) > 0;
    }

    public function updateMemberRole(Group $group, User $user, string $role): bool
    {
        return $group->members()->updateExistingPivot($user->id, ['role' => $role]) > 0;
    }

    public function isMember(Group $group, User $user): bool
    {
        return $group->members()->where('user_id', $user->id)->exists();
    }

    public function isAdmin(Group $group, User $user): bool
    {
        return $group->members()
            ->where('user_id', $user->id)
            ->where('role', 'admin')
            ->exists();
    }
}
