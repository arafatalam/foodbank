<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubcategorySeeder extends Seeder
{
    public function run(): void
    {
        // Use names and product types only: no sizes, units or quantities.
        $subcategoriesByDepartment = [
            'Fresh Produce' => [
                'Fruit' => ['Apples', 'Bananas', 'Citrus Fruit', 'Berries', 'Melons'],
                'Vegetables' => ['Leafy Greens', 'Root Vegetables', 'Squash', 'Peppers', 'Tomatoes'],
                'Fresh Herbs' => ['Basil', 'Cilantro', 'Parsley', 'Mint', 'Rosemary'],
            ],
            'Dairy and Eggs' => [
                'Milk' => ['Whole Milk', 'Reduced-Fat Milk', 'Skim Milk', 'Lactose-Free Milk', 'Flavoured Milk'],
                'Cheese' => ['Cheddar', 'Mozzarella', 'Cream Cheese', 'Cottage Cheese', 'Feta'],
                'Eggs' => ['White Chicken Eggs', 'Brown Chicken Eggs', 'Free-Range Eggs', 'Organic Eggs', 'Duck Eggs'],
            ],
            'Meat and Seafood' => [
                'Poultry' => ['Chicken', 'Turkey', 'Duck', 'Ground Poultry', 'Poultry Sausages'],
                'Red Meat' => ['Beef', 'Pork', 'Lamb', 'Ground Red Meat', 'Red Meat Sausages'],
                'Fish and Seafood' => ['Salmon', 'Tuna', 'White Fish', 'Shrimp', 'Mussels'],
            ],
            'Bakery' => [
                'Bread' => ['White Bread', 'Whole Wheat Bread', 'Multigrain Bread', 'Rye Bread', 'Gluten-Free Bread'],
                'Rolls and Buns' => ['Dinner Rolls', 'Hamburger Buns', 'Hot Dog Buns', 'Sandwich Rolls', 'Whole Wheat Buns'],
                'Pastries' => ['Croissants', 'Muffins', 'Doughnuts', 'Danish Pastries', 'Fruit Turnovers'],
            ],
            'Frozen Foods' => [
                'Frozen Vegetables' => ['Peas', 'Corn', 'Broccoli', 'Spinach', 'Mixed Vegetables'],
                'Frozen Fruit' => ['Strawberries', 'Blueberries', 'Mango', 'Peaches', 'Mixed Fruit'],
                'Frozen Meals' => ['Pizza', 'Lasagna', 'Curries', 'Stir-Fry Meals', 'Vegetarian Meals'],
            ],
            'Pantry Staples' => [
                'Rice and Grains' => ['White Rice', 'Brown Rice', 'Oats', 'Quinoa', 'Barley'],
                'Pasta' => ['Spaghetti', 'Penne', 'Macaroni', 'Lasagna Sheets', 'Gluten-Free Pasta'],
                'Canned Goods' => ['Canned Beans', 'Canned Vegetables', 'Canned Fruit', 'Canned Fish', 'Canned Soup'],
            ],
            'Beverages' => [
                'Water' => ['Spring Water', 'Purified Water', 'Mineral Water', 'Sparkling Water', 'Flavoured Water'],
                'Juice' => ['Apple Juice', 'Orange Juice', 'Grape Juice', 'Cranberry Juice', 'Vegetable Juice'],
                'Tea and Coffee' => ['Black Tea', 'Green Tea', 'Herbal Tea', 'Ground Coffee', 'Instant Coffee'],
            ],
            'Baby Care' => [
                'Diapers' => ['Disposable Diapers', 'Cloth Diapers', 'Overnight Diapers', 'Swim Diapers', 'Training Pants'],
                'Baby Wipes' => ['Fragrance-Free Wipes', 'Sensitive Skin Wipes', 'Water-Based Wipes', 'Plant-Based Wipes', 'Scented Wipes'],
                'Infant Formula' => ['Standard Milk-Based Formula', 'Soy-Based Formula', 'Lactose-Free Formula', 'Hypoallergenic Formula', 'Specialty Formula'],
            ],
            'Personal Care' => [
                'Oral Care' => ['Toothpaste', 'Toothbrushes', 'Dental Floss', 'Mouthwash', 'Denture Care'],
                'Hair Care' => ['Shampoo', 'Conditioner', 'Detangling Products', 'Styling Products', 'Scalp Care'],
                'Bath and Body' => ['Body Wash', 'Bar Soap', 'Hand Soap', 'Body Lotion', 'Deodorant'],
            ],
            'Household Supplies' => [
                'Laundry Supplies' => ['Laundry Detergent', 'Fabric Softener', 'Stain Remover', 'Laundry Bleach', 'Dryer Sheets'],
                'Dishwashing Supplies' => ['Dish Soap', 'Dishwasher Detergent', 'Dishwasher Rinse Aid', 'Dish Sponges', 'Dish Brushes'],
                'Paper Products' => ['Toilet Paper', 'Paper Towels', 'Facial Tissues', 'Paper Napkins', 'Paper Plates'],
            ],
        ];

        // Save the complete set together, or roll it back if a parent is missing.
        DB::transaction(function () use ($subcategoriesByDepartment) {
            foreach ($subcategoriesByDepartment as $departmentName => $categories) {
                foreach ($categories as $categoryName => $subcategoryNames) {
                    // Match both parent names so equally named categories stay separate.
                    $category = Category::query()
                        ->where('name', $categoryName)
                        ->whereHas('department', fn ($query) => $query->where('name', $departmentName))
                        ->sole();

                    foreach ($subcategoryNames as $name) {
                        // The relationship supplies category_id and reruns reuse existing rows.
                        $category->subcategories()->firstOrCreate(['name' => $name]);
                    }
                }
            }
        });
    }
}
