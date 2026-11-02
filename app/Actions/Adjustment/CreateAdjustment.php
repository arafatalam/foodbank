<?php

namespace App\Actions\Adjustment;

use App\Actions\StockItems\StockItemSetup;
use App\Models\PackageSize;
use App\Models\Product;
use App\Models\StockEntry;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

// Keep the steps for saving one adjustment together.
class CreateAdjustment
{
    // Reuse the package compatibility checks already used by Receive.
    public function __construct(private readonly StockItemSetup $setup) {}

    /**
     * Coordinate one complete adjustment; errors roll back its database changes.
     *
     * @param  array{stock_item_id: int, quantity: int, direction: string, reason: string}  $data
     */
    public function create(array $data, User $actor): StockEntry
    {
        $this->authorizeCreation($actor);
        $this->validateDirection($data['direction']);
        $this->validateReason($data['reason']);

        // Keep all locks, checks and writes inside the same transaction.
        return DB::transaction(function () use ($data, $actor): StockEntry {
            $selectedItem = $this->findSelectedItem($data['stock_item_id']);

            // Follow Receive's lock order: Product, package, then stock item.
            $product = $this->lockProduct($selectedItem->product_id);
            $package = $this->lockPackage($selectedItem->package_size_id);
            $stockItem = $this->lockStockItem($selectedItem->id);

            $this->ensureConnectionsUnchanged($stockItem, $product, $package);
            $this->ensureCompatiblePackage($product, $package, $stockItem);

            $resultingBalance = $this->calculateResultingBalance($stockItem, $data['direction'], $data['quantity']);
            $entry = $this->createEntry($data['reason'], $actor);

            $this->createMovement($entry, $stockItem, $data['direction'], $data['quantity'], $resultingBalance);
            $this->saveBalance($stockItem, $resultingBalance);

            return $entry;
        });
    }

    private function authorizeCreation(User $actor): void
    {
        // Stop unless this user may create a stock entry.
        Gate::forUser($actor)->authorize('create', StockEntry::class);
    }

    private function validateDirection(string $direction): void
    {
        // Accept only the two supported stock changes.
        if (! in_array($direction, ['add', 'remove'], true)) {
            throw ValidationException::withMessages([
                'direction' => 'Choose Add or Remove.',
            ]);
        }
    }

    private function validateReason(string $reason): void
    {
        // Require an explanation that fits the entry's notes field.
        if (trim($reason) === '' || mb_strlen($reason) > 10000) {
            throw ValidationException::withMessages([
                'reason' => 'Enter an adjustment reason of no more than 10,000 characters.',
            ]);
        }
    }

    private function findSelectedItem(int $stockItemId): StockItem
    {
        // Discover the connections before locking the item.
        $stockItem = StockItem::query()->find($stockItemId);

        if ($stockItem === null) {
            throw ValidationException::withMessages([
                'stock_item_id' => 'This stock item is no longer available. Refresh the page and try again.',
            ]);
        }

        return $stockItem;
    }

    private function lockProduct(int $productId): Product
    {
        // Hold the Product row until the transaction finishes.
        $product = Product::query()->whereKey($productId)->lockForUpdate()->first();

        if ($product === null) {
            throw ValidationException::withMessages([
                'stock_item_id' => 'This stock item changed. Refresh the page and try again.',
            ]);
        }

        return $product;
    }

    private function lockPackage(int $packageId): PackageSize
    {
        // Hold the package row after obtaining the Product lock.
        $package = PackageSize::query()->whereKey($packageId)->lockForUpdate()->first();

        if ($package === null) {
            throw ValidationException::withMessages([
                'stock_item_id' => 'This stock item changed. Refresh the page and try again.',
            ]);
        }

        return $package;
    }

    private function lockStockItem(int $stockItemId): StockItem
    {
        // Reload and lock the latest balance after the other locks.
        $stockItem = StockItem::query()->whereKey($stockItemId)->lockForUpdate()->first();

        if ($stockItem === null) {
            throw ValidationException::withMessages([
                'stock_item_id' => 'This stock item changed. Refresh the page and try again.',
            ]);
        }

        return $stockItem;
    }

    private function ensureConnectionsUnchanged(StockItem $stockItem, Product $product, PackageSize $package): void
    {
        // Reject a setup change that happened while we waited for locks.
        if ($stockItem->product_id !== $product->id || $stockItem->package_size_id !== $package->id) {
            throw ValidationException::withMessages([
                'stock_item_id' => 'This stock item changed. Refresh the page and try again.',
            ]);
        }
    }

    private function ensureCompatiblePackage(Product $product, PackageSize $package, StockItem $stockItem): void
    {
        // Reuse setup's rule and show package errors under the item picker.
        try {
            $this->setup->ensureCompatiblePackage($product, $package, $stockItem);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages([
                'stock_item_id' => $exception->errors()['package_size_id'] ?? ['This stock item has an incompatible package.'],
            ]);
        }
    }

    /**
     * Calculate a valid new balance without writing to the database.
     *
     * @return non-negative-int
     */
    private function calculateResultingBalance(StockItem $stockItem, string $direction, int $quantity): int
    {
        // Reject quantities that cannot represent a valid package count.
        if ($quantity < 1 || $quantity > 4294967295) {
            throw ValidationException::withMessages([
                'quantity' => 'Enter a whole-package quantity between 1 and 4,294,967,295.',
            ]);
        }

        // Use the locked balance rather than the browser preview.
        $currentBalance = $stockItem->available_quantity;

        if ($direction === 'remove') {
            $resultingBalance = $currentBalance - $quantity;

            if ($resultingBalance < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'The quantity to remove exceeds the available stock.',
                ]);
            }

            return $resultingBalance;
        }

        if ($quantity > 4294967295 - $currentBalance) {
            throw ValidationException::withMessages([
                'quantity' => 'This adjustment would exceed the maximum stock balance.',
            ]);
        }

        return $currentBalance + $quantity;
    }

    private function createEntry(string $reason, User $actor): StockEntry
    {
        // Store shared context once; Laravel supplies the saved timestamp.
        return StockEntry::create([
            'type' => 'adjust',
            'created_by' => $actor->id,
            'notes' => $reason,
        ]);
    }

    private function createMovement(StockEntry $entry, StockItem $stockItem, string $direction, int $quantity, int $resultingBalance): void
    {
        // Add new history without changing any earlier movement records.
        $entry->movements()->create([
            'stock_item_id' => $stockItem->id,
            'direction' => $direction,
            'quantity' => $quantity,
            'resulting_balance' => $resultingBalance,
        ]);
    }

    /** @param non-negative-int $resultingBalance */
    private function saveBalance(StockItem $stockItem, int $resultingBalance): void
    {
        // Save the same balance recorded by the new movement.
        $stockItem->available_quantity = $resultingBalance;
        $stockItem->save();
    }
}
