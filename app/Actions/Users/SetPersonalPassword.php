<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SetPersonalPassword
{
    // Save the chosen password and mark initial setup as finished.
    public function set(User $user, string $password): void
    {

        // Reject the initial password assigned to this account.
        if (Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Choose a password different from your initial password.',
            ]);
        }

        // Read the current initial password from application configuration.
        $initialPassword = config('auth.initial_password');

        // Reject choosing the current shared initial password.
        if ($password === $initialPassword) {
            throw ValidationException::withMessages([
                'password' => 'Choose a password different from the current initial password.',
            ]);
        }

        $user->update([
            'password' => $password,
            'must_change_password' => false,
        ]);
    }
}
