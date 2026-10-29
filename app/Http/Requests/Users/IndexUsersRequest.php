<?php

namespace App\Http\Requests\Users;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexUsersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // The route checks the UserPolicy viewAny permission.
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
            // Allow an empty search, or text up to 100 characters.
            'search' => ['nullable', 'string', 'max:100'],
            // Allow an omitted page number, or a whole number starting at 1.
            'page' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
        ];
    }
}
