<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            SuperAdminSeeder::class,
            UserSeeder::class,
            DemoAccountSetupSeeder::class,
            DepartmentSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            PackageSizeSeeder::class,
            StockItemSeeder::class,
            HouseholdSeeder::class,
            StockHistorySeeder::class,
        ]);
    }
}
