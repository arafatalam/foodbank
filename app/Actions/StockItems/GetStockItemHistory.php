<?php

namespace App\Actions\StockItems;

use App\Models\StockItem;
use App\Models\StockMovement;
use Illuminate\Pagination\LengthAwarePaginator;

class GetStockItemHistory
{
    /**
     * @return array{
     *     stockItem: array{id: int, product: string, path: string, brand: string, package: string, available_quantity: int},
     *     backLink: array{label: string, href: string},
     *     movements: LengthAwarePaginator<int, array<string, mixed>>
     * }
     */
    public function get(StockItem $stockItem, string $origin = 'stock-items'): array
    {
        // Follow the Product's category, then that category's department.
        $category = $stockItem->product->category;
        $department = $category->department;

        // Prepare the selected item's basic details.
        $stockItemData = [
            'id' => $stockItem->id,
            'product' => $stockItem->product->name,
            'path' => $department->name.' / '.$category->name,
            'brand' => $stockItem->brand ?? 'Unbranded',
            'package' => $stockItem->packageSize->label(),
            'available_quantity' => $stockItem->available_quantity,
        ];

        // Ask this action's helper to prepare the movement page.
        $movements = $this->getMovements($stockItem, $origin);

        // Return both sets of prepared data as page props.
        return [
            'stockItem' => $stockItemData,
            // Use fixed destinations rather than accepting an arbitrary return URL.
            'backLink' => $origin === 'product-totals'
                ? ['label' => 'Back to Product totals', 'href' => '/product-totals']
                : ['label' => 'Back to stock items', 'href' => '/stock-items'],
            'movements' => $movements,
        ];
    }

    /**
     * Retrieve the movement history for a stock item. Call this from get().
     * Loads related records, orders newest first, and paginates ten movements
     * per page. Uses formatMovement() internally to prepare each result for Vue.
     * Returns the prepared movement page together with its pagination information.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function getMovements(StockItem $stockItem, string $origin): LengthAwarePaginator
    {
        // Retrieve only this item's movements, newest first, ten per page.
        return $stockItem->movements()
            ->with(['stockEntry.createdBy', 'stockEntry.household'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10)
            // Keep the return destination when navigating through movement pages.
            ->appends(['from' => $origin])
            // Prepare each movement using the dedicated formatting helper.
            ->through(fn (StockMovement $movement): array => $this->formatMovement($movement));
    }

    /**
     * Prepare the Vue fields for ONE movement already retrieved by getMovements().
     * Expects stockEntry.createdBy and stockEntry.household to be loaded.
     * This only formats a single record; it does not retrieve, order, or paginate
     * history. To obtain a stock item's history page, call getMovements() instead.
     *
     * @return array<string, mixed>
     */
    private function formatMovement(StockMovement $movement): array
    {
        // Select the values this movement will expose to Vue.
        return [
            'id' => $movement->id,
            'type' => $movement->stockEntry->type,
            'direction' => $movement->direction,
            'quantity' => $movement->quantity,
            'resulting_balance' => $movement->resulting_balance,
            'recorded_at' => $movement->stockEntry->created_at->toIso8601String(),
            'recorder' => $movement->stockEntry->createdBy->first_name
                .' '.$movement->stockEntry->createdBy->last_name,
            'notes' => $movement->stockEntry->type === 'receive'
                ? $movement->stockEntry->notes
                : null,
            // Include the saved explanation only for an Adjustment entry.
            'reason' => $movement->stockEntry->type === 'adjust'
                ? $movement->stockEntry->notes
                : null,
            // Include the household's name when this entry has a household.
            'household' => $movement->stockEntry->household !== null
                ? $movement->stockEntry->household->first_name
                .' '.$movement->stockEntry->household->last_name
                : null,
        ];
    }
}
