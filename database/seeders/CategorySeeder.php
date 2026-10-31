<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (CatalogueData::departments() as $name => $categories) {
            $department = Department::query()->where('name', $name)->firstOrFail();
            foreach (array_keys($categories) as $category) {
                $department->categories()->firstOrCreate(['name' => $category]);
            }
        }
    }
}
