<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        foreach (CatalogueData::departments() as $name => $categories) {
            $department = Department::query()->where('name', $name)->firstOrFail();
            foreach ($categories as $categoryName => $products) {
                $category = $department->categories()->where('name', $categoryName)->firstOrFail();
                foreach ($products as $product) {
                    // Rerunning the seeder keeps existing products and their status intact.
                    $category->products()->firstOrCreate(['name' => $product], ['is_active' => true]);
                }
            }
        }
    }
}
