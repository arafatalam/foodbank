<?php

namespace App\Actions\Users;

use App\Enums\RoleCode;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

class GetAssignableRoles
{
    /**
     * @return Collection<int, array{id: int, label: string}>
     */
    public function get(User $actor, bool $forEdit = false): Collection
    {
        $actorRole = $actor->role->code;
        $allowedCodes = match ($actorRole) {
            RoleCode::SUPER_ADMIN => [
                RoleCode::MANAGER->value,
                RoleCode::VOLUNTEER->value,
            ],
            // Edit offers promotion; Create keeps its volunteer-only choices.
            RoleCode::MANAGER => $forEdit
                ? [RoleCode::MANAGER->value, RoleCode::VOLUNTEER->value]
                : [RoleCode::VOLUNTEER->value],
            default => [],
        };

        return Role::query()
            ->whereIn('code', $allowedCodes)
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'label' => $role->code->label(),
            ]);
    }
}
