<?php

namespace App\Actions\Products;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

class CheckProductName
{
    public function check(string $name, int $categoryId, bool $confirmed, ?Product $product = null): void
    {
        // Include inactive records and ignore only the product being edited.
        $matches = Product::query()->with('category.department')
            ->when($product !== null, fn ($query) => $query->whereKeyNot($product->id))
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($name))])->orderBy('id')->get();
        if ($matches->contains('category_id', $categoryId)) {
            throw ValidationException::withMessages(['name' => 'A product with this name already exists in the selected category.']);
        }
        // Unchanged identity needs no repeat duplicate-name confirmation.
        $identityChanged = $product === null || $product->category_id !== $categoryId
            || mb_strtolower(trim($product->name)) !== mb_strtolower(trim($name));
        if ($identityChanged && $matches->isNotEmpty() && ! $confirmed) {
            $paths = $matches->map(fn (Product $match) => $match->category->department->name.' → '.$match->category->name.' → '.$match->name)->implode('; ');
            throw ValidationException::withMessages(['confirm_duplicate_name' => 'This name already exists elsewhere: '.$paths.'. Confirm below to continue, or change the name/category.']);
        }
    }
}
