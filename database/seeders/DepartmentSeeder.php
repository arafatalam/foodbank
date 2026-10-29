<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define the main groups of goods handled by the foodbank.
        $names = [
            'Fresh Produce',
            'Dairy and Eggs',
            'Meat and Seafood',
            'Bakery',
            'Frozen Foods',
            'Pantry Staples',
            'Beverages',
            'Baby Care',
            'Personal Care',
            'Household Supplies',
        ];

        // Create each department only if its name does not already exist.
        foreach ($names as $name) {
            Department::firstOrCreate(['name' => $name]);
        }
    }
}
