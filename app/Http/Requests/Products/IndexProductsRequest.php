<?php

namespace App\Http\Requests\Products;

use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route checks the Product policy before the controller runs.
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            // Reject conflicting parents even when the URL is entered manually.
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->when(
                $this->filled('department_id'), fn ($rule) => $rule->where('department_id', $this->integer('department_id')),
            )],
            'subcategory_id' => ['nullable', 'integer', Rule::exists('subcategories', 'id')->when(
                $this->filled('category_id'), fn ($rule) => $rule->where('category_id', $this->integer('category_id')),
            )->when($this->filled('department_id'), fn ($rule) => $rule->where(fn ($query) => $query->whereIn('category_id',
                Category::query()->select('id')->where('department_id', $this->integer('department_id')),
            )))],
            'tag_ids' => ['nullable', 'array', 'list'],
            'tag_ids.*' => ['required', 'integer', 'distinct', 'exists:tags,id'],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
        ];
    }
}
