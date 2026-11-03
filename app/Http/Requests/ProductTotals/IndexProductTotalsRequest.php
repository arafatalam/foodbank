<?php

namespace App\Http\Requests\ProductTotals;

use Illuminate\Foundation\Http\FormRequest;

class IndexProductTotalsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route already checks the viewTotals policy permission.
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            // Allow an empty search or text up to 100 characters.
            'search' => ['nullable', 'string', 'max:100'],

            // Accept only positive page numbers within this limit.
            'page' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
        ];
    }
}
