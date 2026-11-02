<?php

namespace App\Actions\Receive;

use App\Models\StockItem;
use Illuminate\Support\Collection;

class GetReceiveStockItems
{
    /**
     * @return Collection<int, array{id: int, product_name: string, product_path: string, brand: string, package_label: string}>
     */
    public function get(): Collection
    {
        // Fetch the stock items with the related details needed by the picker.
        $items = StockItem::query()
            ->with(['product.category.department', 'packageSize'])
            ->orderBy('id')
            ->get();

        // Prepare the same fields the Receive page already expects.
        return $items->map(function (StockItem $item): array {
            return [
                'id' => $item->id,
                'product_name' => $item->product->name,
                // Include the category path to distinguish similar products.
                'product_path' => $item->product->category->department->name
                    .' / '
                    .$item->product->category->name,
                'brand' => $item->brand ?? 'Unbranded',
                'package_label' => $item->packageSize->label(),
            ];
        });
    }
}
