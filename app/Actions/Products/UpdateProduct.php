<?php

namespace App\Actions\Products;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateProduct
{
    /** @param array{department_id: int, category_id: int, name: string, confirm_duplicate_name?: bool} $data */
    public function update(Product $product, array $data, User $actor): Product
    {
        Gate::forUser($actor)->authorize('update', $product);

        return DB::transaction(function () use ($product, $data) {
            // Serialize writes in the destination category before checking duplicates.
            $category = Category::query()->whereKey($data['category_id'])
                ->where('department_id', $data['department_id'])->lockForUpdate()->first();
            if ($category === null) {
                throw ValidationException::withMessages(['category_id' => 'Select a category in the chosen department.']);
            }
            $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $name = trim($data['name']);
            if ($name === '') {
                throw ValidationException::withMessages(['name' => 'The name field is required.']);
            }
            (new CheckProductName)->check($name, $category->id, $data['confirm_duplicate_name'] ?? false, $product);
            // Ordinary edits change identity only and preserve the stored status.
            $values = ['name' => $name, 'category_id' => $category->id];
            $product->update($values);

            return $product;
        });
    }
}
