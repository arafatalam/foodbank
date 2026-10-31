<?php

namespace App\Actions\Products;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateProduct
{
    /** @param array{department_id: int, category_id: int, name: string, confirm_duplicate_name?: bool} $data */
    public function create(array $data, User $actor): Product
    {
        Gate::forUser($actor)->authorize('create', Product::class);

        return DB::transaction(function () use ($data) {
            // Serialize writes in the destination category before checking duplicates.
            $category = Category::query()->whereKey($data['category_id'])
                ->where('department_id', $data['department_id'])->lockForUpdate()->first();
            if ($category === null) {
                throw ValidationException::withMessages(['category_id' => 'Select a category in the chosen department.']);
            }

            $name = trim($data['name']);
            if ($name === '') {
                throw ValidationException::withMessages(['name' => 'The name field is required.']);
            }
            (new CheckProductName)->check($name, $category->id, $data['confirm_duplicate_name'] ?? false);
            // Keep the stored status, but new Products are always active in version one.
            $values = ['name' => $name, 'category_id' => $category->id, 'is_active' => true];

            return Product::create($values);
        });
    }
}
