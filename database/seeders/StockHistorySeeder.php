<?php

namespace Database\Seeders;

use App\Actions\Adjustment\CreateAdjustment;
use App\Actions\Distribution\CreateDistribution;
use App\Actions\PackageSizes\GetPackageSizeDefinitionKey;
use App\Actions\Receive\CreateReceive;
use App\Actions\StockItems\StockItemSetup;
use App\Enums\RoleCode;
use App\Models\Household;
use App\Models\StockEntry;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockHistorySeeder extends Seeder
{
    private const OPENING_NOTE = 'FoodBank demo history v2: pantry staples donation, batch 1.';

    private const LEGACY_OPENING_NOTE = 'FoodBank demo history v1: opening community donation.';

    public function run(CreateReceive $receive, CreateDistribution $distribute, CreateAdjustment $adjust): void
    {
        // Demo stock must never be added to a production database.
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('StockHistorySeeder is only for local or testing environments.');
        }

        // Save the complete sample together; any error also removes its opening marker.
        DB::transaction(function () use ($receive, $distribute, $adjust): void {
            // Lock the same recorder on every run to prevent simultaneous duplicate runs.
            $manager = $this->findRecorder('amina.rahman', RoleCode::MANAGER);

            // This marker is saved with the entire demo, so reruns cannot add it twice.
            if (StockEntry::query()->where('type', 'receive')->whereIn('notes', [self::OPENING_NOTE, self::LEGACY_OPENING_NOTE])->exists()) {
                $this->command?->info('Demo stock history already exists. No quantities were changed.');

                return;
            }

            $volunteer = $this->findRecorder('farah.khan', RoleCode::VOLUNTEER);
            $items = $this->findDemoItems();
            $households = Household::query()->orderBy('id')->limit(20)->get();

            if ($households->count() !== 20) {
                throw new RuntimeException('Run HouseholdSeeder first: demo history needs 20 households.');
            }

            // Group donations by related goods, then cap each receipt at six stock choices.
            $items->load(['product.category.department', 'packageSize']);
            foreach ($this->donationBatches($items) as $batch) {
                $receive->create($batch, $manager);
            }

            // These are fictional parcel scenarios, not assumptions about real households.
            foreach ($households as $index => $household) {
                $distribute->create([
                    'household_id' => $household->id,
                    'items' => $this->parcelItems($items, $index),
                ], $volunteer);
            }

            // A few specific incidents are more believable than correcting every stock item.
            $corrections = [
                ['Rice', 'remove', 1, 'Torn rice bag found while shelving a donation.'],
                ['Apples', 'remove', 3, 'Bruised apples removed during the produce check.'],
                ['Milk', 'remove', 1, 'Leaking milk carton removed from the cooler.'],
                ['Bread', 'remove', 2, 'Two spoiled loaves removed during the bread check.'],
                ['Flour', 'add', 2, 'Two unopened flour bags found behind another case during counting.'],
                ['Diapers', 'add', 1, 'One sealed diaper box missed during the original count.'],
                ['Dish Soap', 'remove', 1, 'One leaking dish-soap bottle removed from the shelf.'],
                ['Canned Tomatoes', 'add', 3, 'Three cans missed during unloading found in a donation crate.'],
            ];
            foreach ($corrections as $index => [$name, $direction, $quantity, $reason]) {
                $adjust->create([
                    'stock_item_id' => $this->chooseItem($items, $name)->id,
                    'direction' => $direction,
                    'quantity' => $quantity,
                    'reason' => 'Demo: '.$reason,
                ], $index % 2 === 0 ? $manager : $volunteer);
            }

            // Small mixed grocery donations and full follow-up parcels also exercise pagination.
            for ($round = 0; $round < 5; $round++) {
                $receiveItems = [];
                foreach (['Rice', 'Canned Tomatoes', 'Milk', 'Bread'] as $name) {
                    $receiveItems[] = [
                        'stock_item_id' => $this->chooseItem($items, $name)->id,
                        'quantity' => 6,
                    ];
                }
                $receive->create([
                    'notes' => 'Demo: mixed grocery donation of rice, canned tomatoes, milk and bread; delivery '.($round + 1).'.',
                    'items' => $receiveItems,
                ], $manager);
                $distribute->create([
                    'household_id' => $households[$round]->id,
                    'items' => $this->parcelItems($items, 0),
                ], $volunteer);
            }

            // Keep all 100 selected Products stocked after the sample removals.
            if (StockItem::query()->whereKey($items->modelKeys())->where('available_quantity', '<=', 0)->exists()) {
                throw new RuntimeException('Demo history must leave every selected stock choice with a positive balance.');
            }

            $this->command?->info('Demo history created for 140 stock choices, 100 Products and 20 households.');
        });
    }

    /**
     * @param  Collection<int, StockItem>  $items
     * @return list<array{notes: string, items: list<array{stock_item_id: int, quantity: positive-int}>}>
     */
    private function donationBatches(Collection $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            $category = $item->product->category->name;
            $theme = match ($category) {
                'Grains', 'Pasta and Noodles', 'Beans and Pulses', 'Cooking Essentials' => 'pantry staples donation',
                'Canned Foods', 'Soups and Sauces' => 'canned goods food drive',
                'Breakfast and Spreads', 'Beverages' => 'breakfast and drinks donation',
                'Dairy and Eggs' => 'chilled dairy and eggs delivery',
                'Meat and Alternatives', 'Frozen Foods' => 'chilled and frozen grocery delivery',
                'Fruit', 'Vegetables' => 'fresh produce donation',
                'Bakery' => 'bakery collection',
                'Infant Feeding', 'Changing Supplies', 'Baby Hygiene' => 'baby care supply drive',
                'Oral Care', 'Hair Care', 'Body Care', 'Menstrual Care', 'Incontinence Care' => 'personal care supply drive',
                'Cleaning Supplies', 'Paper Products', 'Storage Supplies' => 'household supplies donation',
                default => throw new RuntimeException("Missing demo donation group for {$category}."),
            };
            $groups[$theme][] = $item;
        }

        $batches = [];
        foreach ($groups as $theme => $group) {
            // Balance the batches so a remainder does not become a one-item receipt.
            $batchCount = max(1, (int) ceil(count($group) / 6));
            $rowsPerBatch = max(1, (int) ceil(count($group) / $batchCount));
            foreach (array_chunk($group, $rowsPerBatch) as $index => $batch) {
                $rows = [];
                foreach ($batch as $item) {
                    // Loose produce is counted by item; large bags are received in smaller numbers.
                    $quantity = match (true) {
                        in_array($item->product->category->name, ['Fruit', 'Vegetables'], true) => 60,
                        $item->packageSize->contents_unit === 'kg' && (float) $item->packageSize->contents >= 5 => 12,
                        default => 36,
                    };
                    $rows[] = ['stock_item_id' => $item->id, 'quantity' => $quantity];
                }
                $batches[] = [
                    'notes' => $batches === [] ? self::OPENING_NOTE : 'Demo: '.$theme.', batch '.($index + 1).'.',
                    'items' => $rows,
                ];
            }
        }

        return $batches;
    }

    /**
     * @param  Collection<int, StockItem>  $items
     * @return list<array{stock_item_id: int, quantity: positive-int}>
     */
    private function parcelItems(Collection $items, int $householdIndex): array
    {
        // Each parcel combines meal staples, protein, produce and useful care supplies.
        $parcels = [
            ['Rice' => 1, 'Lentils' => 1, 'Canned Tomatoes' => 2, 'Canned Corn' => 2,
                'Canned Tuna' => 1, 'Milk' => 2, 'Bread' => 1, 'Apples' => 4,
                'Carrots' => 1, 'Toothpaste' => 1, 'Bar Soap' => 1],
            ['Pasta' => 2, 'Pasta Sauce' => 1, 'Chickpeas' => 2, 'Soup' => 2,
                'Oats' => 1, 'Eggs' => 1, 'Bananas' => 6, 'Potatoes' => 1,
                'Shampoo' => 1, 'Toilet Paper' => 1],
            ['Rice' => 1, 'Black Beans' => 2, 'Canned Peas' => 2, 'Canned Salmon' => 1,
                'Peanut Butter' => 1, 'Milk' => 2, 'Rolls' => 1, 'Oranges' => 4,
                'Frozen Vegetables' => 1, 'Dish Soap' => 1, 'Toothbrush' => 2],
            ['Couscous' => 1, 'Kidney Beans' => 2, 'Broth' => 1, 'Canned Peaches' => 1,
                'Breakfast Cereal' => 1, 'Plant-Based Drink' => 2, 'Tortillas' => 1,
                'Pears' => 4, 'Onions' => 1, 'Laundry Detergent' => 1, 'Sanitary Pads' => 1],
        ];
        $parcel = $parcels[$householdIndex % count($parcels)];

        // Four fictional households also collect a separate baby-care supplement.
        if ($householdIndex % 5 === 0) {
            $parcel += ['Diapers' => 1, 'Baby Wipes' => 1, 'Baby Food' => 2];
        }

        $rows = [];
        foreach ($parcel as $name => $quantity) {
            $rows[] = [
                'stock_item_id' => $this->chooseItem($items, $name, $householdIndex)->id,
                'quantity' => $quantity,
            ];
        }

        return $rows;
    }

    /** @param Collection<int, StockItem> $items */
    private function chooseItem(Collection $items, string $name, int $variant = 0): StockItem
    {
        $choices = $items->filter(fn (StockItem $item) => $item->product->name === $name)->values();

        // Give households the smaller 1 kg rice bag rather than a 5 kg bulk bag.
        if ($name === 'Rice') {
            $choices = $choices->filter(fn (StockItem $item) => $item->packageSize->contents_unit === 'kg'
                && $item->packageSize->contents === '1.000')->values();
        }
        if ($choices->isEmpty()) {
            throw new RuntimeException("Missing stock choice for demo parcel: {$name}.");
        }

        // Vary brands without putting two versions of the same Product in one parcel.
        return $choices[$variant % $choices->count()];
    }

    private function findRecorder(string $username, RoleCode $role): User
    {
        // Use only the exact fictional, active, setup-complete account with the right role.
        $user = User::query()
            ->where('username', $username)
            ->where('email', $username.'@example.com')
            ->where('is_active', true)
            ->where('must_change_password', false)
            ->whereHas('role', fn (Builder $roles) => $roles->where('code', $role->value))
            ->lockForUpdate()
            ->first();

        if ($user === null) {
            throw new RuntimeException("Run UserSeeder and DemoAccountSetupSeeder first: {$username} must be active and setup-complete.");
        }

        return $user;
    }

    /** @return Collection<int, StockItem> */
    private function findDemoItems(): Collection
    {
        $items = new Collection;
        $keys = new GetPackageSizeDefinitionKey;
        $setup = new StockItemSetup;

        // Reuse the exact stock definitions instead of selecting arbitrary existing items.
        foreach ((new StockItemSeeder)->examples() as [$department, $category, $name, $brand, $type, $contents, $unit]) {
            $packageKey = $keys->get([
                'package_type' => $type, 'contents' => $contents, 'contents_unit' => $unit,
            ]);
            $brandKey = $setup->normalizeBrand($brand)['brand_key'];
            $item = StockItem::query()
                ->where('brand_key', $brandKey)
                ->whereHas('packageSize', fn (Builder $packages) => $packages->where('definition_key', $packageKey))
                ->whereHas('product', fn (Builder $products) => $products->where('name', $name)
                    ->whereHas('category', fn (Builder $categories) => $categories->where('name', $category)
                        ->whereHas('department', fn (Builder $departments) => $departments->where('name', $department))))
                ->first();

            if ($item === null) {
                throw new RuntimeException("Run StockItemSeeder first: missing {$name}, ".($brand ?? 'unbranded').", {$type} {$contents} {$unit}.");
            }
            $items->push($item);
        }

        if ($items->count() !== 140 || $items->unique('id')->count() !== 140 || $items->pluck('product_id')->unique()->count() !== 100) {
            throw new RuntimeException('Demo stock definitions must contain 140 distinct choices covering 100 Products.');
        }

        return $items;
    }
}
