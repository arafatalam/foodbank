<?php

namespace App\Actions\Products;

use App\Models\Category;
use App\Models\Product;
use App\Models\Subcategory;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateProduct
{
    /**
     * @param  array{department_id: int|string, category_id: int|string, subcategory_id: int|string, name: string, brand?: string|null, is_active: bool|int|string, tag_ids?: list<int|string>|null}  $data
     */
    public function update(Product $product, array $data, User $actor): Product
    {
        Gate::forUser($actor)->authorize('update', $product);

        // Save the changes and tag links together, or roll back both.
        return DB::transaction(function () use ($product, $data) {
            $category = $this->findAndLockCategory($data['category_id'], $data['department_id']);
            $subcategory = $this->findAndLockSubcategory($data['subcategory_id'], $category);

            // Reload and lock the saved product so concurrent edits wait their turn.
            $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            $name = trim($data['name']);
            $brand = trim($data['brand'] ?? '');

            $this->ensureProductIsUnique($product, $subcategory, $name, $brand);
            $tags = $this->findAndLockTags($data['tag_ids'] ?? []);

            // Department/Category are derived through Subcategory, not stored twice.
            $product->update([
                'name' => $name,
                'brand' => $brand === '' ? null : $brand,
                'subcategory_id' => $subcategory->id,
                'is_active' => $data['is_active'],
            ]);
            // Add newly selected tags and remove unchecked links without deleting tags.
            $product->tags()->sync($tags->modelKeys());

            return $product;
        });
    }

    private function findAndLockCategory(int|string $categoryId, int|string $departmentId): Category
    {
        // Keep the category in the chosen department until saving finishes.
        $category = Category::query()->whereKey($categoryId)
            ->where('department_id', $departmentId)->sharedLock()->first();

        if ($category === null) {
            throw ValidationException::withMessages(['category_id' => 'Select a category in the chosen department.']);
        }

        return $category;
    }

    private function findAndLockSubcategory(int|string $subcategoryId, Category $category): Subcategory
    {
        // Make saves in this subcategory wait their turn before checking duplicates.
        $subcategory = Subcategory::query()->whereKey($subcategoryId)
            ->where('category_id', $category->id)->lockForUpdate()->first();

        if ($subcategory === null) {
            throw ValidationException::withMessages(['subcategory_id' => 'Select a subcategory in the chosen category.']);
        }

        return $subcategory;
    }

    private function ensureProductIsUnique(Product $product, Subcategory $subcategory, string $name, string $brand): void
    {
        // Ignore this product; include inactive products and treat blank brands alike.
        $duplicate = Product::query()->whereKeyNot($product->id)
            ->where('subcategory_id', $subcategory->id)
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])
            ->whereRaw("LOWER(TRIM(COALESCE(brand, ''))) = ?", [mb_strtolower($brand)])
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['name' => 'A product with this name and brand already exists in the selected subcategory.']);
        }
    }

    /**
     * @param  list<int|string>  $tagIds
     * @return Collection<int, Tag>
     */
    private function findAndLockTags(array $tagIds): Collection
    {
        // Recheck and hold the selected tags in case they changed after validation.
        $tags = Tag::query()->whereKey($tagIds)->orderBy('id')->sharedLock()->get(['id']);

        if ($tags->count() !== count($tagIds)) {
            throw ValidationException::withMessages(['tag_ids' => 'One or more selected tags are no longer available.']);
        }

        return $tags;
    }
}
