<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveUser
{
    public function __construct(private readonly GenerateUsername $generateUsername) {}

    /** @param array{first_name: string, last_name: string, email: string, phone: string, role_id: int|string, is_active: bool|int|string} $data */
    public function save(array $data, ?User $existingUser = null): User
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                // Roll back a failed save before generating another username.
                return DB::transaction(fn () => $this->saveWithinTransaction($data, $existingUser), attempts: 3);
            } catch (UniqueConstraintViolationException $exception) {
                // Retry only the username index; other database errors keep their meaning.
                if ($exception->index !== 'users_username_unique'
                    && $exception->columns !== ['username']
                    && $exception->columns !== ['users.username']) {
                    throw $exception;
                }
            }
        }

        throw ValidationException::withMessages([
            'first_name' => 'Accounts with this name are being saved right now. Please try saving again.',
        ]);
    }

    /** @param array{first_name: string, last_name: string, email: string, phone: string, role_id: int|string, is_active: bool|int|string} $data */
    private function saveWithinTransaction(array $data, ?User $existingUser): User
    {
        // Reload edits so every attempt starts from the saved account state.
        $user = $existingUser
            ? User::query()->lockForUpdate()->findOrFail($existingUser->id)
            : new User;

        if (! $user->exists || $data['first_name'] !== $user->first_name || $data['last_name'] !== $user->last_name) {
            $data['username'] = $this->generateUsername->generate(
                $data['first_name'], $data['last_name'], $user->exists ? $user : null
            );
        }

        if (! $user->exists) {
            $data['password'] = config('auth.initial_password');
            $data['must_change_password'] = true;
            $user->email_verified_at = now();
        }

        $deactivating = $user->exists && $user->is_active && ! (bool) $data['is_active'];
        if ($deactivating) {
            $user->setRememberToken(Str::random(60));
        }

        $user->fill($data)->save();

        // Keep deactivation and session removal in the same successful transaction.
        if ($deactivating && config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)->delete();
        }

        return $user;
    }
}
