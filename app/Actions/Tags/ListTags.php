<?php

namespace App\Actions\Tags;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ListTags
{
    /**
     * @param  array{search?: string|null}  $filters
     * @return LengthAwarePaginator<int, array{id: int, name: string, canEdit: bool, canDelete: bool}>
     */
    public function get(array $filters, User $actor): LengthAwarePaginator
    {
        $search = $filters['search'] ?? '';

        // Search all names before selecting this page's ten results.
        $query = Tag::query()
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name')->orderBy('id');

        $tags = $query->paginate(10)->appends($filters);

        // Follow the Subcategory list's per-record edit permission pattern.
        return $tags->through(fn (Tag $tag) => [
            'id' => $tag->id,
            'name' => $tag->name,
            'canEdit' => $actor->can('update', $tag),
            'canDelete' => $actor->can('delete', $tag),
        ]);
    }
}
