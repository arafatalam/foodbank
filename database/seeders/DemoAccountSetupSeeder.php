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
        // Allow laptops and tests; other installations need the demo switch enabled.
        if (! app()->environment(['local', 'testing'])
            && ! config('demo.seeding_enabled')) {
            throw new RuntimeException('Enable DEMO_SEEDING_ENABLED before seeding online demo accounts.');
        }

        // Require both configured passwords outside local development and tests.
        if (! app()->environment(['local', 'testing'])) {
            foreach (['demo.manager_password', 'demo.volunteer_password'] as $setting) {
                $password = config($setting);

                // Stop if either password is missing or contains only spaces.
                if (! is_string($password) || trim($password) === '') {
                    throw new RuntimeException("Set {$setting} before seeding online demo accounts.");
                }
            }
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

                // Keep convenient passwords on laptops and during tests.
                if (app()->environment(['local', 'testing'])) {
                    $user->password = $username;
                } elseif ($role === RoleCode::MANAGER) {
                    // Read the manager's password from this installation's settings.
                    $user->password = config('demo.manager_password');
                } else {
                    // The other account in this seeder is the volunteer.
                    $user->password = config('demo.volunteer_password');
                }

                $user->must_change_password = false;
                $user->save();
            }
        });
    }
}
