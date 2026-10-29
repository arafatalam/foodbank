<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GenerateUsername
{
    // When editing, optionally receive the account to exclude from duplicate checks.
    public function generate(
        string $firstName,
        string $lastName,
        ?User $ignoreUser = null
    ): string {

        // Reject unexpected characters like @ before changing both names.
        if (! preg_match("/\A[\p{L}\p{M} '’-]+\z/u", $firstName)) {
            throw ValidationException::withMessages([
                'first_name' => 'Use only letters, spaces, hyphens and apostrophes.',
            ]);
        }
        if (! preg_match("/\A[\p{L}\p{M} '’-]+\z/u", $lastName)) {
            throw ValidationException::withMessages([
                'last_name' => 'Use only letters, spaces, hyphens and apostrophes.',
            ]);
        }

        // Convert accented letters to plain letters, such as é to e.
        $firstName = Str::ascii($firstName);
        $lastName = Str::ascii($lastName);

        // Remove spaces, hyphens and apostrophes from both name parts.
        $firstName = str_replace([' ', '-', "'"], '', $firstName);
        $lastName = str_replace([' ', '-', "'"], '', $lastName);

        // Make sure the cleaned first name and last name contain at least one plain letter
        // "O'Connor" becomes "OConnor" and passes; "---" becomes empty and fails.
        if (! preg_match('/\A[a-z]+\z/i', $firstName)) {
            throw ValidationException::withMessages([
                'first_name' => 'Please check the first name. It cannot produce a supported username.',
            ]);
        }

        if (! preg_match('/\A[a-z]+\z/i', $lastName)) {
            throw ValidationException::withMessages([
                'last_name' => 'Please check the last name. It cannot produce a supported username.',
            ]);
        }

        $base = mb_strtolower($firstName.'.'.$lastName);

        $username = $base;
        $suffix = 1;

        // Keep trying while another account already has this username.
        while (
            User::query()
                ->where('username', $username)
                ->when(
                    $ignoreUser !== null,
                    // Leave the account being edited out of the duplicate check.
                    fn ($query) => $query->where('id', '!=', $ignoreUser->id)
                )
                ->exists()
        ) {
            // Try the next numbered username.
            $username = $base.'.'.str_pad((string) $suffix, 2, '0', STR_PAD_LEFT);

            // Prepare the next number if that username is also taken.
            $suffix++;
        }

        return $username;
    }
}
