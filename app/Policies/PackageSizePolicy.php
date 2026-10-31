<?php

namespace App\Policies;

use App\Enums\RoleCode;
use App\Models\PackageSize;
use App\Models\User;

class PackageSizePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->create($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PackageSize $packageSize): bool
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

        // Permit either management role to create package definitions.
        return $role === RoleCode::SUPER_ADMIN
            || $role === RoleCode::MANAGER;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PackageSize $packageSize): bool
    {
        // Referenced definitions remain immutable for every role.
        return $this->create($user) && ! $packageSize->isReferenced();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PackageSize $packageSize): bool
    {
        return $this->create($user) && ! $packageSize->isReferenced();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PackageSize $packageSize): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PackageSize $packageSize): bool
    {
        return false;
    }
}
