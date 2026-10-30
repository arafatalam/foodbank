<?php

namespace App\Actions\Products;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ListProducts
{
    /**
     * @param  array{search?: string|null, department_id?: int|string|null, category_id?: int|string|null, subcategory_id?: int|string|null, tag_ids?: list<int|string>|null, status?: string|null}  $filters
     * @return LengthAwarePaginator<int, array{id: int, name: string, brand: string|null, is_active: bool, tags: list<array{id: int, name: string}>}>
     */
    public function get(array $filters): LengthAwarePaginator
    {
        $search = $filters['search'] ?? '';

        // Load tags together instead of querying them for every row.
        $query = Product::query()->with([
            'tags' => fn ($query) => $query->orderBy('name')->orderBy('id'),
        ]);

        // Group the alternatives so every other filter applies to both kinds of match.
        $query->when($search !== '', function (Builder $query) use ($search) {
            $query->where(function (Builder $query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhereHas('tags', fn (Builder $tags) => $tags->where('name', 'like', "%{$search}%"));
            });
        });

        $query->when($filters['department_id'] ?? null, fn (Builder $query, $id) => $query->whereHas('subcategory.category', fn (Builder $category) => $category->where('department_id', $id)));
        $query->when($filters['category_id'] ?? null, fn (Builder $query, $id) => $query->whereHas('subcategory', fn (Builder $subcategory) => $subcategory->where('category_id', $id)));
        $query->when($filters['subcategory_id'] ?? null, fn (Builder $query, $id) => $query->where('subcategory_id', $id));
        $query->when($filters['tag_ids'] ?? [], fn (Builder $query, $ids) => $query->whereHas('tags', fn (Builder $tags) => $tags->whereKey($ids)));

        if (in_array($filters['status'] ?? 'all', ['active', 'inactive'], true)) {
            $query->where('is_active', $filters['status'] === 'active');
        }

        // Filter before pagination; EXISTS tag queries keep each product on one row.
        $products = $query->orderBy('name')->orderBy('id')->paginate(10)->appends($filters);

        return $products->through(fn (Product $product) => [
            'id' => $product->id,
            'name' => $product->name,
            'brand' => $product->brand,
            'is_active' => $product->is_active,
            'tags' => array_values($product->tags->map(fn ($tag) => ['id' => $tag->id, 'name' => $tag->name])->all()),
        ]);
    }
}
