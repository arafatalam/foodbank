<?php

namespace App\Http\Requests\Subcategories;

use App\Models\Subcategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubcategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Check permission again when the form is submitted.
        return $this->user()?->can('create', Subcategory::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'department_id' => ['bail', 'required', 'integer', 'exists:departments,id'],
            // A category must exist AND belong to the chosen department.
            'category_id' => [
                'bail', 'required', 'integer',
                Rule::exists('categories', 'id')->where('department_id', $this->integer('department_id')),
            ],
            // Names are unique within the chosen category, not the whole catalogue.
            'name' => [
                'bail', 'required', 'string', 'max:255',
                Rule::unique('subcategories', 'name')->where('category_id', $this->integer('category_id')),
            ],
        ];
    }
}
