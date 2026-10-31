<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class ListProducts
{
    /**
     * @param  array{search?: string|null, department_id?: int|string|null, category_id?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, array{id: int, name: string, category: string, department: string, canUpdate: bool}>
     */
    public function get(array $filters, User $actor): LengthAwarePaginator
    {
        // Include every Product; the retained status field does not filter the catalogue.
        $query = Product::query()->with('category.department');
        $search = $filters['search'] ?? '';
        $query->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"));
        $query->when($filters['department_id'] ?? null, fn ($query, $id) => $query->whereHas('category', fn ($category) => $category->where('department_id', $id)));
        $query->when($filters['category_id'] ?? null, fn ($query, $id) => $query->where('category_id', $id));

        return $query->orderBy('name')->orderBy('id')->paginate(10)->appends($filters)->through(fn (Product $product) => [
            'id' => $product->id, 'name' => $product->name,
            'category' => $product->category->name, 'department' => $product->category->department->name,
            'canUpdate' => $actor->can('update', $product),
        ]);
    }
}
