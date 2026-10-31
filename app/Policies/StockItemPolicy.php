<?php

namespace App\Policies;

use App\Enums\RoleCode;
use App\Models\StockItem;
use App\Models\User;

class StockItemPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Allow all three agreed roles to view the stock-item list.
        return in_array($user->role->code, [
            RoleCode::SUPER_ADMIN,
            RoleCode::MANAGER,
            RoleCode::VOLUNTEER,
        ], true);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, StockItem $stockItem): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Read the signed-in user's role.
        $role = $user->role->code;

        // Allow Managers and Super Admins to create stock items.
        return $role === RoleCode::SUPER_ADMIN
            || $role === RoleCode::MANAGER;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, StockItem $stockItem): bool
    {
        // Management may correct brand spelling; the action protects used identity fields.
        return $this->create($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, StockItem $stockItem): bool
    {
        // Empty balance alone never permits deleting a history-bearing item.
        return $this->create($user)
            && $stockItem->available_quantity === 0
            && ! $stockItem->hasMovements();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, StockItem $stockItem): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, StockItem $stockItem): bool
    {
        return false;
    }
}
