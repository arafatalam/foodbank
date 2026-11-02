<?php

namespace App\Actions\Adjustment;

use App\Models\StockItem;
use Illuminate\Support\Collection;

class GetAdjustmentStockItems
{
    /**
     * @return Collection<int, array{id: int, product_name: string, product_path: string, brand: string, package_label: string, available_quantity: int}>
     */
    public function get(): Collection
    {
        // Fetch each stock item together with its product path and package.
        $items = StockItem::query()
            ->with(['product.category.department', 'packageSize'])
            ->orderBy('id')
            ->get();

        // Prepare one small set of display values for each stock item.
        return $items->map(function (StockItem $item): array {
            return [
                'id' => $item->id,
                'product_name' => $item->product->name,

                // Distinguish products that share the same name.
                'product_path' => $item->product->category->department->name
                    .' / '
                    .$item->product->category->name,

                // Show a readable label when no brand was entered.
                'brand' => $item->brand ?? 'Unbranded',
                'package_label' => $item->packageSize->label(),
                'available_quantity' => $item->available_quantity,
            ];
        });
    }
}
