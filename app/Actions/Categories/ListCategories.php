<?php

namespace App\Actions\Categories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ListCategories
{
    /**
     * @param  array{search?: string|null}  $filters
     * @return LengthAwarePaginator<int, Category>
     */
    public function get(array $filters): LengthAwarePaginator
    {
        // Use empty text when no search was supplied.
        $search = $filters['search'] ?? '';

        // Load departments together to avoid a separate query for every row.
        $query = Category::query()
            ->with('department:id,name')
            ->when($search !== '', function (Builder $query) use ($search) {
                // Keep the two possible name matches in one grouped condition.
                $query->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhereHas('department', function (Builder $departmentQuery) use ($search) {
                            $departmentQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('name')
            ->orderBy('id');

        // Search the full dataset before splitting it into ten-row pages.
        return $query->paginate(10)->appends($filters);
    }
}
