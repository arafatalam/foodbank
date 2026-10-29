<?php

namespace App\Http\Requests\Departments;

use App\Models\Department;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check permission for the department selected by the URL.
        $department = $this->route('department');

        return $department instanceof Department
            && ($this->user()?->can('update', $department) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Ignore this record when checking whether its name is already used.
        $department = $this->route('department');
        abort_unless($department instanceof Department, 404);

        return [
            // Laravel trims outer spaces; MySQL's configured collation ignores case.
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')->ignore($department),
            ],
        ];
    }
}
