<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ListUsers
{
    /**
     * @param  array{search?: string|null}  $filters
     * @return LengthAwarePaginator<int, array<string, int|string|bool>>
     */
    public function get(array $filters, User $actor): LengthAwarePaginator
    {

        // Give the filters to the method that builds the database query.
        $query = $this->buildQuery($filters);

        // Retrieve 10 users and keep the search in generated page links.
        $users = $query->paginate(10)->appends($filters);

        // Give each row's preparation method the signed-in person.
        return $users->through(
            fn (User $user) => $this->prepareUser($user, $actor)
        );
    }

    // Receive the filters needed to prepare the database query.
    /**
     * @param  array{search?: string|null}  $filters
     * @return Builder<User>
     */
    private function buildQuery(array $filters): Builder
    {
        // Use the search text, or empty text when no search was supplied.
        $search = $filters['search'] ?? '';

        // Select the required fields, load roles, and keep a stable order.
        return User::query()
            ->select([
                'id',
                'first_name',
                'last_name',
                'email',
                'phone',
                'role_id',
                'is_active',
            ])
            ->with('role')
            // Search only when text has been supplied.
            ->when($search !== '', function (Builder $query) use ($search) {
                // Keep all the alternatives together.
                $query->where(function (Builder $query) use ($search) {
                    // Match any of the user's contact fields.
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        // Also match the user's related role.
                        ->orWhereHas('role', function (Builder $roleQuery) use ($search) {
                            // The displayed "Super Admin" is stored as "super_admin".
                            $roleSearch = str_replace(' ', '_', $search);

                            // Allow partial role matches, such as "mana".
                            $roleQuery->where('code', 'like', "%{$roleSearch}%");
                        });
                });
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->orderBy('id');
    }

    // Receive both the listed account and the person viewing it.
    /**
     * @return array<string, int|string|bool>
     */
    private function prepareUser(User $user, User $actor): array
    {
        // Prepare the user's identity and contact fields.
        $userData = $user->only([
            'id',
            'first_name',
            'last_name',
            'email',
            'phone',
        ]);

        // Add the readable role name.
        $userData['role'] = $user->role->code->label();

        // Turn the stored true/false value into readable status text.
        $userData['status'] = $user->is_active ? 'Active' : 'Inactive';

        // Ask the existing update policy whether this account may be edited.
        $userData['canEdit'] = $actor->can('update', $user);

        // Return this user's prepared data.
        return $userData;
    }
}
