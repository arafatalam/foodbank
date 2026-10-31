<?php

namespace App\Actions\StockItems;

use App\Models\PackageSize;
use App\Models\Product;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeleteStockItem
{
    public function delete(StockItem $item, User $actor): void
    {
        Gate::forUser($actor)->authorize('delete', $item);

        DB::transaction(function () use ($item, $actor) {
            // Match the save operation's Product -> package -> item lock order.
            $product = Product::query()->whereKey($item->product_id)->lockForUpdate()->firstOrFail();
            PackageSize::query()->whereKey($item->package_size_id)->lockForUpdate()->firstOrFail();
            $current = StockItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $this->ensureIdentityUnchanged($item, $current);
            Gate::forUser($actor)->authorize('delete', $current);
            $current->delete();
            (new StockItemSetup)->syncProductUnit($product);
        }, attempts: 3);
    }

    private function ensureIdentityUnchanged(StockItem $original, StockItem $current): void
    {
        if ($original->product_id !== $current->product_id || $original->package_size_id !== $current->package_size_id) {
            throw ValidationException::withMessages(['stock_item' => 'This item changed. Reload the list before deleting.']);
        }
    }
}
