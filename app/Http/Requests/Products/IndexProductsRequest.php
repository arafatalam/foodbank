<?php

namespace App\Http\Requests\Products;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->when($this->filled('department_id'), fn ($rule) => $rule->where('department_id', $this->integer('department_id')))],
        ];
    }
}
