<?php

namespace App\Http\Requests\Products;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Product::class) ?? false;
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
