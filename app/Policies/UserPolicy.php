<?php

namespace App\Policies;

use App\Enums\RoleCode;
use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        // Allow users to view their own account details.
        if ($user->id === $model->id) {
            return true;
        }

        // Read the roles of the person viewing and the account being viewed.
        $viewerRole = $user->role->code;
        $targetRole = $model->role->code;

        // Super Admins can view manager and volunteer accounts.
        if ($viewerRole === RoleCode::SUPER_ADMIN) {
            return in_array($targetRole, [RoleCode::MANAGER, RoleCode::VOLUNTEER], true);
        }

        // Managers can view volunteer accounts only.
        return $viewerRole === RoleCode::MANAGER
            && $targetRole === RoleCode::VOLUNTEER;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        $role = $user->role->code;

        return $role === RoleCode::SUPER_ADMIN || $role === RoleCode::MANAGER;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // Read the signed-in person's role and the target account's role.
        $actorRole = $user->role->code;
        $targetRole = $model->role->code;

        // Super Admin may edit manager and volunteer accounts.
        if ($actorRole === RoleCode::SUPER_ADMIN) {
            return in_array(
                $targetRole,
                [RoleCode::MANAGER, RoleCode::VOLUNTEER],
                true
            );
        }

        // A manager may edit volunteer accounts only.
        return $actorRole === RoleCode::MANAGER
            && $targetRole === RoleCode::VOLUNTEER;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }
}
