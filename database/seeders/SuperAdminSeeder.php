<?php

namespace Database\Seeders;

use App\Enums\RoleCode;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $role = Role::query()->where('code', RoleCode::SUPER_ADMIN->value)->firstOrFail();

        if (User::query()->where('role_id', $role->id)->exists()) {
            return;
        }

        // Read the password configured for this installation.
        $password = config('superadmin.password');

        // Stop before creating an account if no password was supplied.
        if (! is_string($password) || trim($password) === '') {
            throw new RuntimeException('Set SUPER_ADMIN_PASSWORD before creating the Super Admin.');
        }

        $user = new User([
            'first_name' => config('superadmin.first_name'),
            'last_name' => config('superadmin.last_name'),
            'phone' => config('superadmin.phone'),
            'username' => config('superadmin.username'),
            'email' => config('superadmin.email'),
            // Use the password we checked above.
            'password' => $password,
            'role_id' => $role->id,
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $user->email_verified_at = now();
        $user->save();
    }
}
