<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        foreach (array_keys(CatalogueData::departments()) as $name) {
            Department::firstOrCreate(['name' => $name]);
        }
    }
}
