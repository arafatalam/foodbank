<?php

namespace App\Http\Requests\Subcategories;

use App\Models\Subcategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubcategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check permission for the subcategory selected by the URL.
        $subcategory = $this->route('subcategory');

        return $subcategory instanceof Subcategory
            && ($this->user()?->can('update', $subcategory) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Ignore this record when checking whether its name is already used.
        $subcategory = $this->route('subcategory');
        abort_unless($subcategory instanceof Subcategory, 404);

        return [
            'department_id' => ['bail', 'required', 'integer', 'exists:departments,id'],
            // The selected category must belong to the selected department.
            'category_id' => [
                'bail', 'required', 'integer',
                Rule::exists('categories', 'id')->where('department_id', $this->integer('department_id')),
            ],
            // Laravel trims outer spaces; MySQL's configured collation ignores case.
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('subcategories', 'name')->where('category_id', $this->integer('category_id'))->ignore($subcategory),
            ],
        ];
    }
}
