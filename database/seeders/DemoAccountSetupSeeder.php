<?php

namespace Database\Seeders;

use App\Enums\RoleCode;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoAccountSetupSeeder extends Seeder
{
    public function run(): void
    {
        // These predictable demo passwords are only for local development and tests.
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DemoAccountSetupSeeder is only for local or testing environments.');
        }

        // Prepare both accounts together; an error undoes this run's changes.
        DB::transaction(function (): void {
            $accounts = [
                'amina.rahman' => RoleCode::MANAGER,
                'farah.khan' => RoleCode::VOLUNTEER,
            ];

            foreach ($accounts as $username => $role) {
                // Match the exact fictional account and its expected role, not a database ID.
                $user = User::query()
                    ->where('username', $username)
                    ->where('email', $username.'@example.com')
                    ->where('is_active', true)
                    ->whereHas('role', fn ($query) => $query->where('code', $role->value))
                    ->lockForUpdate()
                    ->firstOrFail();

                // Preserve any password chosen after this account completed setup.
                if (! $user->must_change_password) {
                    continue;
                }

                // The User model hashes this password before saving it.
                $user->password = $username;
                $user->must_change_password = false;
                $user->save();
            }
        });
    }
}
