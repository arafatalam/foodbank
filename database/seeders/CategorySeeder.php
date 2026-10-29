<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define three everyday category examples for each existing department.
        $categoriesByDepartment = [
            'Fresh Produce' => ['Fruit', 'Vegetables', 'Fresh Herbs'],
            'Dairy and Eggs' => ['Milk', 'Cheese', 'Eggs'],
            'Meat and Seafood' => ['Poultry', 'Red Meat', 'Fish and Seafood'],
            'Bakery' => ['Bread', 'Rolls and Buns', 'Pastries'],
            'Frozen Foods' => ['Frozen Vegetables', 'Frozen Fruit', 'Frozen Meals'],
            'Pantry Staples' => ['Rice and Grains', 'Pasta', 'Canned Goods'],
            'Beverages' => ['Water', 'Juice', 'Tea and Coffee'],
            'Baby Care' => ['Diapers', 'Baby Wipes', 'Infant Formula'],
            'Personal Care' => ['Oral Care', 'Hair Care', 'Bath and Body'],
            'Household Supplies' => ['Laundry Supplies', 'Dishwashing Supplies', 'Paper Products'],
        ];

        foreach ($categoriesByDepartment as $departmentName => $categoryNames) {
            // Find the saved parent rather than creating another department.
            $department = Department::query()
                ->where('name', $departmentName)
                ->firstOrFail();

            foreach ($categoryNames as $categoryName) {
                // The relationship supplies department_id and avoids duplicates on reruns.
                $department->categories()->firstOrCreate(['name' => $categoryName]);
            }
        }
    }
}
