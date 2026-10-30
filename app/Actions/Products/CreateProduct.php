<?php

namespace App\Actions\Products;

use App\Models\Category;
use App\Models\Product;
use App\Models\Subcategory;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateProduct
{
    /**
     * @param  array{department_id: int|string, category_id: int|string, subcategory_id: int|string, name: string, brand?: string|null, is_active: bool|int|string, tag_ids?: list<int|string>|null}  $data
     */
    public function create(array $data, User $actor): Product
    {
        Gate::forUser($actor)->authorize('create', Product::class);

        // Save the product and all tag links together, or roll back both.
        return DB::transaction(function () use ($data) {
            // Keep the chosen path consistent until the product has been saved.
            $category = Category::query()->whereKey($data['category_id'])
                ->where('department_id', $data['department_id'])->sharedLock()->first();
            if ($category === null) {
                throw ValidationException::withMessages(['category_id' => 'Select a category in the chosen department.']);
            }

            // Serialize saves in this Subcategory before checking for duplicates.
            $subcategory = Subcategory::query()->whereKey($data['subcategory_id'])
                ->where('category_id', $category->id)->lockForUpdate()->first();
            if ($subcategory === null) {
                throw ValidationException::withMessages(['subcategory_id' => 'Select a subcategory in the chosen category.']);
            }

            $name = trim($data['name']);
            $brand = trim($data['brand'] ?? '');

            // Include inactive products and treat a blank brand as unbranded.
            $duplicate = Product::query()->where('subcategory_id', $subcategory->id)
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])
                ->whereRaw("LOWER(TRIM(COALESCE(brand, ''))) = ?", [mb_strtolower($brand)])
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['name' => 'A product with this name and brand already exists in the selected subcategory.']);
            }

            // Recheck and hold the selected tags in case they changed after validation.
            $tagIds = $data['tag_ids'] ?? [];
            $tags = Tag::query()->whereKey($tagIds)->orderBy('id')->sharedLock()->get(['id']);
            if ($tags->count() !== count($tagIds)) {
                throw ValidationException::withMessages(['tag_ids' => 'One or more selected tags are no longer available.']);
            }

            // Department/Category are derived through Subcategory, not stored twice.
            $product = Product::create([
                'name' => $name,
                'brand' => $brand === '' ? null : $brand,
                'subcategory_id' => $subcategory->id,
                'is_active' => $data['is_active'],
            ]);
            $product->tags()->attach($tags->modelKeys());

            return $product;
        });
    }
}
