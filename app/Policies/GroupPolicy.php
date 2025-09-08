<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('groups.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Group $group): bool
    {
        if (!$user->can('groups.view')) {
            return false;
        }

        // Users can view groups they're members of or created
        return $group->creator_id === $user->id || 
               $group->members()->where('user_id', $user->id)->where('is_active', true)->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('groups.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Group $group): bool
    {
        if (!$user->can('groups.update')) {
            return false;
        }

        // Only creator or admins can update
        return $group->creator_id === $user->id || 
               $group->members()->where('user_id', $user->id)->where('role', 'admin')->where('is_active', true)->exists();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Group $group): bool
    {
        if (!$user->can('groups.delete')) {
            return false;
        }

        // Only creator can delete
        return $group->creator_id === $user->id;
    }

    /**
     * Determine whether the user can join the group.
     */
    public function join(User $user, Group $group): bool
    {
        if (!$user->can('groups.join')) {
            return false;
        }

        // Can't join if already a member
        if ($group->members()->where('user_id', $user->id)->exists()) {
            return false;
        }

        // Can only join active groups
        return $group->status === 'active';
    }

    /**
     * Determine whether the user can leave the group.
     */
    public function leave(User $user, Group $group): bool
    {
        if (!$user->can('groups.leave')) {
            return false;
        }

        // Creator cannot leave their own group
        if ($group->creator_id === $user->id) {
            return false;
        }

        // Must be an active member to leave
        return $group->members()->where('user_id', $user->id)->where('is_active', true)->exists();
    }

    /**
     * Determine whether the user can manage members.
     */
    public function manageMembers(User $user, Group $group): bool
    {
        if (!$user->can('groups.members.add') && !$user->can('groups.members.remove')) {
            return false;
        }

        // Only creator or admins can manage members
        return $group->creator_id === $user->id || 
               $group->members()->where('user_id', $user->id)->where('role', 'admin')->where('is_active', true)->exists();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Group $group): bool
    {
        return $group->creator_id === $user->id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Group $group): bool
    {
        return $group->creator_id === $user->id;
    }

    /**
     * Determine whether the user can view analytics for the group.
     */
    public function viewAnalytics(User $user, Group $group): bool
    {
        // All group members can view analytics
        return $this->view($user, $group);
    }

    /**
     * Determine whether the user can manage analytics for the group.
     */
    public function manageAnalytics(User $user, Group $group): bool
    {
        // Only admins and creator can manage analytics (clear cache, etc.)
        return $this->manage($user, $group);
    }
}
