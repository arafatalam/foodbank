<?php

namespace Database\Seeders;

use App\Actions\PackageSizes\GetPackageSizeDefinitionKey;
use App\Actions\StockItems\CreateStockItem;
use App\Actions\StockItems\StockItemSetup;
use App\Enums\RoleCode;
use App\Models\PackageSize;
use App\Models\Product;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use RuntimeException;

class StockItemSeeder extends Seeder
{
    public function run(CreateStockItem $action, StockItemSetup $setup): void
    {
        $actor = $this->findActor();

        foreach ($this->examples() as $example) {
            $this->seedItem($example, $actor, $action, $setup);
        }
    }

    private function findActor(): User
    {
        // Use an existing permitted account; seeding must not bypass the action's policy.
        $actor = User::query()->where('is_active', true)->where('must_change_password', false)
            ->whereHas('role', fn (Builder $roles) => $roles->whereIn('code', [
                RoleCode::SUPER_ADMIN->value, RoleCode::MANAGER->value,
            ]))->orderBy('id')->first();

        if ($actor === null) {
            throw new RuntimeException('StockItemSeeder needs an active, setup-complete Manager or Super Admin. Run the account seeders first.');
        }

        return $actor;
    }

    /** @param array{string, string, string, ?string, string, string, string} $example */
    private function seedItem(array $example, User $actor, CreateStockItem $action, StockItemSetup $setup): void
    {
        [$department, $category, $name, $brand, $type, $contents, $unit] = $example;
        $product = $this->findProduct($department, $category, $name);
        $package = $this->findPackage($type, $contents, $unit);
        $normalizedBrand = $setup->normalizeBrand($brand);

        // Rerunning keeps existing stock identities, spelling and balances untouched.
        if ($this->alreadyExists($product, $package, $normalizedBrand['brand_key'])) {
            return;
        }

        // The normal action also initializes stock_product_settings and checks compatibility.
        $action->create([
            'product_id' => $product->id,
            'package_size_id' => $package->id,
            'brand' => $brand,
        ], $actor);
    }

    private function findProduct(string $department, string $category, string $name): Product
    {
        // Resolve the full catalogue path instead of relying on fixed IDs or name alone.
        return Product::query()->where('name', $name)
            ->whereHas('category', fn (Builder $categories) => $categories->where('name', $category)
                ->whereHas('department', fn (Builder $departments) => $departments->where('name', $department)))
            ->firstOrFail();
    }

    private function findPackage(string $type, string $contents, string $unit): PackageSize
    {
        // Find the reusable equivalent definition, using the same exact key as package setup.
        $key = (new GetPackageSizeDefinitionKey)->get([
            'package_type' => $type, 'contents' => $contents, 'contents_unit' => $unit,
        ]);

        return PackageSize::query()->where('definition_key', $key)->firstOrFail();
    }

    private function alreadyExists(Product $product, PackageSize $package, string $brandKey): bool
    {
        return StockItem::query()->where('product_id', $product->id)
            ->where('package_size_id', $package->id)->where('brand_key', $brandKey)->exists();
    }

    /** @return list<array{string, string, string, ?string, string, string, string}> */
    private function examples(): array
    {
        $examples = [];

        // Sixty Products get one choice each; the first 39 also get an unbranded choice.
        foreach ($this->sampleProducts() as $index => [$department, $category, $name, $brand, $package]) {
            $examples[] = [$department, $category, $name, $brand, ...$package];
            if ($index < 39) {
                $examples[] = [$department, $category, $name, null, ...$package];
            }
        }

        // A smaller Rice package makes the final total 60 + 39 + 1 = 100 choices.
        $examples[] = ['Food', 'Grains', 'Rice', 'Tilda', 'bag', '1', 'kg'];

        return $examples;
    }

    /** @return list<array{string, string, string, string, array{string, string, string}}> */
    private function sampleProducts(): array
    {
        // Each row: catalogue path, sample brand and an existing reusable package.
        // Package tuples contain type, contents and unit; opening stock still belongs to Receive.
        return [
            ['Food', 'Grains', 'Rice', 'Tilda', ['bag', '5', 'kg']],
            ['Food', 'Grains', 'Oats', 'Quaker', ['bag', '1', 'kg']],
            ['Food', 'Pasta and Noodles', 'Pasta', 'Barilla', ['box', '500', 'g']],
            ['Food', 'Beans and Pulses', 'Lentils', 'Unico', ['bag', '1', 'kg']],
            ['Food', 'Canned Foods', 'Canned Tomatoes', 'Unico', ['can', '400', 'g']],
            ['Food', 'Canned Foods', 'Canned Corn', 'Green Giant', ['can', '400', 'g']],
            ['Food', 'Canned Foods', 'Canned Tuna', 'Clover Leaf', ['can', '200', 'g']],
            ['Food', 'Soups and Sauces', 'Soup', 'Campbell’s', ['can', '400', 'ml']],
            ['Food', 'Breakfast and Spreads', 'Peanut Butter', 'Kraft', ['jar', '500', 'g']],
            ['Food', 'Dairy and Eggs', 'Milk', 'Lactantia', ['carton', '1', 'litre']],
            ['Food', 'Beverages', 'Water', 'Eska', ['bottle', '500', 'ml']],
            ['Food', 'Cooking Essentials', 'Cooking Oil', 'Mazola', ['bottle', '1', 'litre']],
            ['Food', 'Fruit', 'Apples', 'Orchard Select', ['individual_item', '1', 'item']],
            ['Food', 'Bakery', 'Bread', 'Dempster’s', ['individual_item', '1', 'item']],
            ['Food', 'Dairy and Eggs', 'Eggs', 'Burnbrae', ['box', '12', 'item']],
            ['Baby Care', 'Changing Supplies', 'Diapers', 'Pampers', ['box', '24', 'item']],
            ['Personal Care', 'Oral Care', 'Toothbrush', 'Colgate', ['individual_item', '1', 'item']],
            ['Personal Care', 'Hair Care', 'Shampoo', 'Dove', ['bottle', '500', 'ml']],
            ['Household Supplies', 'Cleaning Supplies', 'Dish Soap', 'Dawn', ['bottle', '750', 'ml']],
            ['Household Supplies', 'Paper Products', 'Toilet Paper', 'Royale', ['box', '6', 'item']],
            ['Food', 'Grains', 'Couscous', 'Casbah', ['bag', '500', 'g']],
            ['Food', 'Grains', 'Quinoa', 'GoGo Quinoa', ['bag', '500', 'g']],
            ['Food', 'Grains', 'Flour', 'Robin Hood', ['bag', '2', 'kg']],
            ['Food', 'Pasta and Noodles', 'Noodles', 'Rooster', ['packet', '250', 'g']],
            ['Food', 'Pasta and Noodles', 'Macaroni and Cheese', 'Kraft', ['box', '250', 'g']],
            ['Food', 'Beans and Pulses', 'Chickpeas', 'Unico', ['can', '400', 'g']],
            ['Food', 'Beans and Pulses', 'Kidney Beans', 'Primo', ['can', '400', 'g']],
            ['Food', 'Beans and Pulses', 'Black Beans', 'Unico', ['can', '400', 'g']],
            ['Food', 'Canned Foods', 'Canned Peas', 'Green Giant', ['can', '400', 'g']],
            ['Food', 'Canned Foods', 'Canned Salmon', 'Clover Leaf', ['can', '200', 'g']],
            ['Food', 'Soups and Sauces', 'Broth', 'Campbell’s', ['carton', '1', 'litre']],
            ['Food', 'Soups and Sauces', 'Pasta Sauce', 'Classico', ['jar', '500', 'g']],
            ['Food', 'Breakfast and Spreads', 'Breakfast Cereal', 'Cheerios', ['box', '500', 'g']],
            ['Food', 'Breakfast and Spreads', 'Jam', 'Smucker’s', ['jar', '250', 'g']],
            ['Food', 'Dairy and Eggs', 'Cheese', 'Black Diamond', ['packet', '250', 'g']],
            ['Food', 'Dairy and Eggs', 'Yogurt', 'Astro', ['jar', '750', 'g']],
            ['Food', 'Meat and Alternatives', 'Chicken', 'Maple Leaf', ['bag', '1', 'kg']],
            ['Food', 'Fruit', 'Bananas', 'Chiquita', ['individual_item', '1', 'item']],
            ['Food', 'Vegetables', 'Potatoes', 'Farm Fresh', ['bag', '5', 'kg']],
            ['Food', 'Frozen Foods', 'Frozen Vegetables', 'Green Giant', ['bag', '1', 'kg']],
            ['Baby Care', 'Infant Feeding', 'Infant Formula', 'Enfamil', ['can', '800', 'g']],
            ['Baby Care', 'Infant Feeding', 'Baby Cereal', 'Gerber', ['box', '250', 'g']],
            ['Baby Care', 'Infant Feeding', 'Baby Food', 'Heinz', ['jar', '250', 'g']],
            ['Baby Care', 'Changing Supplies', 'Baby Wipes', 'Huggies', ['box', '24', 'item']],
            ['Baby Care', 'Changing Supplies', 'Diaper Cream', 'Sudocrem', ['jar', '250', 'g']],
            ['Baby Care', 'Baby Hygiene', 'Baby Shampoo', 'Johnson’s', ['bottle', '250', 'ml']],
            ['Baby Care', 'Baby Hygiene', 'Baby Wash', 'Aveeno', ['bottle', '500', 'ml']],
            ['Baby Care', 'Baby Hygiene', 'Baby Lotion', 'Aveeno', ['bottle', '250', 'ml']],
            ['Personal Care', 'Oral Care', 'Toothpaste', 'Colgate', ['packet', '100', 'g']],
            ['Personal Care', 'Oral Care', 'Dental Floss', 'Oral-B', ['individual_item', '1', 'item']],
            ['Personal Care', 'Hair Care', 'Conditioner', 'Pantene', ['bottle', '500', 'ml']],
            ['Personal Care', 'Hair Care', 'Comb', 'Goody', ['individual_item', '1', 'item']],
            ['Personal Care', 'Body Care', 'Bar Soap', 'Dove', ['packet', '100', 'g']],
            ['Personal Care', 'Body Care', 'Body Wash', 'Dove', ['bottle', '500', 'ml']],
            ['Personal Care', 'Menstrual Care', 'Sanitary Pads', 'Always', ['box', '24', 'item']],
            ['Personal Care', 'Incontinence Care', 'Adult Briefs', 'Depend', ['box', '12', 'item']],
            ['Household Supplies', 'Cleaning Supplies', 'Laundry Detergent', 'Tide', ['bottle', '2', 'litre']],
            ['Household Supplies', 'Cleaning Supplies', 'All-Purpose Cleaner', 'Lysol', ['bottle', '750', 'ml']],
            ['Household Supplies', 'Paper Products', 'Paper Towels', 'Bounty', ['box', '6', 'item']],
            ['Household Supplies', 'Storage Supplies', 'Garbage Bags', 'Glad', ['box', '24', 'item']],
        ];
    }
}
