<?php

namespace App\Actions\Distribution;

use App\Actions\StockItems\StockItemSetup;
use App\Models\Household;
use App\Models\PackageSize;
use App\Models\Product;
use App\Models\StockEntry;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

// Handle saving one complete distribution.
class CreateDistribution
{
    // Ask Laravel to provide the existing stock-setup checker.
    public function __construct(
        private readonly StockItemSetup $setup,
    ) {}

    /**
     * @param array{
     *     household_id: int,
     *     items: list<array{stock_item_id: int, quantity: positive-int}>
     * } $data
     */
    public function create(array $data, User $actor): StockEntry
    {
        // Check this person's permission to record a stock entry.
        Gate::forUser($actor)->authorize('create', StockEntry::class);

        // Keep all distribution changes together: all succeed or all are undone.
        return DB::transaction(function () use ($data, $actor): StockEntry {
            // Collect the stock-item IDs we need to load.
            $stockItemIds = array_column($data['items'], 'stock_item_id');

            // Match each stock-item ID to its requested quantity.
            $quantities = array_column($data['items'], 'quantity', 'stock_item_id');

            // Discover the Product and package connected to each selected item.
            $selectedItems = $this->findSelectedItems($stockItemIds);

            // Lock Products before locking packages and stock items.
            $products = $this->lockProducts($selectedItems);

            // Hold package definitions before locking the stock-item balances.
            $packages = $this->lockPackages($selectedItems);

            // Lock stock items last and retrieve their current balances.
            $stockItems = $this->lockStockItems($stockItemIds);

            // Check all locked items before writing any distribution records.
            $this->validateDistributionItems($stockItemIds, $stockItems, $products, $packages, $quantities);

            // Confirm and hold the selected household.
            $household = $this->lockHousehold($data['household_id']);

            // Create the shared entry after all selected items and the household pass checks.
            $entry = $this->createEntry($household, $actor);

            // Save all removals and their updated stock balances.
            $this->saveMovements($entry, $stockItems, $quantities);

            // Pass the saved distribution entry back to the caller.
            return $entry;
        });
    }

    /**
     * @param  array<int, int>  $stockItemIds
     * @param  Collection<int, StockItem>  $stockItems
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, PackageSize>  $packages
     * @param  array<int, positive-int>  $quantities
     */
    private function validateDistributionItems(
        array $stockItemIds,
        Collection $stockItems,
        Collection $products,
        Collection $packages,
        array $quantities,
    ): void {
        // Confirm that every requested item was found before checking individual rows.
        $this->ensureAllItemsFound($stockItemIds, $stockItems);

        // Apply each safety check to every locked stock item.
        foreach ($stockItems as $stockItem) {
            $this->ensureConnectionsLocked($stockItem, $products, $packages);
            $this->ensureCompatiblePackage($stockItem, $products, $packages);
            $this->ensureQuantityAvailable($stockItem, (int) $quantities[$stockItem->id]);
        }
    }

    /**
     * @param  array<int, int>  $stockItemIds
     * @param  Collection<int, StockItem>  $stockItems
     */
    private function ensureAllItemsFound(array $stockItemIds, Collection $stockItems): void
    {
        // Stop if any requested stock item disappeared before we locked it.
        if ($stockItems->count() !== count($stockItemIds)) {
            throw ValidationException::withMessages([
                'items' => 'A selected stock item is no longer available. Refresh the page and try again.',
            ]);
        }

    }

    /**
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, PackageSize>  $packages
     */
    private function ensureConnectionsLocked(StockItem $stockItem, Collection $products, Collection $packages): void
    {
        // Confirm that its Product and package are among our locked records.
        $productIsLocked = $products->contains('id', $stockItem->product_id);
        $packageIsLocked = $packages->contains('id', $stockItem->package_size_id);

        // Stop if a connection now points outside our locks.
        if (! $productIsLocked || ! $packageIsLocked) {
            throw ValidationException::withMessages([
                'items' => 'A selected stock item changed. Refresh the page and try again.',
            ]);
        }
    }

    /**
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, PackageSize>  $packages
     */
    private function ensureCompatiblePackage(StockItem $stockItem, Collection $products, Collection $packages): void
    {
        // Get this item's Product and package from our locked collections.
        $product = $products->findOrFail($stockItem->product_id);
        $package = $packages->findOrFail($stockItem->package_size_id);

        // Apply the existing package-compatibility rule.
        $this->setup->ensureCompatiblePackage($product, $package, $stockItem);
    }

    private function ensureQuantityAvailable(StockItem $stockItem, int $quantity): void
    {
        // Require a positive count that fits the latest locked balance.
        if ($quantity < 1 || $quantity > $stockItem->available_quantity) {
            throw ValidationException::withMessages([
                'items' => 'A requested quantity is invalid or exceeds the available stock. Refresh the page and try again.',
            ]);
        }
    }

    private function lockHousehold(int $householdId): Household
    {
        // Find and hold the selected household until this transaction finishes.
        $household = Household::query()
            ->whereKey($householdId)
            ->lockForUpdate()
            ->first();

        // Stop if the household disappeared after the request was validated.
        if ($household === null) {
            throw ValidationException::withMessages([
                'household_id' => 'This household is no longer available. Refresh the page and select a household again.',
            ]);
        }

        return $household;
    }

    private function createEntry(Household $household, User $actor): StockEntry
    {
        // Record the shared details once for the whole distribution.
        return StockEntry::create([
            'type' => 'distribute',
            'household_id' => $household->id,
            'created_by' => $actor->id,
        ]);
    }

    /**
     * @param  Collection<int, StockItem>  $stockItems
     * @param  array<int, positive-int>  $quantities
     */
    private function saveMovements(
        StockEntry $entry,
        Collection $stockItems,
        array $quantities,
    ): void {
        // Process each checked and locked stock item.
        foreach ($stockItems as $stockItem) {
            // Subtract the requested packages from the locked balance.
            $quantity = (int) $quantities[$stockItem->id];
            $resultingBalance = $stockItem->available_quantity - $quantity;

            // Keep the saved balance nonnegative even if this method changes later.
            if ($resultingBalance < 0) {
                throw ValidationException::withMessages([
                    'items' => 'A requested quantity exceeds the available stock. Refresh the page and try again.',
                ]);
            }

            // Record this removal under the shared distribution entry.
            $entry->movements()->create([
                'stock_item_id' => $stockItem->id,
                'direction' => 'remove',
                'quantity' => $quantity,
                'resulting_balance' => $resultingBalance,
            ]);

            // Store the same resulting balance on the stock item.
            $stockItem->available_quantity = $resultingBalance;
            $stockItem->save();
        }
    }

    /**
     * @param  array<int, int>  $stockItemIds
     * @return Collection<int, StockItem>
     */
    private function findSelectedItems(array $stockItemIds): Collection
    {
        // Read the connections needed to prepare our database locks.
        return StockItem::query()
            ->whereKey($stockItemIds)
            ->get(['id', 'product_id', 'package_size_id']);
    }

    /**
     * @param  Collection<int, StockItem>  $selectedItems
     * @return Collection<int, Product>
     */
    private function lockProducts(Collection $selectedItems): Collection
    {
        // Collect each connected Product ID once.
        $productIds = $selectedItems->pluck('product_id')->unique()->values()->all();

        // Lock Products in ID order until the transaction finishes.
        return Product::query()
            ->whereKey($productIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * @param  Collection<int, StockItem>  $selectedItems
     * @return Collection<int, PackageSize>
     */
    private function lockPackages(Collection $selectedItems): Collection
    {
        // Collect each connected package definition ID once.
        $packageIds = $selectedItems
            ->pluck('package_size_id')
            ->unique()
            ->values()
            ->all();

        // Lock packages in ID order, after locking Products.
        return PackageSize::query()
            ->whereKey($packageIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * @param  array<int, int>  $stockItemIds
     * @return Collection<int, StockItem>
     */
    private function lockStockItems(array $stockItemIds): Collection
    {
        // Load the latest stock records and hold them until the transaction finishes.
        return StockItem::query()
            ->whereKey($stockItemIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }
}
