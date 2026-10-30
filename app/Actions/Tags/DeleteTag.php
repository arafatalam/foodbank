<?php

namespace App\Actions\Tags;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeleteTag
{
    public function delete(Tag $tag, User $actor): void
    {
        // Protect this operation even when called outside the controller.
        Gate::forUser($actor)->authorize('delete', $tag);

        DB::transaction(function () use ($tag) {
            // Product saves lock tags too, so usage cannot change during deletion.
            $currentTag = Tag::query()->whereKey($tag->id)->lockForUpdate()->firstOrFail();

            // Include every linked product, regardless of its Active/Inactive status.
            if ($currentTag->products()->exists()) {
                throw ValidationException::withMessages([
                    'tag' => 'This tag cannot be deleted because a product still uses it. Remove it from all products, including inactive products, first.',
                ]);
            }

            // Delete only the unused shared tag; never detach product links here.
            $currentTag->delete();
        });
    }
}
