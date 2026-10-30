<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define reusable labels for food, personal care and household goods.
        $names = [
            'Gluten-Free',
            'Dairy-Free',
            'Lactose-Free',
            'Nut-Free',
            'Peanut-Free',
            'Soy-Free',
            'Egg-Free',
            'Sesame-Free',
            'Vegan',
            'Vegetarian',
            'Halal',
            'Kosher',
            'Organic',
            'Whole Grain',
            'High Fibre',
            'High Protein',
            'Low Sodium',
            'No Added Salt',
            'No Added Sugar',
            'Unsweetened',
            'Fortified',
            'Shelf-Stable',
            'Refrigerated',
            'Frozen',
            'Keep Dry',
            'Ready to Eat',
            'Ready to Heat',
            'Cooking Required',
            'No-Cook',
            'Easy-Open',
            'Resealable',
            'Single Serving',
            'Family Size',
            'Breakfast',
            'Lunch',
            'Dinner',
            'Snack',
            'Baking',
            'School Lunch',
            'Baby Food',
            'Locally Produced',
            'Canadian Made',
            'Seasonal',
            'Fragrance-Free',
            'Sensitive Skin',
            'Alcohol-Free',
            'Reusable',
            'Biodegradable',
            'Septic-Safe',
            'Concentrated',
        ];

        // Save the whole set together, or undo this run if any insert fails.
        DB::transaction(function () use ($names) {
            foreach ($names as $name) {
                // Reuse an existing tag instead of adding it again on later runs.
                Tag::firstOrCreate(['name' => $name]);
            }
        });
    }
}
