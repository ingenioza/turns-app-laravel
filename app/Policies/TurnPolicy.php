<?php

namespace App\Policies;

use App\Models\Turn;
use App\Models\User;

class TurnPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('turns.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Turn $turn): bool
    {
        if (!$user->can('turns.view')) {
            return false;
        }

        // Users can view turns in groups they're members of
        return $turn->group->members()->where('user_id', $user->id)->where('is_active', true)->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('turns.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Turn $turn): bool
    {
        if (!$user->can('turns.update')) {
            return false;
        }

        // Only the turn owner or group admins can update
        return $turn->user_id === $user->id || 
               $turn->group->members()->where('user_id', $user->id)->where('role', 'admin')->where('is_active', true)->exists();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Turn $turn): bool
    {
        if (!$user->can('turns.delete')) {
            return false;
        }

        // Only the turn owner or group creator/admins can delete
        return $turn->user_id === $user->id || 
               $turn->group->creator_id === $user->id ||
               $turn->group->members()->where('user_id', $user->id)->where('role', 'admin')->where('is_active', true)->exists();
    }

    /**
     * Determine whether the user can assign turns.
     */
    public function assign(User $user, Turn $turn): bool
    {
        if (!$user->can('turns.assign')) {
            return false;
        }

        // Only group creator or admins can assign turns
        return $turn->group->creator_id === $user->id ||
               $turn->group->members()->where('user_id', $user->id)->where('role', 'admin')->where('is_active', true)->exists();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Turn $turn): bool
    {
        return $turn->group->creator_id === $user->id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Turn $turn): bool
    {
        return $turn->group->creator_id === $user->id;
    }
}
