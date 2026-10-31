<?php

namespace App\Actions\StockItems;

use App\Models\StockItem;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ListStockItems
{
    /**
     * @param  array{search: string}  $filters
     * @return LengthAwarePaginator<int, array{id: int, product: string, path: string, brand: ?string, package: string, available_quantity: int, total_quantity: string, canUpdate: bool, canDelete: bool}>
     */
    public function get(array $filters, User $actor): LengthAwarePaginator
    {
        $query = StockItem::query()->with(['product.category.department', 'packageSize']);
        $this->applySearch($query, trim($filters['search']));

        return $query->orderBy('product_id')->orderBy('brand_key')->orderBy('package_size_id')->orderBy('id')
            ->paginate(10)->appends($filters)
            ->through(fn (StockItem $item): array => $this->row($item, $actor));
    }

    /** @param Builder<StockItem> $query */
    private function applySearch(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }
        // Group the OR conditions and search all matching records before pagination.
        $search = '%'.mb_strtolower($search).'%';
        $query->where(fn (Builder $query) => $query
            ->whereHas('product', fn (Builder $products) => $products->whereRaw('LOWER(name) LIKE ?', [$search]))
            ->orWhereRaw('LOWER(brand) LIKE ?', [$search]));
    }

    /** @return array{id: int, product: string, path: string, brand: ?string, package: string, available_quantity: int, total_quantity: string, canUpdate: bool, canDelete: bool} */
    private function row(StockItem $item, User $actor): array
    {
        return [
            'id' => $item->id,
            'product' => $item->product->name,
            'path' => $item->product->category->department->name.' / '.$item->product->category->name,
            'brand' => $item->brand,
            'package' => $item->packageSize->label(),
            'available_quantity' => $item->available_quantity,
            'total_quantity' => $this->totalQuantity($item),
            'canUpdate' => $actor->can('update', $item),
            'canDelete' => $actor->can('delete', $item),
        ];
    }

    private function totalQuantity(StockItem $item): string
    {
        // Calculate exactly before rounding; large balances must not overflow integers.
        $total = BigDecimal::of((string) $item->packageSize->contents)->multipliedBy($item->available_quantity);
        if (in_array($item->packageSize->contents_unit, ['g', 'ml'], true)) {
            $total = $total->dividedBy(1000, 6);
        }
        $display = (string) $total->toScale(3, RoundingMode::HalfUp);
        $display = rtrim(rtrim($display, '0'), '.');
        $unit = match ($item->packageSize->summaryUnit()) {
            'litre' => 'L',
            'item' => 'items',
            default => 'kg',
        };

        return $display.' '.$unit;
    }
}
