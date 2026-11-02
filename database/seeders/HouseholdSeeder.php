<?php

namespace Database\Seeders;

use App\Models\Household;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HouseholdSeeder extends Seeder
{
    public function run(): void
    {
        // Use fixed fictional names so every sample household has a different full name.
        $households = [
            ['first_name' => 'Amelia', 'last_name' => 'Bennett'],
            ['first_name' => 'Noah', 'last_name' => 'Carter'],
            ['first_name' => 'Olivia', 'last_name' => 'Chen'],
            ['first_name' => 'Liam', 'last_name' => 'Clarke'],
            ['first_name' => 'Sophia', 'last_name' => 'Desjardins'],
            ['first_name' => 'Ethan', 'last_name' => 'Dubois'],
            ['first_name' => 'Isabella', 'last_name' => 'Evans'],
            ['first_name' => 'Lucas', 'last_name' => 'Fraser'],
            ['first_name' => 'Mia', 'last_name' => 'Gagnon'],
            ['first_name' => 'Mason', 'last_name' => 'Hassan'],
            ['first_name' => 'Charlotte', 'last_name' => 'Hughes'],
            ['first_name' => 'Oliver', 'last_name' => 'Khan'],
            ['first_name' => 'Grace', 'last_name' => 'LeBlanc'],
            ['first_name' => 'Henry', 'last_name' => 'MacDonald'],
            ['first_name' => 'Zoe', 'last_name' => 'Martin'],
            ['first_name' => 'James', 'last_name' => 'Nguyen'],
            ['first_name' => 'Layla', 'last_name' => 'Patel'],
            ['first_name' => 'Benjamin', 'last_name' => 'Roy'],
            ['first_name' => 'Chloe', 'last_name' => 'Singh'],
            ['first_name' => 'William', 'last_name' => 'Thompson'],
        ];

        // Save the sample set together; a failure rolls back this run's changes.
        DB::transaction(function () use ($households): void {
            foreach ($households as $household) {
                // Reuse an existing matching name pair when this seeder is run again.
                Household::firstOrCreate($household);
            }
        });
    }
}
