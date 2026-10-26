<?php

namespace Database\Seeders;

use App\Actions\Users\GenerateUsername;
use App\Enums\RoleCode;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        // These sample accounts are only for local development.
        if (! app()->environment('local')) {
            $this->command?->warn('Demo users can only be seeded locally.');

            return;
        }

        // Save the whole batch together, or roll it back if anything fails.
        DB::transaction(function (): void {
            $this->call(RoleSeeder::class);

            $groups = [
                RoleCode::MANAGER->value => [
                    ['Maya', 'Chen'],
                    ['Omar', 'Patel'],
                ],
                RoleCode::VOLUNTEER->value => [
                    ['Alice', 'Martin'],
                    ['Ben', 'Wilson'],
                    ['Clara', 'Roy'],
                    ['David', 'Lee'],
                    ['Emma', 'Brown'],
                    ['Farah', 'Khan'],
                    ['Grace', 'Taylor'],
                    ['Hugo', 'Leblanc'],
                    ['Isla', 'Scott'],
                    ['Jonah', 'Young'],
                ],
            ];

            $generateUsername = app(GenerateUsername::class);
            $phoneNumber = 101;

            foreach ($groups as $roleCode => $names) {
                $role = Role::query()->where('code', $roleCode)->firstOrFail();

                foreach ($names as [$firstName, $lastName]) {
                    $email = strtolower($firstName.'.'.$lastName).'@example.com';
                    $phone = '+1 506-555-0'.$phoneNumber++;

                    // Keep existing accounts and their passwords when rerunning.
                    if (User::query()->where('email', $email)->exists()) {
                        continue;
                    }

                    $user = new User([
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'email' => $email,
                        'phone' => $phone,
                        'username' => $generateUsername->generate($firstName, $lastName),
                        'role_id' => $role->id,
                        'is_active' => true,
                        'password' => config('auth.initial_password'),
                        'must_change_password' => true,
                    ]);

                    // Fictional addresses need no real verification email.
                    $user->email_verified_at = now();
                    $user->save();
                }
            }
        });
    }
}
