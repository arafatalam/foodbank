<?php

namespace Database\Seeders;

use App\Enums\RoleCode;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (RoleCode::cases() as $roleCode) {
            Role::firstOrCreate([
                'code' => $roleCode->value,
            ]);
        }
    }
}
