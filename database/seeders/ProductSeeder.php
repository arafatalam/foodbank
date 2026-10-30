<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Subcategory;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Seed all examples together; any missing hierarchy rolls this run back.
        DB::transaction(function () {
            $tagIdsByName = [];

            foreach ($this->catalogue() as $departmentName => $categories) {
                foreach ($categories as $categoryName => $subcategories) {
                    // Match the full path rather than hard-coding database IDs.
                    $category = Category::query()
                        ->where('name', $categoryName)
                        ->whereHas('department', fn (Builder $query) => $query->where('name', $departmentName))
                        ->sole();

                    foreach ($subcategories as $subcategoryName => $examples) {
                        // Use the same Subcategory lock as normal Product creation.
                        $subcategory = Subcategory::query()
                            ->where('category_id', $category->id)
                            ->where('name', $subcategoryName)
                            ->lockForUpdate()
                            ->sole();

                        foreach ($examples['products'] as $example) {
                            // Reuse the same name/brand/Subcategory on subsequent runs.
                            // Existing records keep their current status and other values.
                            $product = Product::firstOrCreate([
                                'subcategory_id' => $subcategory->id,
                                'name' => $example['name'],
                                'brand' => $example['brand'],
                            ], ['is_active' => true]);

                            $tagNames = array_unique([
                                ...$examples['tags'],
                                ...($example['tags'] ?? []),
                            ]);
                            $tagIds = [];

                            foreach ($tagNames as $tagName) {
                                // Reuse shared tags and create a missing label only once.
                                if (! isset($tagIdsByName[$tagName])) {
                                    $tagIdsByName[$tagName] = Tag::firstOrCreate(['name' => $tagName])->id;
                                }

                                $tagIds[] = $tagIdsByName[$tagName];
                            }

                            // Add the example tags without removing any manually selected tags.
                            $product->tags()->syncWithoutDetaching($tagIds);
                        }
                    }
                }
            }
        });
    }

    /**
     * @return array<string, array<string, array<string, array{tags: list<string>, products: list<array{name: string, brand: string, tags?: list<string>}>}>>>
     */
    private function catalogue(): array
    {
        // Representative development data: five branded products per existing Subcategory.
        // No package sizes, quantities, product codes or stock movements are created.
        // These examples are not a verified current local retailer inventory.
        // Niche examples checked against producer pages:
        // https://www.metzerfarms.com/purchase-fresh-duck-eggs.html
        // https://www.clarencecourt.co.uk/our-eggs/egg-range/
        // https://www.smahcp.co.uk/formula-milk/lactose-free-formula
        // https://www.enfamil.com/prosobee/
        // https://zabihahalal.com/products/chicken-drumsticks/
        return [
            'Fresh Produce' => [
                'Fruit' => [
                    'Apples' => [
                        'tags' => ['No-Cook', 'Snack'],
                        'products' => [
                            ['name' => 'Gala Apples', 'brand' => 'Stemilt'],
                            ['name' => 'Honeycrisp Apples', 'brand' => 'Rainier Fruit'],
                            ['name' => 'Pink Lady Apples', 'brand' => 'Pink Lady'],
                            ['name' => 'Ambrosia Apples', 'brand' => 'CMI Orchards'],
                            ['name' => 'Granny Smith Apples', 'brand' => 'Washington Fruit'],
                        ],
                    ],
                    'Bananas' => [
                        'tags' => ['No-Cook', 'Snack'],
                        'products' => [
                            ['name' => 'Yellow Bananas', 'brand' => 'Dole'],
                            ['name' => 'Fresh Bananas', 'brand' => 'Chiquita'],
                            ['name' => 'Organic Bananas', 'brand' => 'Del Monte'],
                            ['name' => 'Cavendish Bananas', 'brand' => 'Fyffes'],
                            ['name' => 'Organic Fairtrade Bananas', 'brand' => 'Equal Exchange'],
                        ],
                    ],
                    'Citrus Fruit' => [
                        'tags' => ['No-Cook', 'Snack'],
                        'products' => [
                            ['name' => 'Navel Oranges', 'brand' => 'Sunkist'],
                            ['name' => 'Mandarins', 'brand' => 'Cuties'],
                            ['name' => 'Clementines', 'brand' => 'Wonderful Halos'],
                            ['name' => 'Lemons', 'brand' => 'Sunkist'],
                            ['name' => 'Grapefruit', 'brand' => 'Ocean Spray'],
                        ],
                    ],
                    'Berries' => [
                        'tags' => ['Refrigerated', 'Snack'],
                        'products' => [
                            ['name' => 'Strawberries', 'brand' => 'Driscoll\'s'],
                            ['name' => 'Blueberries', 'brand' => 'Naturipe'],
                            ['name' => 'Raspberries', 'brand' => 'Driscoll\'s'],
                            ['name' => 'Blackberries', 'brand' => 'BerryWorld'],
                            ['name' => 'Organic Blueberries', 'brand' => 'Wish Farms'],
                        ],
                    ],
                    'Melons' => [
                        'tags' => ['No-Cook', 'Snack'],
                        'products' => [
                            ['name' => 'Cantaloupe', 'brand' => 'Dole'],
                            ['name' => 'Honeydew Melon', 'brand' => 'Del Monte'],
                            ['name' => 'Seedless Watermelon', 'brand' => 'Dulcinea'],
                            ['name' => 'Personal Watermelon', 'brand' => 'Dulcinea'],
                            ['name' => 'Cantaloupe', 'brand' => 'Fyffes'],
                        ],
                    ],
                ],
                'Vegetables' => [
                    'Leafy Greens' => [
                        'tags' => ['Refrigerated', 'No-Cook'],
                        'products' => [
                            ['name' => 'Baby Spinach', 'brand' => 'Earthbound Farm'],
                            ['name' => 'Spring Mix', 'brand' => 'Dole'],
                            ['name' => 'Baby Arugula', 'brand' => 'Fresh Express'],
                            ['name' => 'Romaine Hearts', 'brand' => 'Andy Boy'],
                            ['name' => 'Baby Kale', 'brand' => 'Taylor Farms'],
                        ],
                    ],
                    'Root Vegetables' => [
                        'tags' => ['Refrigerated', 'Dinner'],
                        'products' => [
                            ['name' => 'Baby-Cut Carrots', 'brand' => 'Grimmway Farms'],
                            ['name' => 'Rainbow Carrots', 'brand' => 'Cal-Organic Farms'],
                            ['name' => 'Red Beets', 'brand' => 'Cal-Organic Farms'],
                            ['name' => 'Parsnips', 'brand' => 'Cal-Organic Farms'],
                            ['name' => 'Carrots', 'brand' => 'Bolthouse Farms'],
                        ],
                    ],
                    'Squash' => [
                        'tags' => ['Refrigerated', 'Cooking Required'],
                        'products' => [
                            ['name' => 'Butternut Squash', 'brand' => 'Mann\'s'],
                            ['name' => 'Spaghetti Squash', 'brand' => 'Cal-Organic Farms'],
                            ['name' => 'Acorn Squash', 'brand' => 'Cal-Organic Farms'],
                            ['name' => 'Butternut Squash Cubes', 'brand' => 'Green Giant Fresh'],
                            ['name' => 'Zucchini', 'brand' => 'Cal-Organic Farms'],
                        ],
                    ],
                    'Peppers' => [
                        'tags' => ['Refrigerated', 'No-Cook'],
                        'products' => [
                            ['name' => 'Sweet Mini Peppers', 'brand' => 'SUNSET'],
                            ['name' => 'Bell Peppers', 'brand' => 'NatureSweet'],
                            ['name' => 'Sweet Bell Peppers', 'brand' => 'Windset Farms'],
                            ['name' => 'Mini Sweet Peppers', 'brand' => 'Mucci Farms'],
                            ['name' => 'Sweet Pointed Peppers', 'brand' => 'Nature Fresh Farms'],
                        ],
                    ],
                    'Tomatoes' => [
                        'tags' => ['No-Cook', 'Lunch'],
                        'products' => [
                            ['name' => 'Campari Tomatoes', 'brand' => 'SUNSET'],
                            ['name' => 'Cherubs Tomatoes', 'brand' => 'NatureSweet'],
                            ['name' => 'Grape Tomatoes', 'brand' => 'Windset Farms'],
                            ['name' => 'Cocktail Tomatoes', 'brand' => 'Mucci Farms'],
                            ['name' => 'Cherry Tomatoes', 'brand' => 'Village Farms'],
                        ],
                    ],
                ],
                'Fresh Herbs' => [
                    'Basil' => [
                        'tags' => ['Refrigerated', 'No-Cook'],
                        'products' => [
                            ['name' => 'Fresh Basil', 'brand' => 'Gotham Greens'],
                            ['name' => 'Fresh Basil', 'brand' => 'Living Herbs'],
                            ['name' => 'Basil Leaves', 'brand' => 'Goodness Gardens'],
                            ['name' => 'Organic Basil', 'brand' => 'Shenandoah Growers'],
                            ['name' => 'Fresh Basil', 'brand' => 'That’s Tasty'],
                        ],
                    ],
                    'Cilantro' => [
                        'tags' => ['Refrigerated', 'No-Cook'],
                        'products' => [
                            ['name' => 'Fresh Cilantro', 'brand' => 'Gotham Greens'],
                            ['name' => 'Fresh Cilantro', 'brand' => 'Living Herbs'],
                            ['name' => 'Cilantro Leaves', 'brand' => 'Goodness Gardens'],
                            ['name' => 'Organic Cilantro', 'brand' => 'Shenandoah Growers'],
                            ['name' => 'Fresh Cilantro', 'brand' => 'That’s Tasty'],
                        ],
                    ],
                    'Parsley' => [
                        'tags' => ['Refrigerated', 'No-Cook'],
                        'products' => [
                            ['name' => 'Italian Parsley', 'brand' => 'Goodness Gardens'],
                            ['name' => 'Curly Parsley', 'brand' => 'Dole'],
                            ['name' => 'Fresh Parsley', 'brand' => 'Living Herbs'],
                            ['name' => 'Organic Parsley', 'brand' => 'Shenandoah Growers'],
                            ['name' => 'Flat-Leaf Parsley', 'brand' => 'That’s Tasty'],
                        ],
                    ],
                    'Mint' => [
                        'tags' => ['Refrigerated', 'No-Cook'],
                        'products' => [
                            ['name' => 'Fresh Mint', 'brand' => 'Gotham Greens'],
                            ['name' => 'Mint Leaves', 'brand' => 'Goodness Gardens'],
                            ['name' => 'Fresh Mint', 'brand' => 'Living Herbs'],
                            ['name' => 'Organic Mint', 'brand' => 'Shenandoah Growers'],
                            ['name' => 'Fresh Mint', 'brand' => 'That’s Tasty'],
                        ],
                    ],
                    'Rosemary' => [
                        'tags' => ['Refrigerated', 'Cooking Required'],
                        'products' => [
                            ['name' => 'Fresh Rosemary', 'brand' => 'Goodness Gardens'],
                            ['name' => 'Fresh Rosemary', 'brand' => 'Living Herbs'],
                            ['name' => 'Organic Rosemary', 'brand' => 'Shenandoah Growers'],
                            ['name' => 'Rosemary Sprigs', 'brand' => 'That’s Tasty'],
                            ['name' => 'Fresh Rosemary', 'brand' => 'Gourmet Garden'],
                        ],
                    ],
                ],
            ],
            'Dairy and Eggs' => [
                'Milk' => [
                    'Whole Milk' => [
                        'tags' => ['Refrigerated', 'Breakfast'],
                        'products' => [
                            ['name' => 'Whole Milk', 'brand' => 'Lactantia'],
                            ['name' => 'Whole Milk', 'brand' => 'Natrel'],
                            ['name' => 'Whole Milk', 'brand' => 'Neilson'],
                            ['name' => 'Whole Milk', 'brand' => 'Farmers'],
                            ['name' => 'Whole Milk', 'brand' => 'Scotsburn'],
                        ],
                    ],
                    'Reduced-Fat Milk' => [
                        'tags' => ['Refrigerated', 'Breakfast'],
                        'products' => [
                            ['name' => 'Reduced-Fat Milk', 'brand' => 'Lactantia'],
                            ['name' => 'Reduced-Fat Milk', 'brand' => 'Natrel'],
                            ['name' => 'Reduced-Fat Milk', 'brand' => 'Neilson'],
                            ['name' => 'Reduced-Fat Milk', 'brand' => 'Farmers'],
                            ['name' => 'Reduced-Fat Milk', 'brand' => 'Scotsburn'],
                        ],
                    ],
                    'Skim Milk' => [
                        'tags' => ['Refrigerated', 'Breakfast'],
                        'products' => [
                            ['name' => 'Skim Milk', 'brand' => 'Lactantia'],
                            ['name' => 'Skim Milk', 'brand' => 'Natrel'],
                            ['name' => 'Skim Milk', 'brand' => 'Neilson'],
                            ['name' => 'Skim Milk', 'brand' => 'Farmers'],
                            ['name' => 'Skim Milk', 'brand' => 'Scotsburn'],
                        ],
                    ],
                    'Lactose-Free Milk' => [
                        'tags' => ['Refrigerated', 'Lactose-Free'],
                        'products' => [
                            ['name' => 'Fine-Filtered Lactose-Free Milk', 'brand' => 'Natrel'],
                            ['name' => 'Lactose-Free Milk', 'brand' => 'Lactantia'],
                            ['name' => 'Lactose-Free Milk', 'brand' => 'Lactaid'],
                            ['name' => 'Lactose-Free Milk', 'brand' => 'Fairlife'],
                            ['name' => 'Lactose-Free Milk', 'brand' => 'Organic Valley'],
                        ],
                    ],
                    'Flavoured Milk' => [
                        'tags' => ['Refrigerated', 'Snack'],
                        'products' => [
                            ['name' => 'Chocolate Milk', 'brand' => 'Natrel'],
                            ['name' => 'Chocolate Milk', 'brand' => 'Neilson'],
                            ['name' => 'Chocolate Milk', 'brand' => 'Farmers'],
                            ['name' => 'Strawberry Milk', 'brand' => 'Neilson'],
                            ['name' => 'Chocolate Milk', 'brand' => 'Fairlife'],
                        ],
                    ],
                ],
                'Cheese' => [
                    'Cheddar' => [
                        'tags' => ['Refrigerated', 'No-Cook'],
                        'products' => [
                            ['name' => 'Old Cheddar', 'brand' => 'Armstrong'],
                            ['name' => 'Marble Cheddar', 'brand' => 'Black Diamond'],
                            ['name' => 'Medium Cheddar', 'brand' => 'Cracker Barrel'],
                            ['name' => 'Mild Cheddar', 'brand' => 'Balderson'],
                            ['name' => 'Extra Old Cheddar', 'brand' => 'Bothwell'],
                        ],
                    ],
                    'Mozzarella' => [
                        'tags' => ['Refrigerated', 'No-Cook'],
                        'products' => [
                            ['name' => 'Mozzarella', 'brand' => 'Saputo'],
                            ['name' => 'Pizza Mozzarella', 'brand' => 'Black Diamond'],
                            ['name' => 'Mozzarella', 'brand' => 'Armstrong'],
                            ['name' => 'Mozzarella', 'brand' => 'Tre Stelle'],
                            ['name' => 'Mozzarella', 'brand' => 'Galbani'],
                        ],
                    ],
                    'Cream Cheese' => [
                        'tags' => ['Refrigerated', 'Breakfast'],
                        'products' => [
                            ['name' => 'Original Cream Cheese', 'brand' => 'Philadelphia'],
                            ['name' => 'Herb and Garlic Cream Cheese', 'brand' => 'Philadelphia'],
                            ['name' => 'Original Cream Cheese', 'brand' => 'Lactantia'],
                            ['name' => 'Plain Cream Cheese', 'brand' => 'Arla'],
                            ['name' => 'Light Cream Cheese', 'brand' => 'Western'],
                        ],
                    ],
                    'Cottage Cheese' => [
                        'tags' => ['Refrigerated', 'No-Cook'],
                        'products' => [
                            ['name' => 'Cottage Cheese', 'brand' => 'Dairyland'],
                            ['name' => 'Cottage Cheese', 'brand' => 'Nordica'],
                            ['name' => 'Cottage Cheese', 'brand' => 'Sealtest'],
                            ['name' => 'Cottage Cheese', 'brand' => 'Western'],
                            ['name' => 'Cottage Cheese', 'brand' => 'Gay Lea'],
                        ],
                    ],
                    'Feta' => [
                        'tags' => ['Refrigerated', 'No-Cook'],
                        'products' => [
                            ['name' => 'Traditional Feta', 'brand' => 'Tre Stelle'],
                            ['name' => 'Greek Feta', 'brand' => 'Krinos'],
                            ['name' => 'Feta', 'brand' => 'Saputo'],
                            ['name' => 'Feta', 'brand' => 'Dodoni'],
                            ['name' => 'Crumbled Feta', 'brand' => 'President'],
                        ],
                    ],
                ],
                'Eggs' => [
                    'White Chicken Eggs' => [
                        'tags' => ['Refrigerated', 'Cooking Required', 'Breakfast'],
                        'products' => [
                            ['name' => 'White Eggs', 'brand' => 'Burnbrae Farms'],
                            ['name' => 'White Eggs', 'brand' => 'Gray Ridge'],
                            ['name' => 'White Eggs', 'brand' => 'Conestoga Farms'],
                            ['name' => 'White Eggs', 'brand' => 'GoldEgg'],
                            ['name' => 'White Eggs', 'brand' => 'Eggland\'s Best'],
                        ],
                    ],
                    'Brown Chicken Eggs' => [
                        'tags' => ['Refrigerated', 'Cooking Required', 'Breakfast'],
                        'products' => [
                            ['name' => 'Brown Eggs', 'brand' => 'Burnbrae Farms'],
                            ['name' => 'Brown Eggs', 'brand' => 'Gray Ridge'],
                            ['name' => 'Brown Eggs', 'brand' => 'Conestoga Farms'],
                            ['name' => 'Brown Eggs', 'brand' => 'GoldEgg'],
                            ['name' => 'Brown Eggs', 'brand' => 'Nellie\'s'],
                        ],
                    ],
                    'Free-Range Eggs' => [
                        'tags' => ['Refrigerated', 'Cooking Required', 'Breakfast'],
                        'products' => [
                            ['name' => 'Free-Range Eggs', 'brand' => 'Nellie\'s'],
                            ['name' => 'Free-Range Eggs', 'brand' => 'Happy Egg'],
                            ['name' => 'Free-Range Eggs', 'brand' => 'Pete & Gerry\'s'],
                            ['name' => 'Free-Range Eggs', 'brand' => 'Clarence Court'],
                            ['name' => 'Free-Range Eggs', 'brand' => 'St Ewe'],
                        ],
                    ],
                    'Organic Eggs' => [
                        'tags' => ['Refrigerated', 'Cooking Required', 'Breakfast'],
                        'products' => [
                            ['name' => 'Organic Eggs', 'brand' => 'Naturegg'],
                            ['name' => 'Organic Eggs', 'brand' => 'Pete & Gerry\'s'],
                            ['name' => 'Organic Eggs', 'brand' => 'Organic Valley'],
                            ['name' => 'Organic Eggs', 'brand' => 'Vital Farms'],
                            ['name' => 'Organic Eggs', 'brand' => 'Conestoga Farms'],
                        ],
                    ],
                    'Duck Eggs' => [
                        'tags' => ['Refrigerated', 'Cooking Required', 'Baking'],
                        'products' => [
                            ['name' => 'Braddock White Duck Eggs', 'brand' => 'Clarence Court'],
                            ['name' => 'Fresh Duck Eggs', 'brand' => 'Olinday Farms'],
                            ['name' => 'Duck Eggs', 'brand' => 'Goffle Road Poultry Farm'],
                            ['name' => 'Fresh Duck Eggs', 'brand' => 'Guido Family Farm'],
                            ['name' => 'Duck Eggs', 'brand' => 'Evergreen Valley Farms'],
                        ],
                    ],
                ],
            ],
            'Meat and Seafood' => [
                'Poultry' => [
                    'Chicken' => [
                        'tags' => ['Refrigerated', 'Cooking Required', 'Dinner'],
                        'products' => [
                            ['name' => 'Chicken Breasts', 'brand' => 'Maple Leaf'],
                            ['name' => 'Chicken Thighs', 'brand' => 'Maple Lodge Farms'],
                            ['name' => 'Whole Chicken', 'brand' => 'Exceldor'],
                            ['name' => 'Chicken Drumsticks', 'brand' => 'Zabiha Halal', 'tags' => ['Halal']],
                            ['name' => 'Chicken Wings', 'brand' => 'Lilydale'],
                        ],
                    ],
                    'Turkey' => [
                        'tags' => ['Refrigerated', 'Cooking Required', 'Dinner'],
                        'products' => [
                            ['name' => 'Whole Turkey', 'brand' => 'Butterball'],
                            ['name' => 'Turkey Breast', 'brand' => 'Lilydale'],
                            ['name' => 'Turkey Drumsticks', 'brand' => 'Hayter\'s Farm'],
                            ['name' => 'Turkey Breast Roast', 'brand' => 'Butterball'],
                            ['name' => 'Whole Turkey', 'brand' => 'Norbest'],
                        ],
                    ],
                    'Duck' => [
                        'tags' => ['Refrigerated', 'Cooking Required', 'Dinner'],
                        'products' => [
                            ['name' => 'Whole Duck', 'brand' => 'King Cole'],
                            ['name' => 'Duck Breast', 'brand' => 'Brome Lake'],
                            ['name' => 'Duck Legs', 'brand' => 'Maple Leaf Farms'],
                            ['name' => 'Whole Duck', 'brand' => 'Culver Duck'],
                            ['name' => 'Duck Breast', 'brand' => 'D\'Artagnan'],
                        ],
                    ],
                    'Ground Poultry' => [
                        'tags' => ['Refrigerated', 'Cooking Required', 'Dinner'],
                        'products' => [
                            ['name' => 'Ground Chicken', 'brand' => 'Maple Leaf'],
                            ['name' => 'Ground Turkey', 'brand' => 'Butterball'],
                            ['name' => 'Ground Chicken', 'brand' => 'Zabiha Halal', 'tags' => ['Halal']],
                            ['name' => 'Ground Turkey', 'brand' => 'Lilydale'],
                            ['name' => 'Ground Chicken', 'brand' => 'Maple Lodge Farms'],
                        ],
                    ],
                    'Poultry Sausages' => [
                        'tags' => ['Refrigerated', 'Dinner'],
                        'products' => [
                            ['name' => 'Chicken Sausages', 'brand' => 'Zabiha Halal', 'tags' => ['Halal']],
                            ['name' => 'Turkey Sausages', 'brand' => 'Butterball'],
                            ['name' => 'Chicken and Apple Sausages', 'brand' => 'Aidells'],
                            ['name' => 'Chicken Sausages', 'brand' => 'Maple Lodge Farms'],
                            ['name' => 'Turkey Breakfast Sausages', 'brand' => 'Lilydale'],
                        ],
                    ],
                ],
                'Red Meat' => [
                    'Beef' => [
                        'tags' => ['Refrigerated', 'Cooking Required', 'Dinner'],
                        'products' => [
                            ['name' => 'Beef Sirloin Steak', 'brand' => 'Certified Angus Beef'],
                            ['name' => 'Beef Chuck Roast', 'brand' => 'Sterling Silver'],
                            ['name' => 'Beef Stewing Cubes', 'brand' => 'President\'s Choice'],
                            ['name' => 'Beef Ribeye Steak', 'brand' => 'Creekstone Farms'],
                            ['name' => 'Beef Striploin Steak', 'brand' => 'Meyer Natural Angus'],
                        ],
                    ],
                    'Pork' => [
                        'tags' => ['Refrigerated', 'Cooking Required', 'Dinner'],
                        'products' => [
                            ['name' => 'Pork Loin Chops', 'brand' => 'Maple Leaf'],
                            ['name' => 'Pork Tenderloin', 'brand' => 'duBreton'],
                            ['name' => 'Pork Shoulder Roast', 'brand' => 'Smithfield'],
                            ['name' => 'Pork Rib Chops', 'brand' => 'Olymel'],
                            ['name' => 'Pork Back Ribs', 'brand' => 'Prairie Fresh'],
                        ],
                    ],
                    'Lamb' => [
                        'tags' => ['Refrigerated', 'Cooking Required', 'Dinner'],
                        'products' => [
                            ['name' => 'Lamb Leg', 'brand' => 'New Zealand Spring Lamb'],
                            ['name' => 'Lamb Loin Chops', 'brand' => 'Thomas Farms'],
                            ['name' => 'Lamb Shoulder', 'brand' => 'Opal Valley'],
                            ['name' => 'Lamb Rack', 'brand' => 'Silver Fern Farms'],
                            ['name' => 'Lamb Shanks', 'brand' => 'Mountain States Rosen'],
                        ],
                    ],
                    'Ground Red Meat' => [
                        'tags' => ['Refrigerated', 'Cooking Required', 'Dinner'],
                        'products' => [
                            ['name' => 'Lean Ground Beef', 'brand' => 'Certified Angus Beef'],
                            ['name' => 'Ground Pork', 'brand' => 'duBreton'],
                            ['name' => 'Ground Lamb', 'brand' => 'New Zealand Spring Lamb'],
                            ['name' => 'Ground Beef', 'brand' => 'Meyer Natural Angus'],
                            ['name' => 'Ground Bison', 'brand' => 'Great Range'],
                        ],
                    ],
                    'Red Meat Sausages' => [
                        'tags' => ['Refrigerated', 'Dinner'],
                        'products' => [
                            ['name' => 'Italian Pork Sausages', 'brand' => 'Marcangelo'],
                            ['name' => 'Bratwurst', 'brand' => 'Johnsonville'],
                            ['name' => 'Pork Breakfast Sausages', 'brand' => 'Maple Leaf'],
                            ['name' => 'Beef Sausages', 'brand' => 'Schneiders'],
                            ['name' => 'Italian Sausages', 'brand' => 'Olymel'],
                        ],
                    ],
                ],
                'Fish and Seafood' => [
                    'Salmon' => [
                        'tags' => ['Frozen', 'Cooking Required', 'Dinner'],
                        'products' => [
                            ['name' => 'Atlantic Salmon Fillets', 'brand' => 'High Liner'],
                            ['name' => 'Sockeye Salmon Fillets', 'brand' => 'SeaChange'],
                            ['name' => 'Wild Pink Salmon Portions', 'brand' => 'Ocean Jewel'],
                            ['name' => 'Atlantic Salmon Portions', 'brand' => 'Aqua Star'],
                            ['name' => 'Coho Salmon Fillets', 'brand' => 'Trident Seafoods'],
                        ],
                    ],
                    'Tuna' => [
                        'tags' => ['Frozen', 'Cooking Required', 'Dinner'],
                        'products' => [
                            ['name' => 'Yellowfin Tuna Steaks', 'brand' => 'Ocean Jewel'],
                            ['name' => 'Ahi Tuna Steaks', 'brand' => 'Sea Best'],
                            ['name' => 'Yellowfin Tuna Portions', 'brand' => 'Aqua Star'],
                            ['name' => 'Tuna Steaks', 'brand' => 'Sea Delight'],
                            ['name' => 'Yellowfin Tuna Steaks', 'brand' => 'President\'s Choice'],
                        ],
                    ],
                    'White Fish' => [
                        'tags' => ['Frozen', 'Cooking Required', 'Dinner'],
                        'products' => [
                            ['name' => 'Haddock Fillets', 'brand' => 'High Liner'],
                            ['name' => 'Cod Fillets', 'brand' => 'Ocean Jewel'],
                            ['name' => 'Pollock Fillets', 'brand' => 'Sea Best'],
                            ['name' => 'Sole Fillets', 'brand' => 'Aqua Star'],
                            ['name' => 'Tilapia Fillets', 'brand' => 'President\'s Choice'],
                        ],
                    ],
                    'Shrimp' => [
                        'tags' => ['Frozen', 'Cooking Required', 'Dinner'],
                        'products' => [
                            ['name' => 'Raw White Shrimp', 'brand' => 'Aqua Star'],
                            ['name' => 'Raw Shrimp', 'brand' => 'Ocean Jewel'],
                            ['name' => 'Black Tiger Shrimp', 'brand' => 'Sea Best'],
                            ['name' => 'Raw Pacific Shrimp', 'brand' => 'President\'s Choice'],
                            ['name' => 'Raw Shrimp', 'brand' => 'Kirkland Signature'],
                        ],
                    ],
                    'Mussels' => [
                        'tags' => ['Frozen', 'Cooking Required', 'Dinner'],
                        'products' => [
                            ['name' => 'Blue Mussels', 'brand' => 'PEI Mussel King'],
                            ['name' => 'Mussels', 'brand' => 'Aqua Star'],
                            ['name' => 'Blue Mussels', 'brand' => 'Ocean Jewel'],
                            ['name' => 'Green-Lipped Mussels', 'brand' => 'Talley\'s'],
                            ['name' => 'Mussels', 'brand' => 'Sea Best'],
                        ],
                    ],
                ],
            ],
            'Bakery' => [
                'Bread' => [
                    'White Bread' => [
                        'tags' => ['Ready to Eat', 'Lunch'],
                        'products' => [
                            ['name' => 'White Bread', 'brand' => 'Dempster\'s'],
                            ['name' => 'Wonder White Bread', 'brand' => 'Wonder'],
                            ['name' => 'White Bread', 'brand' => 'Ben\'s'],
                            ['name' => 'White Bread', 'brand' => 'POM'],
                            ['name' => 'White Sandwich Bread', 'brand' => 'Sara Lee'],
                        ],
                    ],
                    'Whole Wheat Bread' => [
                        'tags' => ['Ready to Eat', 'Lunch'],
                        'products' => [
                            ['name' => 'Whole Wheat Bread', 'brand' => 'Dempster\'s'],
                            ['name' => 'Whole Wheat Bread', 'brand' => 'Wonder'],
                            ['name' => 'Whole Wheat Bread', 'brand' => 'Country Harvest'],
                            ['name' => 'Whole Wheat Bread', 'brand' => 'POM'],
                            ['name' => 'Whole Wheat Bread', 'brand' => 'Nature\'s Own'],
                        ],
                    ],
                    'Multigrain Bread' => [
                        'tags' => ['Ready to Eat', 'Lunch'],
                        'products' => [
                            ['name' => 'Twelve Grains Bread', 'brand' => 'Dempster\'s'],
                            ['name' => 'Ancient Grains Bread', 'brand' => 'Country Harvest'],
                            ['name' => 'Multigrain Bread', 'brand' => 'POM'],
                            ['name' => 'Good Seed Bread', 'brand' => 'Dave\'s Killer Bread'],
                            ['name' => 'Multigrain Bread', 'brand' => 'Silver Hills'],
                        ],
                    ],
                    'Rye Bread' => [
                        'tags' => ['Ready to Eat', 'Lunch'],
                        'products' => [
                            ['name' => 'Light Rye Bread', 'brand' => 'Dimpflmeier'],
                            ['name' => 'Dark Rye Bread', 'brand' => 'Dimpflmeier'],
                            ['name' => 'Rye Bread', 'brand' => 'Ben\'s'],
                            ['name' => 'Deli Rye Bread', 'brand' => 'Dempster\'s'],
                            ['name' => 'Rye Bread', 'brand' => 'Mestemacher'],
                        ],
                    ],
                    'Gluten-Free Bread' => [
                        'tags' => ['Ready to Eat', 'Lunch'],
                        'products' => [
                            ['name' => 'White Bread', 'brand' => 'Udi\'s'],
                            ['name' => 'Mountain White Bread', 'brand' => 'Canyon Bakehouse'],
                            ['name' => 'Whole Grain Bread', 'brand' => 'Little Northern Bakehouse'],
                            ['name' => 'Classic White Bread', 'brand' => 'Schar'],
                            ['name' => 'Cinnamon Raisin Bread', 'brand' => 'Promise Gluten Free'],
                        ],
                    ],
                ],
                'Rolls and Buns' => [
                    'Dinner Rolls' => [
                        'tags' => ['Ready to Eat', 'Dinner'],
                        'products' => [
                            ['name' => 'Original Hawaiian Sweet Rolls', 'brand' => 'King\'s Hawaiian'],
                            ['name' => 'Dinner Rolls', 'brand' => 'Dempster\'s'],
                            ['name' => 'White Dinner Rolls', 'brand' => 'POM'],
                            ['name' => 'Soft Dinner Rolls', 'brand' => 'Wonder'],
                            ['name' => 'Butter Rolls', 'brand' => 'Sara Lee'],
                        ],
                    ],
                    'Hamburger Buns' => [
                        'tags' => ['Ready to Eat', 'Lunch'],
                        'products' => [
                            ['name' => 'Hamburger Buns', 'brand' => 'Dempster\'s'],
                            ['name' => 'Hamburger Buns', 'brand' => 'Wonder'],
                            ['name' => 'Brioche Hamburger Buns', 'brand' => 'St Pierre'],
                            ['name' => 'Hamburger Buns', 'brand' => 'POM'],
                            ['name' => 'Sesame Hamburger Buns', 'brand' => 'Ball Park'],
                        ],
                    ],
                    'Hot Dog Buns' => [
                        'tags' => ['Ready to Eat', 'Lunch'],
                        'products' => [
                            ['name' => 'Hot Dog Buns', 'brand' => 'Dempster\'s'],
                            ['name' => 'Hot Dog Buns', 'brand' => 'Wonder'],
                            ['name' => 'Hot Dog Buns', 'brand' => 'POM'],
                            ['name' => 'Brioche Hot Dog Rolls', 'brand' => 'St Pierre'],
                            ['name' => 'Hot Dog Buns', 'brand' => 'Ball Park'],
                        ],
                    ],
                    'Sandwich Rolls' => [
                        'tags' => ['Ready to Eat', 'Lunch'],
                        'products' => [
                            ['name' => 'Submarine Rolls', 'brand' => 'Dempster\'s'],
                            ['name' => 'Kaiser Rolls', 'brand' => 'POM'],
                            ['name' => 'Hoagie Rolls', 'brand' => 'Amoroso\'s'],
                            ['name' => 'Sandwich Rolls', 'brand' => 'Wonder'],
                            ['name' => 'Ciabatta Rolls', 'brand' => 'ACE Bakery'],
                        ],
                    ],
                    'Whole Wheat Buns' => [
                        'tags' => ['Ready to Eat', 'Lunch'],
                        'products' => [
                            ['name' => 'Whole Wheat Hamburger Buns', 'brand' => 'Dempster\'s'],
                            ['name' => 'Whole Wheat Hamburger Buns', 'brand' => 'Wonder'],
                            ['name' => 'Whole Wheat Buns', 'brand' => 'POM'],
                            ['name' => 'Whole Wheat Hamburger Buns', 'brand' => 'Country Harvest'],
                            ['name' => 'Whole Wheat Sandwich Buns', 'brand' => 'Nature\'s Own'],
                        ],
                    ],
                ],
                'Pastries' => [
                    'Croissants' => [
                        'tags' => ['Ready to Eat', 'Breakfast'],
                        'products' => [
                            ['name' => 'Butter Croissants', 'brand' => 'Kirkland Signature'],
                            ['name' => 'Butter Croissants', 'brand' => 'Au Pain Dore'],
                            ['name' => 'Chocolate Croissants', 'brand' => 'La Boulangerie'],
                            ['name' => 'Butter Croissants', 'brand' => 'President\'s Choice'],
                            ['name' => 'Butter Croissants', 'brand' => 'Marketside'],
                        ],
                    ],
                    'Muffins' => [
                        'tags' => ['Ready to Eat', 'Snack'],
                        'products' => [
                            ['name' => 'Blueberry Muffins', 'brand' => 'Kirkland Signature'],
                            ['name' => 'Chocolate Chip Muffins', 'brand' => 'Give & Go'],
                            ['name' => 'Banana Chocolate Muffins', 'brand' => 'Garden Lites'],
                            ['name' => 'Blueberry Muffins', 'brand' => 'Otis Spunkmeyer'],
                            ['name' => 'Bran Muffins', 'brand' => 'President\'s Choice'],
                        ],
                    ],
                    'Doughnuts' => [
                        'tags' => ['Ready to Eat', 'Snack'],
                        'products' => [
                            ['name' => 'Original Glazed Doughnuts', 'brand' => 'Krispy Kreme'],
                            ['name' => 'Chocolate Mini Donuts', 'brand' => 'Hostess'],
                            ['name' => 'Powdered Mini Donuts', 'brand' => 'Little Debbie'],
                            ['name' => 'Old Fashioned Doughnuts', 'brand' => 'Entenmann\'s'],
                            ['name' => 'Glazed Doughnuts', 'brand' => 'Marketside'],
                        ],
                    ],
                    'Danish Pastries' => [
                        'tags' => ['Ready to Eat', 'Breakfast'],
                        'products' => [
                            ['name' => 'Cheese Danish', 'brand' => 'Entenmann\'s'],
                            ['name' => 'Raspberry Danish', 'brand' => 'Svenhard\'s'],
                            ['name' => 'Apple Danish', 'brand' => 'Kirkland Signature'],
                            ['name' => 'Cinnamon Danish', 'brand' => 'La Boulangerie'],
                            ['name' => 'Cream Cheese Danish', 'brand' => 'Cloverhill'],
                        ],
                    ],
                    'Fruit Turnovers' => [
                        'tags' => ['Ready to Eat', 'Snack'],
                        'products' => [
                            ['name' => 'Apple Turnovers', 'brand' => 'President\'s Choice'],
                            ['name' => 'Cherry Turnovers', 'brand' => 'Hostess'],
                            ['name' => 'Apple Turnovers', 'brand' => 'Entenmann\'s'],
                            ['name' => 'Blueberry Turnovers', 'brand' => 'Marketside'],
                            ['name' => 'Apple Turnovers', 'brand' => 'Kirkland Signature'],
                        ],
                    ],
                ],
            ],
            'Frozen Foods' => [
                'Frozen Vegetables' => [
                    'Peas' => [
                        'tags' => ['Frozen', 'Cooking Required'],
                        'products' => [
                            ['name' => 'Sweet Peas', 'brand' => 'Green Giant'],
                            ['name' => 'Sweet Peas', 'brand' => 'Birds Eye'],
                            ['name' => 'Green Peas', 'brand' => 'Arctic Gardens'],
                            ['name' => 'Sweet Peas', 'brand' => 'Europe\'s Best'],
                            ['name' => 'Green Peas', 'brand' => 'Compliments'],
                        ],
                    ],
                    'Corn' => [
                        'tags' => ['Frozen', 'Cooking Required'],
                        'products' => [
                            ['name' => 'Sweet Corn', 'brand' => 'Green Giant'],
                            ['name' => 'Sweet Corn', 'brand' => 'Birds Eye'],
                            ['name' => 'Whole Kernel Corn', 'brand' => 'Arctic Gardens'],
                            ['name' => 'Sweet Corn', 'brand' => 'Europe\'s Best'],
                            ['name' => 'Sweet Corn', 'brand' => 'Compliments'],
                        ],
                    ],
                    'Broccoli' => [
                        'tags' => ['Frozen', 'Cooking Required'],
                        'products' => [
                            ['name' => 'Broccoli Florets', 'brand' => 'Green Giant'],
                            ['name' => 'Broccoli Florets', 'brand' => 'Birds Eye'],
                            ['name' => 'Broccoli', 'brand' => 'Arctic Gardens'],
                            ['name' => 'Broccoli Florets', 'brand' => 'Europe\'s Best'],
                            ['name' => 'Broccoli Florets', 'brand' => 'Compliments'],
                        ],
                    ],
                    'Spinach' => [
                        'tags' => ['Frozen', 'Cooking Required'],
                        'products' => [
                            ['name' => 'Chopped Spinach', 'brand' => 'Green Giant'],
                            ['name' => 'Chopped Spinach', 'brand' => 'Birds Eye'],
                            ['name' => 'Spinach', 'brand' => 'Arctic Gardens'],
                            ['name' => 'Leaf Spinach', 'brand' => 'Europe\'s Best'],
                            ['name' => 'Chopped Spinach', 'brand' => 'Compliments'],
                        ],
                    ],
                    'Mixed Vegetables' => [
                        'tags' => ['Frozen', 'Cooking Required'],
                        'products' => [
                            ['name' => 'Mixed Vegetables', 'brand' => 'Green Giant'],
                            ['name' => 'Mixed Vegetables', 'brand' => 'Birds Eye'],
                            ['name' => 'Mixed Vegetables', 'brand' => 'Arctic Gardens'],
                            ['name' => 'Mixed Vegetables', 'brand' => 'Europe\'s Best'],
                            ['name' => 'Mixed Vegetables', 'brand' => 'Compliments'],
                        ],
                    ],
                ],
                'Frozen Fruit' => [
                    'Strawberries' => [
                        'tags' => ['Frozen', 'Snack'],
                        'products' => [
                            ['name' => 'Frozen Strawberries', 'brand' => 'Europe\'s Best'],
                            ['name' => 'Frozen Strawberries', 'brand' => 'Dole'],
                            ['name' => 'Frozen Strawberries', 'brand' => 'Kirkland Signature'],
                            ['name' => 'Frozen Strawberries', 'brand' => 'Wyman\'s'],
                            ['name' => 'Frozen Strawberries', 'brand' => 'Great Value'],
                        ],
                    ],
                    'Blueberries' => [
                        'tags' => ['Frozen', 'Snack'],
                        'products' => [
                            ['name' => 'Wild Blueberries', 'brand' => 'Wyman\'s'],
                            ['name' => 'Frozen Blueberries', 'brand' => 'Europe\'s Best'],
                            ['name' => 'Frozen Blueberries', 'brand' => 'Dole'],
                            ['name' => 'Organic Blueberries', 'brand' => 'Kirkland Signature'],
                            ['name' => 'Frozen Blueberries', 'brand' => 'Great Value'],
                        ],
                    ],
                    'Mango' => [
                        'tags' => ['Frozen', 'Snack'],
                        'products' => [
                            ['name' => 'Mango Chunks', 'brand' => 'Europe\'s Best'],
                            ['name' => 'Mango Chunks', 'brand' => 'Dole'],
                            ['name' => 'Organic Mango Chunks', 'brand' => 'Kirkland Signature'],
                            ['name' => 'Mango Chunks', 'brand' => 'Wyman\'s'],
                            ['name' => 'Mango Chunks', 'brand' => 'Great Value'],
                        ],
                    ],
                    'Peaches' => [
                        'tags' => ['Frozen', 'Snack'],
                        'products' => [
                            ['name' => 'Sliced Peaches', 'brand' => 'Europe\'s Best'],
                            ['name' => 'Sliced Peaches', 'brand' => 'Dole'],
                            ['name' => 'Sliced Peaches', 'brand' => 'Wyman\'s'],
                            ['name' => 'Sliced Peaches', 'brand' => 'Great Value'],
                            ['name' => 'Sliced Peaches', 'brand' => 'Compliments'],
                        ],
                    ],
                    'Mixed Fruit' => [
                        'tags' => ['Frozen', 'Snack'],
                        'products' => [
                            ['name' => 'Mixed Fruit', 'brand' => 'Dole'],
                            ['name' => 'Mixed Berries', 'brand' => 'Europe\'s Best'],
                            ['name' => 'Triple Berry Blend', 'brand' => 'Kirkland Signature'],
                            ['name' => 'Mixed Berry Blend', 'brand' => 'Wyman\'s'],
                            ['name' => 'Mixed Fruit', 'brand' => 'Great Value'],
                        ],
                    ],
                ],
                'Frozen Meals' => [
                    'Pizza' => [
                        'tags' => ['Frozen', 'Cooking Required', 'Dinner'],
                        'products' => [
                            ['name' => 'Giuseppe Rising Crust Pepperoni Pizza', 'brand' => 'Dr. Oetker'],
                            ['name' => 'Ristorante Margherita Pizza', 'brand' => 'Dr. Oetker'],
                            ['name' => 'Four Cheese Pizza', 'brand' => 'President\'s Choice'],
                            ['name' => 'Thin Crust Pepperoni Pizza', 'brand' => 'Red Baron'],
                            ['name' => 'Cheese Pizza', 'brand' => 'DiGiorno'],
                        ],
                    ],
                    'Lasagna' => [
                        'tags' => ['Frozen', 'Ready to Heat', 'Dinner'],
                        'products' => [
                            ['name' => 'Meat Lasagna', 'brand' => 'Stouffer\'s'],
                            ['name' => 'Lasagna with Meat Sauce', 'brand' => 'President\'s Choice'],
                            ['name' => 'Vegetable Lasagna', 'brand' => 'Amy\'s'],
                            ['name' => 'Meat Lasagna', 'brand' => 'Michelina\'s'],
                            ['name' => 'Italian Sausage Lasagna', 'brand' => 'Rao\'s'],
                        ],
                    ],
                    'Curries' => [
                        'tags' => ['Frozen', 'Ready to Heat', 'Dinner'],
                        'products' => [
                            ['name' => 'Chicken Tikka Masala', 'brand' => 'Deep Indian Kitchen'],
                            ['name' => 'Butter Chicken', 'brand' => 'Saffron Road'],
                            ['name' => 'Palak Paneer', 'brand' => 'Amy\'s'],
                            ['name' => 'Chicken Curry', 'brand' => 'President\'s Choice'],
                            ['name' => 'Channa Masala', 'brand' => 'Deep Indian Kitchen'],
                        ],
                    ],
                    'Stir-Fry Meals' => [
                        'tags' => ['Frozen', 'Cooking Required', 'Dinner'],
                        'products' => [
                            ['name' => 'Chicken Stir-Fry', 'brand' => 'Healthy Choice'],
                            ['name' => 'Garlic Chicken', 'brand' => 'Birds Eye Voila'],
                            ['name' => 'Chicken Teriyaki', 'brand' => 'Tai Pei'],
                            ['name' => 'Beef and Broccoli', 'brand' => 'P.F. Chang\'s'],
                            ['name' => 'Sweet and Sour Chicken', 'brand' => 'InnovAsian'],
                        ],
                    ],
                    'Vegetarian Meals' => [
                        'tags' => ['Frozen', 'Ready to Heat', 'Dinner'],
                        'products' => [
                            ['name' => 'Vegetable Korma', 'brand' => 'Amy\'s'],
                            ['name' => 'Black Bean Enchilada', 'brand' => 'Amy\'s'],
                            ['name' => 'Vegetable Fried Rice', 'brand' => 'Tai Pei'],
                            ['name' => 'Vegetable Biryani', 'brand' => 'Deep Indian Kitchen'],
                            ['name' => 'Vegetable Pad Thai', 'brand' => 'Saffron Road'],
                        ],
                    ],
                ],
            ],
            'Pantry Staples' => [
                'Rice and Grains' => [
                    'White Rice' => [
                        'tags' => ['Shelf-Stable', 'Cooking Required'],
                        'products' => [
                            ['name' => 'Basmati Rice', 'brand' => 'Tilda'],
                            ['name' => 'Jasmine Rice', 'brand' => 'Royal'],
                            ['name' => 'Long Grain White Rice', 'brand' => 'Dainty'],
                            ['name' => 'Original Converted Rice', 'brand' => 'Ben\'s Original'],
                            ['name' => 'Jasmine Rice', 'brand' => 'Lundberg'],
                        ],
                    ],
                    'Brown Rice' => [
                        'tags' => ['Shelf-Stable', 'Cooking Required'],
                        'products' => [
                            ['name' => 'Whole Grain Brown Rice', 'brand' => 'Dainty'],
                            ['name' => 'Brown Basmati Rice', 'brand' => 'Tilda'],
                            ['name' => 'Whole Grain Brown Rice', 'brand' => 'Ben\'s Original'],
                            ['name' => 'Short Grain Brown Rice', 'brand' => 'Lundberg'],
                            ['name' => 'Brown Rice', 'brand' => 'Minute Rice'],
                        ],
                    ],
                    'Oats' => [
                        'tags' => ['Shelf-Stable', 'Breakfast'],
                        'products' => [
                            ['name' => 'Old Fashioned Oats', 'brand' => 'Quaker'],
                            ['name' => 'Large Flake Oats', 'brand' => 'Robin Hood'],
                            ['name' => 'Steel Cut Oats', 'brand' => 'Bob\'s Red Mill'],
                            ['name' => 'Organic Rolled Oats', 'brand' => 'Nature\'s Path'],
                            ['name' => 'Quick Oats', 'brand' => 'Rogers'],
                        ],
                    ],
                    'Quinoa' => [
                        'tags' => ['Shelf-Stable', 'Cooking Required'],
                        'products' => [
                            ['name' => 'White Quinoa', 'brand' => 'GoGo Quinoa'],
                            ['name' => 'Organic Quinoa', 'brand' => 'Bob\'s Red Mill'],
                            ['name' => 'Organic Quinoa', 'brand' => 'Kirkland Signature'],
                            ['name' => 'Tricolour Quinoa', 'brand' => 'Lundberg'],
                            ['name' => 'Organic Quinoa', 'brand' => 'Inari'],
                        ],
                    ],
                    'Barley' => [
                        'tags' => ['Shelf-Stable', 'Cooking Required'],
                        'products' => [
                            ['name' => 'Pearl Barley', 'brand' => 'Bob\'s Red Mill'],
                            ['name' => 'Pearl Barley', 'brand' => 'Dainty'],
                            ['name' => 'Pearled Barley', 'brand' => 'Arrowhead Mills'],
                            ['name' => 'Pot Barley', 'brand' => 'Rogers'],
                            ['name' => 'Pearl Barley', 'brand' => 'Inari'],
                        ],
                    ],
                ],
                'Pasta' => [
                    'Spaghetti' => [
                        'tags' => ['Shelf-Stable', 'Cooking Required'],
                        'products' => [
                            ['name' => 'Spaghetti', 'brand' => 'Barilla'],
                            ['name' => 'Spaghetti', 'brand' => 'Catelli'],
                            ['name' => 'Spaghetti', 'brand' => 'Primo'],
                            ['name' => 'Spaghetti', 'brand' => 'De Cecco'],
                            ['name' => 'Spaghetti', 'brand' => 'Rummo'],
                        ],
                    ],
                    'Penne' => [
                        'tags' => ['Shelf-Stable', 'Cooking Required'],
                        'products' => [
                            ['name' => 'Penne Rigate', 'brand' => 'Barilla'],
                            ['name' => 'Penne', 'brand' => 'Catelli'],
                            ['name' => 'Penne Rigate', 'brand' => 'Primo'],
                            ['name' => 'Penne Rigate', 'brand' => 'De Cecco'],
                            ['name' => 'Penne Rigate', 'brand' => 'Rummo'],
                        ],
                    ],
                    'Macaroni' => [
                        'tags' => ['Shelf-Stable', 'Cooking Required'],
                        'products' => [
                            ['name' => 'Elbow Macaroni', 'brand' => 'Catelli'],
                            ['name' => 'Elbow Macaroni', 'brand' => 'Primo'],
                            ['name' => 'Elbows', 'brand' => 'Barilla'],
                            ['name' => 'Elbow Macaroni', 'brand' => 'Ronzoni'],
                            ['name' => 'Elbow Macaroni', 'brand' => 'Italpasta'],
                        ],
                    ],
                    'Lasagna Sheets' => [
                        'tags' => ['Shelf-Stable', 'Cooking Required'],
                        'products' => [
                            ['name' => 'Lasagne Sheets', 'brand' => 'Barilla'],
                            ['name' => 'Lasagne Noodles', 'brand' => 'Catelli'],
                            ['name' => 'Lasagna', 'brand' => 'Primo'],
                            ['name' => 'Lasagne', 'brand' => 'De Cecco'],
                            ['name' => 'Oven-Ready Lasagna', 'brand' => 'Italpasta'],
                        ],
                    ],
                    'Gluten-Free Pasta' => [
                        'tags' => ['Shelf-Stable', 'Cooking Required'],
                        'products' => [
                            ['name' => 'Gluten-Free Spaghetti', 'brand' => 'Barilla'],
                            ['name' => 'Gluten-Free Penne', 'brand' => 'Catelli'],
                            ['name' => 'Brown Rice Pasta Spirals', 'brand' => 'Tinkyada'],
                            ['name' => 'Quinoa Macaroni', 'brand' => 'GoGo Quinoa'],
                            ['name' => 'Gluten-Free Fusilli', 'brand' => 'Rummo'],
                        ],
                    ],
                ],
                'Canned Goods' => [
                    'Canned Beans' => [
                        'tags' => ['Shelf-Stable', 'Ready to Eat'],
                        'products' => [
                            ['name' => 'Black Beans', 'brand' => 'Unico'],
                            ['name' => 'Red Kidney Beans', 'brand' => 'Primo'],
                            ['name' => 'Chickpeas', 'brand' => 'Cedar'],
                            ['name' => 'Pinto Beans', 'brand' => 'Bush\'s'],
                            ['name' => 'Black Beans', 'brand' => 'Eden'],
                        ],
                    ],
                    'Canned Vegetables' => [
                        'tags' => ['Shelf-Stable', 'Ready to Eat'],
                        'products' => [
                            ['name' => 'Whole Kernel Corn', 'brand' => 'Green Giant'],
                            ['name' => 'Garden Peas', 'brand' => 'Del Monte'],
                            ['name' => 'Cut Green Beans', 'brand' => 'Green Giant'],
                            ['name' => 'Sliced Beets', 'brand' => 'Aylmer'],
                            ['name' => 'Mixed Vegetables', 'brand' => 'Del Monte'],
                        ],
                    ],
                    'Canned Fruit' => [
                        'tags' => ['Shelf-Stable', 'Ready to Eat'],
                        'products' => [
                            ['name' => 'Peach Slices', 'brand' => 'Del Monte'],
                            ['name' => 'Pineapple Chunks', 'brand' => 'Dole'],
                            ['name' => 'Mandarin Oranges', 'brand' => 'Dole'],
                            ['name' => 'Pear Halves', 'brand' => 'Del Monte'],
                            ['name' => 'Fruit Cocktail', 'brand' => 'Dole'],
                        ],
                    ],
                    'Canned Fish' => [
                        'tags' => ['Shelf-Stable', 'Ready to Eat'],
                        'products' => [
                            ['name' => 'Chunk Light Tuna', 'brand' => 'Clover Leaf'],
                            ['name' => 'Pink Salmon', 'brand' => 'Gold Seal'],
                            ['name' => 'Sardines in Olive Oil', 'brand' => 'Brunswick'],
                            ['name' => 'Sockeye Salmon', 'brand' => 'Wild Planet'],
                            ['name' => 'Solid White Tuna', 'brand' => 'Ocean\'s'],
                        ],
                    ],
                    'Canned Soup' => [
                        'tags' => ['Shelf-Stable', 'Ready to Heat'],
                        'products' => [
                            ['name' => 'Tomato Soup', 'brand' => 'Campbell\'s'],
                            ['name' => 'Chicken Noodle Soup', 'brand' => 'Campbell\'s'],
                            ['name' => 'Minestrone Soup', 'brand' => 'Primo'],
                            ['name' => 'Lentil Soup', 'brand' => 'Amy\'s'],
                            ['name' => 'Vegetable Soup', 'brand' => 'Progresso'],
                        ],
                    ],
                ],
            ],
            'Beverages' => [
                'Water' => [
                    'Spring Water' => [
                        'tags' => ['Shelf-Stable', 'No-Cook'],
                        'products' => [
                            ['name' => 'Natural Spring Water', 'brand' => 'Eska'],
                            ['name' => 'Spring Water', 'brand' => 'Ice River Springs'],
                            ['name' => 'Natural Spring Water', 'brand' => 'Evian'],
                            ['name' => 'Spring Water', 'brand' => 'Poland Spring'],
                            ['name' => 'Spring Water', 'brand' => 'Nestle Pure Life'],
                        ],
                    ],
                    'Purified Water' => [
                        'tags' => ['Shelf-Stable', 'No-Cook'],
                        'products' => [
                            ['name' => 'Purified Water', 'brand' => 'Dasani'],
                            ['name' => 'Purified Water', 'brand' => 'Aquafina'],
                            ['name' => 'Purified Water', 'brand' => 'Smartwater'],
                            ['name' => 'Purified Water', 'brand' => 'Kirkland Signature'],
                            ['name' => 'Purified Water', 'brand' => 'Great Value'],
                        ],
                    ],
                    'Mineral Water' => [
                        'tags' => ['Shelf-Stable', 'No-Cook'],
                        'products' => [
                            ['name' => 'Natural Mineral Water', 'brand' => 'Perrier'],
                            ['name' => 'Natural Mineral Water', 'brand' => 'San Pellegrino'],
                            ['name' => 'Natural Mineral Water', 'brand' => 'Gerolsteiner'],
                            ['name' => 'Natural Mineral Water', 'brand' => 'Vichy Catalan'],
                            ['name' => 'Natural Mineral Water', 'brand' => 'Topo Chico'],
                        ],
                    ],
                    'Sparkling Water' => [
                        'tags' => ['Shelf-Stable', 'No-Cook'],
                        'products' => [
                            ['name' => 'Original Sparkling Water', 'brand' => 'Bubly'],
                            ['name' => 'Pure Sparkling Water', 'brand' => 'LaCroix'],
                            ['name' => 'Sparkling Water', 'brand' => 'Schweppes'],
                            ['name' => 'Sparkling Water', 'brand' => 'Perrier'],
                            ['name' => 'Sparkling Water', 'brand' => 'San Pellegrino'],
                        ],
                    ],
                    'Flavoured Water' => [
                        'tags' => ['Shelf-Stable', 'No-Cook'],
                        'products' => [
                            ['name' => 'Lime Sparkling Water', 'brand' => 'Bubly'],
                            ['name' => 'Peach Pear Sparkling Water', 'brand' => 'LaCroix'],
                            ['name' => 'Lemon Flavoured Water', 'brand' => 'Hint'],
                            ['name' => 'Lemon Sparkling Water', 'brand' => 'Perrier'],
                            ['name' => 'Blackberry Sparkling Water', 'brand' => 'Waterloo'],
                        ],
                    ],
                ],
                'Juice' => [
                    'Apple Juice' => [
                        'tags' => ['No-Cook', 'Breakfast'],
                        'products' => [
                            ['name' => 'Apple Juice', 'brand' => 'Allen\'s'],
                            ['name' => 'Apple Juice', 'brand' => 'SunRype'],
                            ['name' => 'Apple Juice', 'brand' => 'Oasis'],
                            ['name' => 'Apple Juice', 'brand' => 'Minute Maid'],
                            ['name' => 'Apple Juice', 'brand' => 'Tropicana'],
                        ],
                    ],
                    'Orange Juice' => [
                        'tags' => ['No-Cook', 'Breakfast'],
                        'products' => [
                            ['name' => 'Pure Premium Orange Juice', 'brand' => 'Tropicana'],
                            ['name' => 'Orange Juice', 'brand' => 'Simply Orange'],
                            ['name' => 'Orange Juice', 'brand' => 'Minute Maid'],
                            ['name' => 'Orange Juice', 'brand' => 'Florida\'s Natural'],
                            ['name' => 'Orange Juice', 'brand' => 'Oasis'],
                        ],
                    ],
                    'Grape Juice' => [
                        'tags' => ['No-Cook', 'Breakfast'],
                        'products' => [
                            ['name' => 'Concord Grape Juice', 'brand' => 'Welch\'s'],
                            ['name' => 'Grape Juice', 'brand' => 'Oasis'],
                            ['name' => 'Grape Juice', 'brand' => 'Minute Maid'],
                            ['name' => 'Grape Juice', 'brand' => 'Lakewood'],
                            ['name' => 'Grape Juice', 'brand' => 'R.W. Knudsen'],
                        ],
                    ],
                    'Cranberry Juice' => [
                        'tags' => ['No-Cook', 'Breakfast'],
                        'products' => [
                            ['name' => 'Cranberry Cocktail', 'brand' => 'Ocean Spray'],
                            ['name' => 'Cranberry Juice', 'brand' => 'Oasis'],
                            ['name' => 'Pure Cranberry Juice', 'brand' => 'Lakewood'],
                            ['name' => 'Cranberry Juice Cocktail', 'brand' => 'Tropicana'],
                            ['name' => 'Cranberry Juice', 'brand' => 'R.W. Knudsen'],
                        ],
                    ],
                    'Vegetable Juice' => [
                        'tags' => ['No-Cook', 'Lunch'],
                        'products' => [
                            ['name' => 'Original Vegetable Cocktail', 'brand' => 'V8'],
                            ['name' => 'Tomato Juice', 'brand' => 'Campbell\'s'],
                            ['name' => 'Vegetable Cocktail', 'brand' => 'Garden Cocktail'],
                            ['name' => 'Vegetable Juice', 'brand' => 'Biotta'],
                            ['name' => 'Vegetable Juice', 'brand' => 'Lakewood'],
                        ],
                    ],
                ],
                'Tea and Coffee' => [
                    'Black Tea' => [
                        'tags' => ['Shelf-Stable', 'Keep Dry'],
                        'products' => [
                            ['name' => 'Orange Pekoe Tea', 'brand' => 'Red Rose'],
                            ['name' => 'English Breakfast Tea', 'brand' => 'Twinings'],
                            ['name' => 'Original Black Tea', 'brand' => 'Tetley'],
                            ['name' => 'Orange Pekoe Tea', 'brand' => 'Lipton'],
                            ['name' => 'Orange Pekoe Tea', 'brand' => 'King Cole'],
                        ],
                    ],
                    'Green Tea' => [
                        'tags' => ['Shelf-Stable', 'Keep Dry'],
                        'products' => [
                            ['name' => 'Pure Green Tea', 'brand' => 'Twinings'],
                            ['name' => 'Green Tea', 'brand' => 'Tetley'],
                            ['name' => 'Green Tea', 'brand' => 'Lipton'],
                            ['name' => 'Green Tea', 'brand' => 'Bigelow'],
                            ['name' => 'Green Tea', 'brand' => 'Uncle Lee\'s Tea'],
                        ],
                    ],
                    'Herbal Tea' => [
                        'tags' => ['Shelf-Stable', 'Keep Dry'],
                        'products' => [
                            ['name' => 'Peppermint Tea', 'brand' => 'Traditional Medicinals'],
                            ['name' => 'Chamomile Tea', 'brand' => 'Twinings'],
                            ['name' => 'Sleepytime Herbal Tea', 'brand' => 'Celestial Seasonings'],
                            ['name' => 'Lemon Ginger Tea', 'brand' => 'Stash'],
                            ['name' => 'Wild Berry Zinger', 'brand' => 'Celestial Seasonings'],
                        ],
                    ],
                    'Ground Coffee' => [
                        'tags' => ['Shelf-Stable', 'Keep Dry'],
                        'products' => [
                            ['name' => 'Original Roast Ground Coffee', 'brand' => 'Tim Hortons'],
                            ['name' => 'Pike Place Roast', 'brand' => 'Starbucks'],
                            ['name' => 'Original Blend Ground Coffee', 'brand' => 'McCafe'],
                            ['name' => 'Classic Roast', 'brand' => 'Folgers'],
                            ['name' => 'Qualita Rossa Ground Coffee', 'brand' => 'Lavazza'],
                        ],
                    ],
                    'Instant Coffee' => [
                        'tags' => ['Shelf-Stable', 'Keep Dry'],
                        'products' => [
                            ['name' => 'Rich Instant Coffee', 'brand' => 'Nescafe'],
                            ['name' => 'House Blend Instant Coffee', 'brand' => 'Starbucks'],
                            ['name' => 'Original Instant Coffee', 'brand' => 'Maxwell House'],
                            ['name' => 'Instant Coffee', 'brand' => 'Folgers'],
                            ['name' => 'Freeze-Dried Coffee', 'brand' => 'Mount Hagen'],
                        ],
                    ],
                ],
            ],
            'Baby Care' => [
                'Diapers' => [
                    'Disposable Diapers' => [
                        'tags' => ['Baby Essentials', 'Shelf-Stable'],
                        'products' => [
                            ['name' => 'Swaddlers Diapers', 'brand' => 'Pampers'],
                            ['name' => 'Little Snugglers Diapers', 'brand' => 'Huggies'],
                            ['name' => 'Premium Diapers', 'brand' => 'Hello Bello'],
                            ['name' => 'Diapers', 'brand' => 'The Honest Company'],
                            ['name' => 'Eco-Friendly Diapers', 'brand' => 'Bambo Nature'],
                        ],
                    ],
                    'Cloth Diapers' => [
                        'tags' => ['Baby Essentials', 'Reusable'],
                        'products' => [
                            ['name' => 'Original Pocket Diaper', 'brand' => 'bumGenius'],
                            ['name' => 'Freetime Cloth Diaper', 'brand' => 'bumGenius'],
                            ['name' => 'EasyFit Cloth Diaper', 'brand' => 'TotsBots'],
                            ['name' => 'One-Size Cloth Diaper', 'brand' => 'Charlie Banana'],
                            ['name' => 'Wizard Uno Cloth Diaper', 'brand' => 'Mother-ease'],
                        ],
                    ],
                    'Overnight Diapers' => [
                        'tags' => ['Baby Essentials', 'Shelf-Stable'],
                        'products' => [
                            ['name' => 'Overnites Diapers', 'brand' => 'Huggies'],
                            ['name' => 'Swaddlers Overnights', 'brand' => 'Pampers'],
                            ['name' => 'Overnight Diapers', 'brand' => 'The Honest Company'],
                            ['name' => 'Overnight Diapers', 'brand' => 'Hello Bello'],
                            ['name' => 'Sleepy Nights Diapers', 'brand' => 'Goodnites'],
                        ],
                    ],
                    'Swim Diapers' => [
                        'tags' => ['Baby Essentials', 'Shelf-Stable'],
                        'products' => [
                            ['name' => 'Little Swimmers', 'brand' => 'Huggies'],
                            ['name' => 'Splashers', 'brand' => 'Pampers'],
                            ['name' => 'Disposable Swim Diapers', 'brand' => 'Little Toes'],
                            ['name' => 'Swim Diapers', 'brand' => 'Hello Bello'],
                            ['name' => 'Swim Diapers', 'brand' => 'Naty'],
                        ],
                    ],
                    'Training Pants' => [
                        'tags' => ['Baby Essentials', 'Shelf-Stable'],
                        'products' => [
                            ['name' => 'Easy Ups', 'brand' => 'Pampers'],
                            ['name' => 'Pull-Ups Training Pants', 'brand' => 'Huggies'],
                            ['name' => 'Potty Training Pants', 'brand' => 'The Honest Company'],
                            ['name' => 'Training Pants', 'brand' => 'Hello Bello'],
                            ['name' => 'Training Pants', 'brand' => 'Rascal + Friends'],
                        ],
                    ],
                ],
                'Baby Wipes' => [
                    'Fragrance-Free Wipes' => [
                        'tags' => ['Baby Essentials', 'Fragrance-Free'],
                        'products' => [
                            ['name' => 'Sensitive Baby Wipes', 'brand' => 'Pampers'],
                            ['name' => 'Natural Care Unscented Wipes', 'brand' => 'Huggies'],
                            ['name' => 'Fragrance-Free Wipes', 'brand' => 'The Honest Company'],
                            ['name' => 'Unscented Wipes', 'brand' => 'Hello Bello'],
                            ['name' => 'Free and Clear Baby Wipes', 'brand' => 'Seventh Generation'],
                        ],
                    ],
                    'Sensitive Skin Wipes' => [
                        'tags' => ['Baby Essentials', 'Sensitive Skin'],
                        'products' => [
                            ['name' => 'Sensitive Baby Wipes', 'brand' => 'Pampers'],
                            ['name' => 'Natural Care Sensitive Wipes', 'brand' => 'Huggies'],
                            ['name' => 'Sensitive Baby Wipes', 'brand' => 'WaterWipes'],
                            ['name' => 'Sensitive Wipes', 'brand' => 'Rascal + Friends'],
                            ['name' => 'Sensitive Baby Wipes', 'brand' => 'Naty'],
                        ],
                    ],
                    'Water-Based Wipes' => [
                        'tags' => ['Baby Essentials', 'Shelf-Stable'],
                        'products' => [
                            ['name' => 'Original Baby Wipes', 'brand' => 'WaterWipes'],
                            ['name' => 'Aqua Pure Wipes', 'brand' => 'Pampers'],
                            ['name' => 'Natural Care Pure Wipes', 'brand' => 'Huggies'],
                            ['name' => 'Water Wipes', 'brand' => 'The Honest Company'],
                            ['name' => 'Water-Based Baby Wipes', 'brand' => 'Mama Bear'],
                        ],
                    ],
                    'Plant-Based Wipes' => [
                        'tags' => ['Baby Essentials', 'Shelf-Stable'],
                        'products' => [
                            ['name' => 'Plant-Based Baby Wipes', 'brand' => 'The Honest Company'],
                            ['name' => 'Free and Clear Wipes', 'brand' => 'Seventh Generation'],
                            ['name' => 'Plant-Based Wipes', 'brand' => 'Hello Bello'],
                            ['name' => 'Plant-Based Baby Wipes', 'brand' => 'Naty'],
                            ['name' => 'Plant-Based Wipes', 'brand' => 'Bambo Nature'],
                        ],
                    ],
                    'Scented Wipes' => [
                        'tags' => ['Baby Essentials', 'Resealable'],
                        'products' => [
                            ['name' => 'Baby Fresh Wipes', 'brand' => 'Pampers'],
                            ['name' => 'Simply Clean Fresh Wipes', 'brand' => 'Huggies'],
                            ['name' => 'Cucumber Wipes', 'brand' => 'The Honest Company'],
                            ['name' => 'Lavender Wipes', 'brand' => 'Hello Bello'],
                            ['name' => 'Fresh Scent Wipes', 'brand' => 'Parent\'s Choice'],
                        ],
                    ],
                ],
                'Infant Formula' => [
                    'Standard Milk-Based Formula' => [
                        'tags' => ['Baby Essentials', 'Shelf-Stable'],
                        'products' => [
                            ['name' => 'NeuroPro Infant Formula', 'brand' => 'Enfamil'],
                            ['name' => 'Advance Infant Formula', 'brand' => 'Similac'],
                            ['name' => 'Good Start Plus Infant Formula', 'brand' => 'Nestle'],
                            ['name' => 'Organic Dairy Infant Formula', 'brand' => 'Earth\'s Best'],
                            ['name' => 'Organic Infant Formula', 'brand' => 'Kendamil'],
                        ],
                    ],
                    'Soy-Based Formula' => [
                        'tags' => ['Baby Essentials', 'Shelf-Stable'],
                        'products' => [
                            ['name' => 'ProSobee Infant Formula', 'brand' => 'Enfamil'],
                            ['name' => 'Soy Isomil Infant Formula', 'brand' => 'Similac'],
                            ['name' => 'Good Start Soy Infant Formula', 'brand' => 'Gerber'],
                            ['name' => 'Soy Infant Formula', 'brand' => 'Earth\'s Best'],
                            ['name' => 'NAN Soy Infant Formula', 'brand' => 'Nestle'],
                        ],
                    ],
                    'Lactose-Free Formula' => [
                        'tags' => ['Baby Essentials', 'Shelf-Stable'],
                        'products' => [
                            ['name' => 'LF Lactose-Free Infant Milk', 'brand' => 'SMA Nutrition'],
                            ['name' => 'Lactose-Free Infant Milk', 'brand' => 'Aptamil'],
                            ['name' => 'NAN Lactose-Free Formula', 'brand' => 'Nestle'],
                            ['name' => 'O-Lac Infant Formula', 'brand' => 'Enfamil'],
                            ['name' => 'Novalac Lactose-Free Formula', 'brand' => 'Novalac'],
                        ],
                    ],
                    'Hypoallergenic Formula' => [
                        'tags' => ['Baby Essentials', 'Shelf-Stable'],
                        'products' => [
                            ['name' => 'Nutramigen Infant Formula', 'brand' => 'Enfamil'],
                            ['name' => 'Alimentum Infant Formula', 'brand' => 'Similac'],
                            ['name' => 'Pepti 1 Infant Formula', 'brand' => 'Aptamil'],
                            ['name' => 'Extensive HA Infant Formula', 'brand' => 'Gerber'],
                            ['name' => 'PurAmino Infant Formula', 'brand' => 'Enfamil'],
                        ],
                    ],
                    'Specialty Formula' => [
                        'tags' => ['Baby Essentials', 'Shelf-Stable'],
                        'products' => [
                            ['name' => 'EnfaCare Infant Formula', 'brand' => 'Enfamil'],
                            ['name' => 'NeoSure Infant Formula', 'brand' => 'Similac'],
                            ['name' => 'Infant DHA/ARA Formula', 'brand' => 'Neocate'],
                            ['name' => 'Infant Formula', 'brand' => 'EleCare'],
                            ['name' => 'Alfamino Infant Formula', 'brand' => 'Nestle'],
                        ],
                    ],
                ],
            ],
            'Personal Care' => [
                'Oral Care' => [
                    'Toothpaste' => [
                        'tags' => ['Personal Hygiene', 'Shelf-Stable'],
                        'products' => [
                            ['name' => 'Cavity Protection Toothpaste', 'brand' => 'Colgate'],
                            ['name' => 'Pro-Health Toothpaste', 'brand' => 'Crest'],
                            ['name' => 'Pronamel Toothpaste', 'brand' => 'Sensodyne'],
                            ['name' => 'Enamel Strength Toothpaste', 'brand' => 'Arm & Hammer'],
                            ['name' => 'Total Care Toothpaste', 'brand' => 'Tom\'s of Maine'],
                        ],
                    ],
                    'Toothbrushes' => [
                        'tags' => ['Personal Hygiene', 'Reusable'],
                        'products' => [
                            ['name' => 'Pro-Health Toothbrush', 'brand' => 'Oral-B'],
                            ['name' => 'Extra Clean Toothbrush', 'brand' => 'Colgate'],
                            ['name' => 'Sensitive Toothbrush', 'brand' => 'Sensodyne'],
                            ['name' => 'Deep Clean Toothbrush', 'brand' => 'GUM'],
                            ['name' => 'Slim Soft Toothbrush', 'brand' => 'Colgate'],
                        ],
                    ],
                    'Dental Floss' => [
                        'tags' => ['Personal Hygiene', 'Shelf-Stable'],
                        'products' => [
                            ['name' => 'Glide Dental Floss', 'brand' => 'Oral-B'],
                            ['name' => 'Total Dental Floss', 'brand' => 'Colgate'],
                            ['name' => 'Reach Waxed Floss', 'brand' => 'Reach'],
                            ['name' => 'Butlerweave Dental Floss', 'brand' => 'GUM'],
                            ['name' => 'Dental Flossers', 'brand' => 'Plackers'],
                        ],
                    ],
                    'Mouthwash' => [
                        'tags' => ['Personal Hygiene', 'Shelf-Stable'],
                        'products' => [
                            ['name' => 'Total Care Mouthwash', 'brand' => 'Listerine'],
                            ['name' => 'Pro-Health Mouthwash', 'brand' => 'Crest'],
                            ['name' => 'Anticavity Rinse', 'brand' => 'ACT'],
                            ['name' => 'Therapeutic Oral Rinse', 'brand' => 'TheraBreath'],
                            ['name' => 'Complete Care Mouthwash', 'brand' => 'Colgate'],
                        ],
                    ],
                    'Denture Care' => [
                        'tags' => ['Personal Hygiene', 'Shelf-Stable'],
                        'products' => [
                            ['name' => 'Denture Cleanser Tablets', 'brand' => 'Polident'],
                            ['name' => 'Denture Adhesive Cream', 'brand' => 'Fixodent'],
                            ['name' => 'Denture Adhesive Cream', 'brand' => 'Poligrip'],
                            ['name' => 'Denture Cleanser Tablets', 'brand' => 'Efferdent'],
                            ['name' => 'Denture Brush', 'brand' => 'GUM'],
                        ],
                    ],
                ],
                'Hair Care' => [
                    'Shampoo' => [
                        'tags' => ['Personal Hygiene', 'Shelf-Stable'],
                        'products' => [
                            ['name' => 'Daily Moisture Shampoo', 'brand' => 'Dove'],
                            ['name' => 'Classic Clean Shampoo', 'brand' => 'Head & Shoulders'],
                            ['name' => 'Daily Moisture Renewal Shampoo', 'brand' => 'Pantene'],
                            ['name' => 'Extraordinary Oil Shampoo', 'brand' => 'L\'Oreal Paris'],
                            ['name' => 'Whole Blends Shampoo', 'brand' => 'Garnier'],
                        ],
                    ],
                    'Conditioner' => [
                        'tags' => ['Personal Hygiene', 'Shelf-Stable'],
                        'products' => [
                            ['name' => 'Daily Moisture Conditioner', 'brand' => 'Dove'],
                            ['name' => 'Daily Moisture Renewal Conditioner', 'brand' => 'Pantene'],
                            ['name' => 'Extraordinary Oil Conditioner', 'brand' => 'L\'Oreal Paris'],
                            ['name' => 'Whole Blends Conditioner', 'brand' => 'Garnier'],
                            ['name' => 'Moisture Rich Conditioner', 'brand' => 'TRESemme'],
                        ],
                    ],
                    'Detangling Products' => [
                        'tags' => ['Personal Hygiene', 'Shelf-Stable'],
                        'products' => [
                            ['name' => 'No More Tangles Detangling Spray', 'brand' => 'Johnson\'s'],
                            ['name' => 'Kids Detangler', 'brand' => 'Suave'],
                            ['name' => 'Kids Detangling Spray', 'brand' => 'The Honest Company'],
                            ['name' => 'Hair Milk Detangling Spray', 'brand' => 'Carol\'s Daughter'],
                            ['name' => 'Miracle Leave-In Product', 'brand' => 'It\'s a 10'],
                        ],
                    ],
                    'Styling Products' => [
                        'tags' => ['Personal Hygiene', 'Shelf-Stable'],
                        'products' => [
                            ['name' => 'Extra Hold Hairspray', 'brand' => 'TRESemme'],
                            ['name' => 'Ultra Glued Styling Gel', 'brand' => 'Got2b'],
                            ['name' => 'Fructis Style Gel', 'brand' => 'Garnier'],
                            ['name' => 'Elnett Satin Hairspray', 'brand' => 'L\'Oreal Paris'],
                            ['name' => 'Classic Pomade', 'brand' => 'American Crew'],
                        ],
                    ],
                    'Scalp Care' => [
                        'tags' => ['Personal Hygiene', 'Shelf-Stable'],
                        'products' => [
                            ['name' => 'Clinical Strength Shampoo', 'brand' => 'Head & Shoulders'],
                            ['name' => 'Anti-Dandruff Shampoo', 'brand' => 'Selsun Blue'],
                            ['name' => 'Anti-Dandruff Shampoo', 'brand' => 'Nizoral'],
                            ['name' => 'T/Sal Shampoo', 'brand' => 'Neutrogena'],
                            ['name' => 'Tea Tree Special Shampoo', 'brand' => 'Paul Mitchell'],
                        ],
                    ],
                ],
                'Bath and Body' => [
                    'Body Wash' => [
                        'tags' => ['Personal Hygiene', 'Shelf-Stable'],
                        'products' => [
                            ['name' => 'Deep Moisture Body Wash', 'brand' => 'Dove'],
                            ['name' => 'Ultra Moisture Body Wash', 'brand' => 'Olay'],
                            ['name' => 'Body Wash', 'brand' => 'Nivea'],
                            ['name' => 'Original Body Wash', 'brand' => 'Irish Spring'],
                            ['name' => 'Daily Moisturizing Body Wash', 'brand' => 'Aveeno'],
                        ],
                    ],
                    'Bar Soap' => [
                        'tags' => ['Personal Hygiene', 'Shelf-Stable'],
                        'products' => [
                            ['name' => 'Original Beauty Bar', 'brand' => 'Dove'],
                            ['name' => 'Original Bar Soap', 'brand' => 'Irish Spring'],
                            ['name' => 'Pure and Natural Soap', 'brand' => 'Ivory'],
                            ['name' => 'Deodorant Soap', 'brand' => 'Dial'],
                            ['name' => 'Pure Castile Bar Soap', 'brand' => 'Dr. Bronner\'s'],
                        ],
                    ],
                    'Hand Soap' => [
                        'tags' => ['Personal Hygiene', 'Shelf-Stable'],
                        'products' => [
                            ['name' => 'Liquid Hand Soap', 'brand' => 'Softsoap'],
                            ['name' => 'Liquid Hand Soap', 'brand' => 'Dial'],
                            ['name' => 'Foaming Hand Soap', 'brand' => 'Method'],
                            ['name' => 'Hand Soap', 'brand' => 'Mrs. Meyer\'s Clean Day'],
                            ['name' => 'Liquid Hand Soap', 'brand' => 'Live Clean'],
                        ],
                    ],
                    'Body Lotion' => [
                        'tags' => ['Personal Hygiene', 'Shelf-Stable'],
                        'products' => [
                            ['name' => 'Daily Moisturizing Lotion', 'brand' => 'Aveeno'],
                            ['name' => 'Intensive Care Lotion', 'brand' => 'Vaseline'],
                            ['name' => 'Daily Hydration Lotion', 'brand' => 'Eucerin'],
                            ['name' => 'Moisturizing Lotion', 'brand' => 'CeraVe'],
                            ['name' => 'Original Moisturizer', 'brand' => 'Lubriderm'],
                        ],
                    ],
                    'Deodorant' => [
                        'tags' => ['Personal Hygiene', 'Shelf-Stable'],
                        'products' => [
                            ['name' => 'Advanced Care Deodorant', 'brand' => 'Dove'],
                            ['name' => 'Invisible Solid Deodorant', 'brand' => 'Secret'],
                            ['name' => 'High Endurance Deodorant', 'brand' => 'Old Spice'],
                            ['name' => 'Speed Stick Deodorant', 'brand' => 'Speed Stick'],
                            ['name' => 'Aluminum-Free Deodorant', 'brand' => 'Native'],
                        ],
                    ],
                ],
            ],
            'Household Supplies' => [
                'Laundry Supplies' => [
                    'Laundry Detergent' => [
                        'tags' => ['Cleaning Supplies', 'Shelf-Stable'],
                        'products' => [
                            ['name' => 'Original Laundry Detergent', 'brand' => 'Tide'],
                            ['name' => 'Original Laundry Detergent', 'brand' => 'Gain'],
                            ['name' => 'Liquid Laundry Detergent', 'brand' => 'Persil'],
                            ['name' => 'Clean Burst Detergent', 'brand' => 'Arm & Hammer'],
                            ['name' => 'Free and Clear Detergent', 'brand' => 'Seventh Generation'],
                        ],
                    ],
                    'Fabric Softener' => [
                        'tags' => ['Cleaning Supplies', 'Shelf-Stable'],
                        'products' => [
                            ['name' => 'Ultra April Fresh Fabric Softener', 'brand' => 'Downy'],
                            ['name' => 'Blue Sparkle Fabric Softener', 'brand' => 'Snuggle'],
                            ['name' => 'Original Fabric Softener', 'brand' => 'Gain'],
                            ['name' => 'Fabric Softener', 'brand' => 'Fleecy'],
                            ['name' => 'Fabric Softener', 'brand' => 'ATTITUDE'],
                        ],
                    ],
                    'Stain Remover' => [
                        'tags' => ['Cleaning Supplies', 'Shelf-Stable'],
                        'products' => [
                            ['name' => 'Versatile Stain Remover', 'brand' => 'OxiClean'],
                            ['name' => 'Triple-Acting Stain Remover', 'brand' => 'Shout'],
                            ['name' => 'Laundry Stain Remover', 'brand' => 'Resolve'],
                            ['name' => 'Max Force Stain Remover', 'brand' => 'OxiClean'],
                            ['name' => 'Stain Remover', 'brand' => 'ATTITUDE'],
                        ],
                    ],
                    'Laundry Bleach' => [
                        'tags' => ['Cleaning Supplies', 'Shelf-Stable'],
                        'products' => [
                            ['name' => 'Disinfecting Bleach', 'brand' => 'Clorox'],
                            ['name' => 'Original Bleach', 'brand' => 'Javex'],
                            ['name' => 'Concentrated Bleach', 'brand' => 'Great Value'],
                            ['name' => 'Bleach', 'brand' => 'Compliments'],
                            ['name' => 'Laundry Bleach', 'brand' => 'No Name'],
                        ],
                    ],
                    'Dryer Sheets' => [
                        'tags' => ['Cleaning Supplies', 'Shelf-Stable'],
                        'products' => [
                            ['name' => 'Outdoor Fresh Dryer Sheets', 'brand' => 'Bounce'],
                            ['name' => 'Original Dryer Sheets', 'brand' => 'Gain'],
                            ['name' => 'Blue Sparkle Dryer Sheets', 'brand' => 'Snuggle'],
                            ['name' => 'Dryer Sheets', 'brand' => 'Fleecy'],
                            ['name' => 'Free and Clear Dryer Sheets', 'brand' => 'Seventh Generation'],
                        ],
                    ],
                ],
                'Dishwashing Supplies' => [
                    'Dish Soap' => [
                        'tags' => ['Cleaning Supplies', 'Shelf-Stable'],
                        'products' => [
                            ['name' => 'Ultra Dishwashing Liquid', 'brand' => 'Dawn'],
                            ['name' => 'Original Dish Liquid', 'brand' => 'Palmolive'],
                            ['name' => 'Dish Liquid', 'brand' => 'Sunlight'],
                            ['name' => 'Dish Soap', 'brand' => 'Method'],
                            ['name' => 'Free and Clear Dish Liquid', 'brand' => 'Seventh Generation'],
                        ],
                    ],
                    'Dishwasher Detergent' => [
                        'tags' => ['Cleaning Supplies', 'Shelf-Stable'],
                        'products' => [
                            ['name' => 'Platinum Dishwasher Detergent', 'brand' => 'Cascade'],
                            ['name' => 'Quantum Dishwasher Detergent', 'brand' => 'Finish'],
                            ['name' => 'Dishwasher Detergent', 'brand' => 'Seventh Generation'],
                            ['name' => 'Dishwasher Detergent', 'brand' => 'ATTITUDE'],
                            ['name' => 'Dishwasher Detergent', 'brand' => 'Great Value'],
                        ],
                    ],
                    'Dishwasher Rinse Aid' => [
                        'tags' => ['Cleaning Supplies', 'Shelf-Stable'],
                        'products' => [
                            ['name' => 'Jet-Dry Rinse Aid', 'brand' => 'Finish'],
                            ['name' => 'PowerDry Rinse Aid', 'brand' => 'Cascade'],
                            ['name' => 'Rinse Aid', 'brand' => 'Seventh Generation'],
                            ['name' => 'Dishwasher Rinse Aid', 'brand' => 'ATTITUDE'],
                            ['name' => 'Rinse Aid', 'brand' => 'Ecover'],
                        ],
                    ],
                    'Dish Sponges' => [
                        'tags' => ['Cleaning Supplies', 'Reusable'],
                        'products' => [
                            ['name' => 'Non-Scratch Scrub Sponge', 'brand' => 'Scotch-Brite'],
                            ['name' => 'Original Scrubber', 'brand' => 'Scrub Daddy'],
                            ['name' => 'Heavy Duty Scrub Sponge', 'brand' => 'Vileda'],
                            ['name' => 'Non-Scratch Sponge', 'brand' => 'O-Cedar'],
                            ['name' => 'Walnut Scrubber Sponge', 'brand' => 'Full Circle'],
                        ],
                    ],
                    'Dish Brushes' => [
                        'tags' => ['Cleaning Supplies', 'Reusable'],
                        'products' => [
                            ['name' => 'Soap Dispensing Dish Brush', 'brand' => 'OXO'],
                            ['name' => 'Dish Brush', 'brand' => 'Vileda'],
                            ['name' => 'Dish Brush', 'brand' => 'Scotch-Brite'],
                            ['name' => 'Be Good Dish Brush', 'brand' => 'Full Circle'],
                            ['name' => 'Dish Brush', 'brand' => 'Joseph Joseph'],
                        ],
                    ],
                ],
                'Paper Products' => [
                    'Toilet Paper' => [
                        'tags' => ['Paper Goods', 'Keep Dry'],
                        'products' => [
                            ['name' => 'Ultra Soft Toilet Paper', 'brand' => 'Charmin'],
                            ['name' => 'ComfortPlus Toilet Paper', 'brand' => 'Scott'],
                            ['name' => 'Ultra Comfort Toilet Paper', 'brand' => 'Cottonelle'],
                            ['name' => 'Premium Toilet Paper', 'brand' => 'Cashmere'],
                            ['name' => 'Original Toilet Paper', 'brand' => 'Royale'],
                        ],
                    ],
                    'Paper Towels' => [
                        'tags' => ['Paper Goods', 'Keep Dry'],
                        'products' => [
                            ['name' => 'Select-A-Size Paper Towels', 'brand' => 'Bounty'],
                            ['name' => 'Original Paper Towels', 'brand' => 'SpongeTowels'],
                            ['name' => 'Pick-A-Size Paper Towels', 'brand' => 'Scott'],
                            ['name' => 'Velour Paper Towels', 'brand' => 'Royale'],
                            ['name' => 'Paper Towels', 'brand' => 'Great Value'],
                        ],
                    ],
                    'Facial Tissues' => [
                        'tags' => ['Paper Goods', 'Keep Dry'],
                        'products' => [
                            ['name' => 'Everyday Facial Tissues', 'brand' => 'Kleenex'],
                            ['name' => 'Plus Lotion Facial Tissues', 'brand' => 'Puffs'],
                            ['name' => 'Original Facial Tissues', 'brand' => 'Royale'],
                            ['name' => 'Supreme Facial Tissues', 'brand' => 'Scotties'],
                            ['name' => 'Facial Tissues', 'brand' => 'Great Value'],
                        ],
                    ],
                    'Paper Napkins' => [
                        'tags' => ['Paper Goods', 'Keep Dry'],
                        'products' => [
                            ['name' => 'Everyday Napkins', 'brand' => 'Bounty'],
                            ['name' => 'Dinner Napkins', 'brand' => 'Vanity Fair'],
                            ['name' => 'Paper Napkins', 'brand' => 'Royale'],
                            ['name' => 'Paper Napkins', 'brand' => 'Great Value'],
                            ['name' => 'Paper Napkins', 'brand' => 'Compliments'],
                        ],
                    ],
                    'Paper Plates' => [
                        'tags' => ['Paper Goods', 'Keep Dry'],
                        'products' => [
                            ['name' => 'Everyday Paper Plates', 'brand' => 'Dixie'],
                            ['name' => 'Paper Plates', 'brand' => 'Chinet'],
                            ['name' => 'Paper Plates', 'brand' => 'Hefty'],
                            ['name' => 'Paper Plates', 'brand' => 'Great Value'],
                            ['name' => 'Paper Plates', 'brand' => 'Compliments'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
