<?php

namespace App\Actions\Subcategories;

use App\Models\Subcategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ListSubcategories
{
    /**
     * @param  array{search?: string|null}  $filters
     * @return LengthAwarePaginator<int, Subcategory>
     */
    public function get(array $filters): LengthAwarePaginator
    {
        $search = $filters['search'] ?? '';

        // Load both parents together so each row can display its full path.
        $query = Subcategory::query()
            ->with(['category:id,name,department_id', 'category.department:id,name'])
            ->when($search !== '', function (Builder $query) use ($search) {
                // Match any of the three names inside a single grouped condition.
                $query->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhereHas('category', function (Builder $categoryQuery) use ($search) {
                            $categoryQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('category.department', function (Builder $departmentQuery) use ($search) {
                            $departmentQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('name')->orderBy('id');

        // Filter the full dataset first, then return ten rows and retain the search.
        return $query->paginate(10)->appends($filters);
    }
}
