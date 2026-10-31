<?php

namespace App\Actions\StockItems;

use App\Models\StockItem;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateStockItem
{
    public function __construct(private readonly StockItemSetup $setup) {}

    /** @param array{product_id: int, package_size_id: int, brand: ?string} $data */
    public function update(StockItem $item, array $data, User $actor): StockItem
    {
        Gate::forUser($actor)->authorize('update', $item);
        $brand = $this->setup->normalizeBrand($data['brand']);

        try {
            return DB::transaction(fn () => $this->updateWithinTransaction($item, $data, $brand, $actor), attempts: 3);
        } catch (UniqueConstraintViolationException $exception) {
            $this->setup->ensureCombinationAvailable($data, $brand['brand_key'], $item->id);

            throw $exception;
        }
    }

    /**
     * @param  array{product_id: int, package_size_id: int, brand: ?string}  $data
     * @param  array{brand: ?string, brand_key: string}  $brand
     */
    private function updateWithinTransaction(StockItem $item, array $data, array $brand, User $actor): StockItem
    {
        $products = $this->setup->lockProducts($data['product_id'], $item);
        $packages = $this->setup->lockPackages($data['package_size_id'], $item);
        $current = $this->lockCurrentItem($item, $actor);
        $this->ensureIdentityEditable($current, $data);
        $this->setup->ensureCombinationAvailable($data, $brand['brand_key'], $current->id);
        $this->setup->ensureCompatiblePackage(
            $products->findOrFail($data['product_id']), $packages->findOrFail($data['package_size_id']), $current,
        );

        $current->update([...$data, ...$brand]);
        $this->setup->syncProductUnits($products);

        return $current;
    }

    private function lockCurrentItem(StockItem $item, User $actor): StockItem
    {
        $current = StockItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
        Gate::forUser($actor)->authorize('update', $current);
        if ($current->product_id !== $item->product_id || $current->package_size_id !== $item->package_size_id) {
            throw ValidationException::withMessages(['product_id' => 'This item changed. Reload the page before saving.']);
        }

        return $current;
    }

    /** @param array{product_id: int, package_size_id: int, brand: ?string} $data */
    private function ensureIdentityEditable(StockItem $item, array $data): void
    {
        if ($item->hasMovements()
            && ($item->product_id !== $data['product_id'] || $item->package_size_id !== $data['package_size_id'])) {
            throw ValidationException::withMessages([
                'package_size_id' => 'Product and package size cannot change after a stock movement.',
            ]);
        }
    }
}
