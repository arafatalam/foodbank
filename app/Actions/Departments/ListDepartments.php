<?php

namespace App\Actions\Departments;

use App\Models\Department;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ListDepartments
{
    /**
     * @param  array{search?: string|null}  $filters
     * @return LengthAwarePaginator<int, Department>
     */
    public function get(array $filters): LengthAwarePaginator
    {
        // Use empty text when no search was supplied.
        $search = $filters['search'] ?? '';

        // Build the matching department list in alphabetical order.
        $query = Department::query()
            ->when($search !== '', function (Builder $query) use ($search) {
                // Match text anywhere in the department name.
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name');

        // Return ten results per page and retain validated search filters.
        return $query->paginate(10)->appends($filters);
    }
}
