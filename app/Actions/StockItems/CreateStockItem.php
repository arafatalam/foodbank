<?php

namespace App\Actions\StockItems;

use App\Models\StockItem;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateStockItem
{
    public function __construct(private readonly StockItemSetup $setup) {}

    /** @param array{product_id: int, package_size_id: int, brand: ?string} $data */
    public function create(array $data, User $actor): StockItem
    {
        Gate::forUser($actor)->authorize('create', StockItem::class);
        $brand = $this->setup->normalizeBrand($data['brand']);

        try {
            return DB::transaction(fn () => $this->createWithinTransaction($data, $brand), attempts: 3);
        } catch (UniqueConstraintViolationException $exception) {
            // Explain a concurrent duplicate after the transaction has rolled back.
            $this->setup->ensureCombinationAvailable($data, $brand['brand_key']);

            throw $exception;
        }
    }

    /**
     * @param  array{product_id: int, package_size_id: int, brand: ?string}  $data
     * @param  array{brand: ?string, brand_key: string}  $brand
     */
    private function createWithinTransaction(array $data, array $brand): StockItem
    {
        // Match editing/deletion: lock Products first, then package definitions.
        $products = $this->setup->lockProducts($data['product_id']);
        $packages = $this->setup->lockPackages($data['package_size_id']);
        $product = $products->findOrFail($data['product_id']);
        $package = $packages->findOrFail($data['package_size_id']);
        $this->setup->ensureCombinationAvailable($data, $brand['brand_key']);
        $this->setup->ensureCompatiblePackage($product, $package);

        $item = StockItem::create([...$data, ...$brand]);
        $this->setup->syncProductUnit($product);

        return $item;
    }
}
