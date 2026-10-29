<?php

namespace App\Http\Requests\Categories;

use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check permission for the category selected by the URL.
        $category = $this->route('category');

        return $category instanceof Category
            && ($this->user()?->can('update', $category) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Ignore this record when checking whether its name is already used.
        $category = $this->route('category');
        abort_unless($category instanceof Category, 404);

        return [
            // Require a department that still exists when the form is saved.
            'department_id' => ['bail', 'required', 'integer', 'exists:departments,id'],

            // Laravel trims outer spaces; MySQL's configured collation ignores case.
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')
                    ->where('department_id', $this->integer('department_id'))
                    ->ignore($category),
            ],
        ];
    }
}
