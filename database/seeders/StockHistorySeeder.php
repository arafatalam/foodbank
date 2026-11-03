<?php

namespace Database\Seeders;

use App\Actions\Adjustment\CreateAdjustment;
use App\Actions\Distribution\CreateDistribution;
use App\Actions\PackageSizes\GetPackageSizeDefinitionKey;
use App\Actions\Receive\CreateReceive;
use App\Enums\RoleCode;
use App\Models\Household;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockHistorySeeder extends Seeder
{
    public function run(CreateReceive $receive, CreateDistribution $distribute, CreateAdjustment $adjust): void
    {
        // This optional sample seeder must never populate a production database.
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('StockHistorySeeder is only for local or testing environments.');
        }

        // Load existing eligible recorders without creating or changing user accounts.
        $managers = User::query()->where('is_active', true)->where('must_change_password', false)
            ->whereHas('role', fn (Builder $roles) => $roles->where('code', RoleCode::MANAGER->value))
            ->get();
        $volunteers = User::query()->where('is_active', true)->where('must_change_password', false)
            ->whereHas('role', fn (Builder $roles) => $roles->where('code', RoleCode::VOLUNTEER->value))
            ->get();

        if ($managers->isEmpty() || $volunteers->isEmpty()) {
            throw new RuntimeException('At least one active, setup-complete Manager and Volunteer are required. No history was changed.');
        }

        // Use the same fictional household supplied by HouseholdSeeder.
        $household = Household::query()->where('first_name', 'Amelia')->where('last_name', 'Bennett')->first();
        if ($household === null) {
            throw new RuntimeException('Run HouseholdSeeder first to create the sample households.');
        }

        // Resolve the exact choices from StockItemSeeder, never arbitrary database IDs.
        $examples = [
            ['Grains', 'Rice', 'Tilda', 'bag', '5', 'kg'],
            ['Bakery', 'Bread', 'Dempster’s', 'individual_item', '1', 'item'],
            ['Dairy and Eggs', 'Milk', 'Lactantia', 'carton', '1', 'litre'],
            ['Dairy and Eggs', 'Eggs', 'Burnbrae', 'box', '12', 'item'],
        ];
        $items = [];
        foreach ($examples as [$category, $name, $brand, $type, $contents, $unit]) {
            $packageKey = (new GetPackageSizeDefinitionKey)->get([
                'package_type' => $type, 'contents' => $contents, 'contents_unit' => $unit,
            ]);
            $item = StockItem::query()->with('product')->withCount('movements')
                ->where('brand_key', mb_strtolower($brand))
                ->whereHas('packageSize', fn (Builder $packages) => $packages->where('definition_key', $packageKey))
                ->whereHas('product', fn (Builder $products) => $products->where('name', $name)
                    ->whereHas('category', fn (Builder $categories) => $categories->where('name', $category)
                        ->whereHas('department', fn (Builder $departments) => $departments->where('name', 'Food'))))
                ->first();

            if ($item === null) {
                throw new RuntimeException("Missing stock choice: {$name}, {$brand}, {$type} {$contents} {$unit}. Run StockItemSeeder first. No history was changed.");
            }
            $items[] = $item;
        }
        foreach ($items as $item) {
            if ($item->movements_count > 10) {
                throw new RuntimeException("Stock item {$item->id} already has more than 10 movements. No history was changed.");
            }
        }

        // Save all four histories together, or roll back this entire run on failure.
        DB::transaction(function () use ($items, $managers, $volunteers, $household, $receive, $distribute, $adjust): void {
            foreach ($items as $item) {
                $existingCount = $item->movements()->count();
                for ($number = $existingCount + 1; $number <= 10; $number++) {
                    $item->refresh();

                    // Alternate roles, choosing a random existing person for each new entry.
                    $recorders = ($number - $existingCount) % 2 === 1 ? $managers : $volunteers;
                    $actor = $recorders->random();

                    // Begin with a receipt; then alternate distributions and adjustments.
                    // A receipt also replenishes any balance too small for a removal.
                    $step = ($number - $existingCount - 1) % 3;
                    if ($step === 0 || $item->available_quantity < 3) {
                        $receive->create([
                            'notes' => "Sample history: community donation (movement {$number}).",
                            'items' => [['stock_item_id' => $item->id, 'quantity' => 10]],
                        ], $actor);
                    } elseif ($step === 1) {
                        $distribute->create([
                            'household_id' => $household->id,
                            'items' => [['stock_item_id' => $item->id, 'quantity' => 3]],
                        ], $actor);
                    } else {
                        $adjust->create([
                            'stock_item_id' => $item->id,
                            'direction' => 'remove',
                            'quantity' => 1,
                            'reason' => "Sample history: damaged package found during a stock check (movement {$number}).",
                        ], $actor);
                    }
                }

                // Check the final count before committing any of this run's changes.
                if ($item->movements()->count() !== 10) {
                    throw new RuntimeException("Stock item {$item->id} did not finish with exactly 10 movements.");
                }
            }
        });

        foreach ($items as $item) {
            $item->refresh();
            $this->command?->info("Stock item #{$item->id} ({$item->product->name}): 10 movements; balance {$item->available_quantity} packages.");
        }
    }
}
