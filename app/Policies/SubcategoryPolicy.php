<?php

namespace App\Policies;

use App\Enums\RoleCode;
use App\Models\Subcategory;
use App\Models\User;

class SubcategoryPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Use the same catalogue list permission as Departments and Categories.
        $role = $user->role->code;

        return $role === RoleCode::SUPER_ADMIN
            || $role === RoleCode::MANAGER;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Subcategory $subcategory): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Use the same creation permission as Departments and Categories.
        $role = $user->role->code;

        return $role === RoleCode::SUPER_ADMIN
            || $role === RoleCode::MANAGER;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Subcategory $subcategory): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Subcategory $subcategory): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Subcategory $subcategory): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Subcategory $subcategory): bool
    {
        return false;
    }
}
