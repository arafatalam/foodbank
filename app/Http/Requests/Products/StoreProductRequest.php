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

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'department_id' => ['bail', 'required', 'integer', 'exists:departments,id'],
            'category_id' => ['bail', 'required', 'integer', Rule::exists('categories', 'id')->where('department_id', $this->integer('department_id'))],
            'name' => ['required', 'string', 'max:255'],
            'confirm_duplicate_name' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array{department_id: int, category_id: int, name: string, confirm_duplicate_name: bool} */
    // Only copy editable fields; a submitted status is deliberately ignored.
    public function productData(): array
    {
        return [
            'department_id' => $this->integer('department_id'),
            'category_id' => $this->integer('category_id'),
            'name' => trim($this->string('name')->toString()),
            'confirm_duplicate_name' => $this->boolean('confirm_duplicate_name'),
        ];
    }
}
