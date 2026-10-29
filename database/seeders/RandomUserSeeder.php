<?php

namespace Database\Seeders;

use App\Actions\Users\GenerateUsername;
use App\Enums\RoleCode;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RandomUserSeeder extends Seeder
{
    public function run(): void
    {
        // Save the entire batch, or undo it if anything fails.
        DB::transaction(function (): void {
            $generateUsername = app(GenerateUsername::class);

            // Add 10 managers and 40 volunteers.
            $groups = [
                RoleCode::MANAGER->value => 10,
                RoleCode::VOLUNTEER->value => 40,
            ];

            foreach ($groups as $roleCode => $count) {
                // Find the existing role using its stored code.
                $role = Role::query()
                    ->where('code', $roleCode)
                    ->firstOrFail();

                // Prepare and save one user at a time.
                for ($i = 0; $i < $count; $i++) {
                    // Generate fictional contact details with normal account defaults.
                    $user = User::factory()->make([
                        'role_id' => $role->id,
                        'password' => config('auth.initial_password'),
                        'is_active' => true,
                        'must_change_password' => true,
                    ]);

                    // Follow the same username rules as account creation.
                    $user->username = $generateUsername->generate(
                        $user->first_name,
                        $user->last_name,
                    );

                    // Save before generating the next user's username.
                    $user->save();
                }
            }
        });
    }
}
