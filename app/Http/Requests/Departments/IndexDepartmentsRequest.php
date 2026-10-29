<?php

namespace App\Http\Requests\Departments;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexDepartmentsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Accept an empty search or text up to 100 characters.
            'search' => ['nullable', 'string', 'max:100'],

            // Accept an omitted page or a positive whole-number page.
            'page' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
        ];
    }
}
