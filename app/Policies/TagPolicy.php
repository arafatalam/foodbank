<?php

namespace App\Policies;

use App\Enums\RoleCode;
use App\Models\Tag;
use App\Models\User;

class TagPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Read the signed-in person's role.
        $role = $user->role->code;

        // Allow Managers and Super Admin to view the tag list.
        return $role === RoleCode::SUPER_ADMIN
            || $role === RoleCode::MANAGER;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Tag $tag): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Read the signed-in person's role.
        $role = $user->role->code;

        // Allow either of these two roles to create tags.
        return $role === RoleCode::SUPER_ADMIN
            || $role === RoleCode::MANAGER;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Tag $tag): bool
    {
        // Read the signed-in person's role.
        $role = $user->role->code;

        // Allow Managers and Super Admin to rename tags.
        return $role === RoleCode::SUPER_ADMIN
            || $role === RoleCode::MANAGER;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Tag $tag): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Tag $tag): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Tag $tag): bool
    {
        return false;
    }
}
