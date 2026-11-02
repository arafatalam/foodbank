<?php

namespace App\Policies;

use App\Enums\RoleCode;
use App\Models\StockEntry;
use App\Models\User;

class StockEntryPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, StockEntry $stockEntry): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Permit stock recording for all three agreed roles.
        return in_array($user->role->code, [
            RoleCode::SUPER_ADMIN,
            RoleCode::MANAGER,
            RoleCode::VOLUNTEER,
        ], true);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, StockEntry $stockEntry): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, StockEntry $stockEntry): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, StockEntry $stockEntry): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, StockEntry $stockEntry): bool
    {
        return false;
    }
}
