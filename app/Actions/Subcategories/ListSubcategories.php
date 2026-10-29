<?php

namespace App\Actions\Subcategories;

use App\Models\Subcategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ListSubcategories
{
    /**
     * @param  array{search?: string|null}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function get(array $filters, User $actor): LengthAwarePaginator
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
        $subcategories = $query->paginate(10)->appends($filters);

        // Follow ListUsers: prepare each row with its actual update permission.
        return $subcategories->through(fn (Subcategory $subcategory) => [
            ...$subcategory->only(['id', 'name']),
            'category' => [
                ...$subcategory->category->only(['id', 'name']),
                'department' => $subcategory->category->department->only(['id', 'name']),
            ],
            'canEdit' => $actor->can('update', $subcategory),
        ]);
    }
}
