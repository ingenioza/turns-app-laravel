<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        // Users can view their own profile or if they share groups
        return $user->id === $model->id || $this->shareGroups($user, $model);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // Users can only update their own profile
        return $user->id === $model->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // Users can only delete their own account
        return $user->id === $model->id;
    }

    /**
     * Determine whether the user can view analytics for the model.
     */
    public function viewAnalytics(User $user, User $model): bool
    {
        // Users can only view their own analytics
        return $user->id === $model->id;
    }

    /**
     * Check if two users share any groups
     */
    private function shareGroups(User $user1, User $user2): bool
    {
        $user1Groups = $user1->groups()->pluck('groups.id');
        $user2Groups = $user2->groups()->pluck('groups.id');

        return $user1Groups->intersect($user2Groups)->isNotEmpty();
    }
}
