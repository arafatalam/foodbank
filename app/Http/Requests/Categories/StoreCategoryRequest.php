<?php

namespace App\Http\Requests\Categories;

use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Permit submission only when the Category policy allows creation.
        return $this->user()?->can('create', Category::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Require the ID of a department that exists in the database.
            'department_id' => ['bail', 'required', 'integer', 'exists:departments,id'],

            // Reject duplicate names only within the selected department.
            'name' => [
                'bail',
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')
                    ->where('department_id', $this->integer('department_id')),
            ],
        ];
    }
}
