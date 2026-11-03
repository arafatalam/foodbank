<?php

namespace App\Actions\ProductTotals;

use App\Models\StockItem;
use App\Models\StockProductSetting;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ListProductTotals
{
    /**
     * @param  array{search: string}  $filters
     * @return LengthAwarePaginator<int, array{id: int, product: string, path: string, total_quantity: string, stock_items: list<array{id: int, brand: string, package: string, available_quantity: int, total_quantity: string, canViewHistory: bool}>}>
     */
    public function get(array $filters, User $actor): LengthAwarePaginator
    {
        $search = trim($filters['search']);

        return StockProductSetting::query()
            // Include only Products that have at least one stock item.
            ->whereHas('stockItems')
            // Match Product names before pagination; keep all their stock items.
            ->when($search !== '', fn (Builder $query) => $query
                ->whereHas('product', fn (Builder $products) => $products
                    ->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($search).'%'])))
            // Load the names and package details needed for each total.
            ->with([
                'product.category.department',
                'stockItems.packageSize',
            ])
            // Keep Products in a consistent order across pages.
            ->orderBy('product_id')
            // Show ten Products per page.
            ->paginate(10)
            // Keep the current search when moving between result pages.
            ->appends($filters)
            // Prepare each Product's information for the totals page.
            ->through(fn (StockProductSetting $setting): array => $this->formatProductTotal($setting, $actor));
    }

    /**
     * @return array{id: int, product: string, path: string, total_quantity: string, stock_items: list<array{id: int, brand: string, package: string, available_quantity: int, total_quantity: string, canViewHistory: bool}>}
     */
    private function formatProductTotal(StockProductSetting $setting, User $actor): array
    {
        // Follow the relationships already loaded by get().
        $product = $setting->product;
        $category = $product->category;
        $department = $category->department;

        // Calculate the combined quantity before rounding it for display.
        $total = $this->calculateTotalQuantity($setting);

        // Include every stock item, even when its available count is zero.
        $stockItems = array_values($setting->stockItems->map(fn (StockItem $stockItem): array => [
            'id' => $stockItem->id,
            'brand' => $stockItem->brand ?? 'Unbranded',
            'package' => $stockItem->packageSize->label(),
            'available_quantity' => $stockItem->available_quantity,
            // Use the same per-item permission as the Stock items page.
            'canViewHistory' => $actor->can('viewHistory', $stockItem),
            'total_quantity' => $this->formatQuantity($this->calculateStockItemQuantity($stockItem), $setting->summary_unit),
        ])->all());

        return [
            'id' => $product->id,
            'product' => $product->name,
            'path' => $department->name.' / '.$category->name,
            'total_quantity' => $this->formatQuantity($total, $setting->summary_unit),
            'stock_items' => $stockItems,
        ];
    }

    private function formatQuantity(BigDecimal $quantity, string $summaryUnit): string
    {
        // Round only for display, then remove unnecessary decimal zeros.
        $display = (string) $quantity->toScale(3, RoundingMode::HalfUp);
        $display = rtrim(rtrim($display, '0'), '.');

        $unit = match ($summaryUnit) {
            'kg' => 'kg',
            'litre' => 'L',
            'item' => 'items',
            default => throw new \UnexpectedValueException(
                'This Product has no supported stock summary unit.'
            ),
        };

        return $display.' '.$unit;
    }

    private function calculateTotalQuantity(StockProductSetting $setting): BigDecimal
    {
        // Begin with zero before adding this Product's stock quantities.
        $total = BigDecimal::of('0');

        foreach ($setting->stockItems as $stockItem) {
            // Sum exact quantities, before any display rounding.
            $total = $total->plus($this->calculateStockItemQuantity($stockItem));
        }

        return $total;
    }

    private function calculateStockItemQuantity(StockItem $stockItem): BigDecimal
    {
        // Multiply one package's contents by its available package count.
        $package = $stockItem->packageSize;
        $quantity = BigDecimal::of($package->contents)
            ->multipliedBy($stockItem->available_quantity);

        // Convert grams to kilograms, or millilitres to litres.
        if (in_array($package->contents_unit, ['g', 'ml'], true)) {
            $quantity = $quantity->dividedBy(1000, 6);
        }

        return $quantity;
    }
}
