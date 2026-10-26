<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (User::where('role', 'super_admin')->exists()) {
            return;
        }

        User::factory()->create([
            'name' => config('superadmin.name'),
            'username' => config('superadmin.username'),
            'email' => config('superadmin.email'),
            'password' => config('superadmin.password'),
            'role' => 'super_admin',
            'is_active' => true,
            'must_change_password' => false,
            'email_verified_at' => now(),
        ]);
    }
}
