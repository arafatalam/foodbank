<?php

namespace App\Http\Requests\Products;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Check the saved product selected by the URL, not a submitted ID.
        $product = $this->route('product');

        return $product instanceof Product
            && ($this->user()?->can('update', $product) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'department_id' => ['bail', 'required', 'integer', 'exists:departments,id'],
            // Each selected child must belong to its selected parent.
            'category_id' => ['bail', 'required', 'integer', Rule::exists('categories', 'id')->where('department_id', $this->integer('department_id'))],
            'subcategory_id' => ['bail', 'required', 'integer', Rule::exists('subcategories', 'id')->where('category_id', $this->integer('category_id'))],
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            // Accept only distinct IDs of tags that still exist.
            'tag_ids' => ['nullable', 'array', 'list'],
            'tag_ids.*' => ['bail', 'required', 'integer', 'distinct', 'exists:tags,id'],
        ];
    }

    /**
     * @return array<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                // Check duplicates only after all field values pass their rules.
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $product = $this->route('product');
                abort_unless($product instanceof Product, 404);

                // Match Create's comparison, including inactive and unbranded products.
                $name = trim($this->string('name')->toString());
                $brand = $this->filled('brand') ? trim($this->string('brand')->toString()) : '';
                $duplicate = Product::query()
                    ->whereKeyNot($product->id)
                    ->where('subcategory_id', $this->integer('subcategory_id'))
                    ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])
                    ->whereRaw("LOWER(TRIM(COALESCE(brand, ''))) = ?", [mb_strtolower($brand)])
                    ->exists();

                if ($duplicate) {
                    $validator->errors()->add('name', 'A product with this name and brand already exists in the selected subcategory.');
                }
            },
        ];
    }

    /**
     * @return array{department_id: int, category_id: int, subcategory_id: int, name: string, brand: string|null, is_active: bool, tag_ids: list<int>}
     */
    public function productData(): array
    {
        // Prepare the already-validated form values with predictable PHP types.
        return [
            'department_id' => $this->integer('department_id'),
            'category_id' => $this->integer('category_id'),
            'subcategory_id' => $this->integer('subcategory_id'),
            'name' => $this->string('name')->toString(),
            'brand' => $this->filled('brand') ? $this->string('brand')->toString() : null,
            'is_active' => $this->boolean('is_active'),
            'tag_ids' => array_values(array_map(fn ($id): int => (int) $id, $this->array('tag_ids'))),
        ];
    }
}
