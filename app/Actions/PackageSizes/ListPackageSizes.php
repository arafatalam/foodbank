<?php

namespace App\Actions\PackageSizes;

use App\Models\PackageSize;
use Illuminate\Pagination\LengthAwarePaginator;

class ListPackageSizes
{
    /**
     * @param  array{search?: string}  $filters
     * @return LengthAwarePaginator<int, PackageSize>
     */
    public function get(array $filters = []): LengthAwarePaginator
    {
        $search = mb_strtolower(trim($filters['search'] ?? ''));

        // Keep reusable definitions in a predictable order, ten per page.
        return PackageSize::query()
            // Search all definitions before pagination; match readable names such as individual item.
            ->when($search !== '', fn ($query) => $query->whereRaw(
                "LOWER(REPLACE(package_type, '_', ' ')) LIKE ?",
                ['%'.$search.'%'],
            ))
            ->orderBy('package_type')
            ->orderBy('contents_unit')
            ->orderBy('contents')
            ->orderBy('id')
            ->paginate(10)
            ->appends($filters);
    }
}
