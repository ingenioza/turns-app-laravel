<?php

namespace App\Application\Services;

use App\Domain\Group\Group;
use App\Domain\Group\GroupRepositoryInterface;
use App\Domain\User\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GroupService
{
    public function __construct(
        private GroupRepositoryInterface $groupRepository
    ) {}
    
    public function createGroup(User $creator, array $data): Group
    {
        // Validate required fields
        $this->validateGroupData($data);
        
        // Generate unique invite code
        $data['invite_code'] = $this->generateUniqueInviteCode();
        $data['creator_id'] = $creator->id;
        
        $group = $this->groupRepository->create($data);
        
        // Add creator as admin member
        $this->groupRepository->addMember($group, $creator, [
            'role' => 'admin',
            'turn_order' => 1
        ]);
        
        // Log group creation
        activity()
            ->performedOn($group)
            ->causedBy($creator)
            ->log('group_created');
        
        return $group;
    }
    
    public function updateGroup(Group $group, array $data): Group
    {
        // Remove fields that shouldn't be updated directly
        unset($data['creator_id'], $data['invite_code']);
        
        $this->groupRepository->update($group, $data);
        
        return $group->fresh();
    }
    
    public function joinGroup(string $inviteCode, User $user): Group
    {
        $group = $this->groupRepository->findByInviteCode($inviteCode);
        
        if (!$group) {
            throw ValidationException::withMessages(['invite_code' => 'Invalid invite code']);
        }
        
        if ($group->status !== 'active') {
            throw ValidationException::withMessages(['group' => 'Group is not active']);
        }
        
        if ($this->groupRepository->isMember($group, $user)) {
            throw ValidationException::withMessages(['user' => 'Already a member of this group']);
        }
        
        // Get next turn order
        $maxOrder = $group->getMaxTurnOrder();
        
        $this->groupRepository->addMember($group, $user, [
            'turn_order' => $maxOrder + 1
        ]);
        
        // Log user joining
        activity()
            ->performedOn($group)
            ->causedBy($user)
            ->log('user_joined_group');
        
        return $group->fresh();
    }
    
    public function leaveGroup(Group $group, User $user): void
    {
        if (!$this->groupRepository->isMember($group, $user)) {
            throw ValidationException::withMessages(['user' => 'Not a member of this group']);
        }
        
        if ($group->creator_id === $user->id) {
            throw ValidationException::withMessages(['user' => 'Creator cannot leave the group']);
        }
        
        $this->groupRepository->removeMember($group, $user);
        
        // Reorder remaining members
        $this->reorderMembers($group);
        
        // Log user leaving
        activity()
            ->performedOn($group)
            ->causedBy($user)
            ->log('user_left_group');
    }
    
    public function removeMember(Group $group, User $member, User $admin): void
    {
        if (!$this->groupRepository->isAdmin($group, $admin) && $group->creator_id !== $admin->id) {
            throw ValidationException::withMessages(['permission' => 'Not authorized to remove members']);
        }
        
        if ($group->creator_id === $member->id) {
            throw ValidationException::withMessages(['member' => 'Cannot remove group creator']);
        }
        
        if (!$this->groupRepository->isMember($group, $member)) {
            throw ValidationException::withMessages(['member' => 'User is not a member of this group']);
        }
        
        $this->groupRepository->removeMember($group, $member);
        
        // Reorder remaining members
        $this->reorderMembers($group);
        
        // Log member removal
        activity()
            ->performedOn($group)
            ->causedBy($admin)
            ->withProperties(['removed_user_id' => $member->id])
            ->log('member_removed');
    }
    
    public function updateMemberRole(Group $group, User $member, string $role, User $admin): void
    {
        if (!$this->groupRepository->isAdmin($group, $admin) && $group->creator_id !== $admin->id) {
            throw ValidationException::withMessages(['permission' => 'Not authorized to update member roles']);
        }
        
        if ($group->creator_id === $member->id) {
            throw ValidationException::withMessages(['member' => 'Cannot change creator role']);
        }
        
        $this->groupRepository->updateMemberRole($group, $member, $role);
        
        // Log role update
        activity()
            ->performedOn($group)
            ->causedBy($admin)
            ->withProperties([
                'user_id' => $member->id,
                'new_role' => $role
            ])
            ->log('member_role_updated');
    }
    
    public function updateGroupSettings(Group $group, array $settings): Group
    {
        $currentSettings = $group->getSettings();
        $newSettings = array_merge($currentSettings, $settings);
        
        $this->groupRepository->update($group, [
            'settings' => $newSettings
        ]);
        
        return $group->fresh();
    }
    
    public function archiveGroup(Group $group, User $user): Group
    {
        if ($group->creator_id !== $user->id) {
            throw ValidationException::withMessages(['permission' => 'Only creator can archive the group']);
        }
        
        $this->groupRepository->update($group, [
            'status' => 'archived'
        ]);
        
        // Log group archival
        activity()
            ->performedOn($group)
            ->causedBy($user)
            ->log('group_archived');
        
        return $group->fresh();
    }
    
    public function reorderMembers(Group $group): void
    {
        $members = $group->members()->orderBy('pivot_turn_order')->get();
        
        foreach ($members as $index => $member) {
            $this->groupRepository->updateMemberRole($group, $member, $member->pivot->role);
            // Update turn order in pivot table
            $group->members()->updateExistingPivot($member->id, [
                'turn_order' => $index + 1
            ]);
        }
    }
    
    public function searchGroups(string $query): array
    {
        return $this->groupRepository->search($query);
    }
    
    public function getUserGroups(User $user): array
    {
        return $this->groupRepository->findByUser($user);
    }
    
    private function validateGroupData(array $data): void
    {
        if (empty($data['name'])) {
            throw ValidationException::withMessages(['name' => 'Group name is required']);
        }
        
        if (strlen($data['name']) < 3) {
            throw ValidationException::withMessages(['name' => 'Group name must be at least 3 characters']);
        }
    }
    
    private function generateUniqueInviteCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while ($this->groupRepository->findByInviteCode($code));
        
        return $code;
    }
}
