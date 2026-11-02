<?php

namespace App\Actions\Receive;

use App\Actions\StockItems\StockItemSetup;
use App\Models\PackageSize;
use App\Models\Product;
use App\Models\StockEntry;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

// This action will handle saving one complete Receive receipt.
class CreateReceive
{
    /**
     * Takes: the StockItemSetup helper, supplied by Laravel.
     * Database: no queries or changes.
     * Stores: the helper for later compatibility checks; returns no value.
     * Example: $this->setup->ensureCompatiblePackage(...) uses this helper.
     */
    public function __construct(private readonly StockItemSetup $setup) {}

    /**
     * Save one complete receipt after checking permission and every item.
     * Takes: validated receipt data and the signed-in user.
     * Example: items [{stock_item_id: 12, quantity: 3}], notes "Donation".
     * Database: reads/locks products, package_sizes and stock_items; runs setup
     * compatibility lookups; inserts stock_entries and stock_movements;
     * updates stock_items balances. All changes share one transaction.
     * Returns: the saved StockEntry, for example receipt ID 50.
     * An error rolls back the transaction's database changes.
     *
     * @param array{
     *     notes?: string|null,
     *     items: array<int, array{stock_item_id: int, quantity: positive-int}>
     * } $data
     */
    public function create(array $data, User $actor): StockEntry
    {
        // Check whether this user may create a stock entry.
        Gate::forUser($actor)->authorize('create', StockEntry::class);

        // Keep the receipt's database changes together: all succeed or all roll back.
        return DB::transaction(function () use ($data, $actor): StockEntry {

            // Prepare the submitted IDs and the ID => quantity lookup.
            $stockItemIds = array_column($data['items'], 'stock_item_id');
            $quantities = array_column($data['items'], 'quantity', 'stock_item_id');

            // Discover connections, then lock Products, packages and stock items.
            $selectedItems = $this->findSelectedItems($stockItemIds);
            $products = $this->lockProducts($selectedItems);
            $packages = $this->lockPackages($selectedItems);
            $stockItems = $this->lockStockItems($stockItemIds);

            // Check every item before inserting the receipt.
            $this->validateReceiptItems($stockItemIds, $stockItems, $products, $packages, $quantities);

            $entry = $this->createEntry($data['notes'] ?? null, $actor);
            $this->saveMovements($entry, $stockItems, $quantities);

            // Pass the completed receipt back out of the transaction.
            return $entry;
        });
    }

    /**
     * Find the connections needed to prepare the locks.
     * Takes: stock item IDs, for example [12, 27].
     * Database: reads id, product_id and package_size_id from stock_items.
     * Does not lock or change rows. Missing IDs are absent from the result.
     * Returns: StockItem objects, for example item 12 -> Product 4, package 8.
     *
     * @param  array<int, int>  $stockItemIds
     * @return Collection<int, StockItem>
     */
    private function findSelectedItems(array $stockItemIds): Collection
    {
        return StockItem::query()
            ->whereKey($stockItemIds)
            ->get(['id', 'product_id', 'package_size_id']);
    }

    /**
     * Load and lock the connected Products inside the receipt transaction.
     * Takes: preliminary StockItem objects containing product_id.
     * Example: Product IDs [4, 4, 9] become [4, 9].
     * Database: reads and locks products rows in ID order; no updates.
     * Returns: Product objects, for example Product 4 (Rice) and Product 9 (Milk).
     * Run before the package and stock item locks.
     *
     * @param  Collection<int, StockItem>  $selectedItems
     * @return Collection<int, Product>
     */
    private function lockProducts(Collection $selectedItems): Collection
    {
        $productIds = $selectedItems->pluck('product_id')->unique()->values()->all();

        // Lock Products in ID order until this transaction finishes.
        return Product::query()
            ->whereKey($productIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Load and lock the connected package definitions inside the transaction.
     * Takes: preliminary StockItem objects containing package_size_id.
     * Example: package IDs [8, 8, 3] become [8, 3].
     * Database: reads and locks package_sizes rows in ID order; no updates.
     * Returns: PackageSize objects, for example package 8 (500 g bag).
     * Run after Product locks and before stock item locks.
     *
     * @param  Collection<int, StockItem>  $selectedItems
     * @return Collection<int, PackageSize>
     */
    private function lockPackages(Collection $selectedItems): Collection
    {
        $packageSizeIds = $selectedItems
            ->pluck('package_size_id')
            ->unique()
            ->values()
            ->all();

        // Lock package definitions after Products, in ID order.
        return PackageSize::query()
            ->whereKey($packageSizeIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Load and lock the current stock item records inside the transaction.
     * Takes: submitted stock item IDs, for example [12, 27].
     * Database: reads and locks stock_items rows in ID order; no updates.
     * Returns: full StockItem objects, for example item 12 with balance 10.
     * Missing IDs are absent; validateReceiptItems checks for them next.
     * Run after the Product and package locks.
     *
     * @param  array<int, int>  $stockItemIds
     * @return Collection<int, StockItem>
     */
    private function lockStockItems(array $stockItemIds): Collection
    {
        return StockItem::query()
            ->whereKey($stockItemIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Check every selected item before saving the receipt.
     * Takes: submitted IDs, locked models and the ID => received quantity lookup.
     * Example: IDs [12], quantities [12 => 3], item 12 with balance 10.
     * Checks missing items, locked connections, package compatibility and balance limits.
     * Database: checks loaded collections; the setup helper also reads
     * stock_product_settings and stock_items, and may inspect stock_movements history.
     * Does not insert or update rows.
     * Returns: nothing; throws a validation error if any check fails.
     *
     * @param  array<int, int>  $stockItemIds
     * @param  Collection<int, StockItem>  $stockItems
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, PackageSize>  $packages
     * @param  array<int, positive-int>  $quantities
     */
    private function validateReceiptItems(array $stockItemIds, Collection $stockItems, Collection $products, Collection $packages, array $quantities): void
    {
        // Stop if any selected stock item disappeared before we locked it.
        if ($stockItems->count() !== count($stockItemIds)) {
            throw ValidationException::withMessages([
                'items' => 'A selected stock item is no longer available. Refresh the page and try again.',
            ]);
        }

        // Check the current connections against the records we locked.
        foreach ($stockItems as $stockItem) {
            $productIsLocked = $products->contains('id', $stockItem->product_id);
            $packageIsLocked = $packages->contains('id', $stockItem->package_size_id);

            // Ask the user to reload if either connected record is outside our locks.
            if (! $productIsLocked || ! $packageIsLocked) {
                throw ValidationException::withMessages([
                    'items' => 'A selected stock item changed. Refresh the page and try again.',
                ]);

            }

            // Retrieve this item's Product and package from our locked collections.
            $product = $products->findOrFail($stockItem->product_id);
            $package = $packages->findOrFail($stockItem->package_size_id);

            // Apply the same compatibility rule used during stock item setup.
            $this->setup->ensureCompatiblePackage($product, $package, $stockItem);

            // Find the submitted quantity for this locked stock item.
            $quantity = (int) $quantities[$stockItem->id];

            // Stop if the new balance would exceed the database column's limit.
            if ($quantity > 4294967295 - $stockItem->available_quantity) {
                throw ValidationException::withMessages([
                    'items' => 'A received quantity would exceed the maximum stock balance.',
                ]);
            }
        }
    }

    /**
     * Insert the shared information for one Receive receipt inside the transaction.
     * Takes: optional notes and the signed-in user, e.g. "Donation" and user 7.
     * Database: inserts stock_entries with type receive, created_by 7 and the notes.
     * Laravel supplies the timestamps. Does not insert movements.
     * Returns: the saved StockEntry object, for example entry ID 50.
     */
    private function createEntry(?string $notes, User $actor): StockEntry
    {
        return StockEntry::create([
            'type' => 'receive',
            'created_by' => $actor->id,
            'notes' => $notes,
        ]);
    }

    /**
     * Insert movements and update balances inside the receipt transaction.
     * Takes: a saved entry, checked/locked stock items and ID => quantity lookup.
     * Example: entry 50, item 12 with balance 10, quantities [12 => 3].
     * Database: inserts stock_movements linked to entry 50 with quantity 3 and
     * resulting_balance 13; updates stock_items item 12 to available_quantity 13.
     * Repeats for every item. No additional lookup is needed.
     * Returns: nothing.
     *
     * @param  Collection<int, StockItem>  $stockItems
     * @param  array<int, positive-int>  $quantities
     */
    private function saveMovements(StockEntry $entry, Collection $stockItems, array $quantities): void
    {
        // Prepare the new balance for each checked stock item.
        foreach ($stockItems as $stockItem) {
            // Get the quantity received for this item.
            $quantity = (int) $quantities[$stockItem->id];

            // Add the received packages to the current balance.
            $resultingBalance = $stockItem->available_quantity + $quantity;

            // Insert a stock_movements row linked to this receipt.
            $entry->movements()->create([
                'stock_item_id' => $stockItem->id,
                'direction' => 'add',
                'quantity' => $quantity,
                'resulting_balance' => $resultingBalance,
            ]);

            // Save the new available balance on this stock item.
            $stockItem->available_quantity = $resultingBalance;
            $stockItem->save();
        }
    }
}
