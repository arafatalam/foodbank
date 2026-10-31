<?php

namespace App\Http\Requests\StockItems;

use Illuminate\Foundation\Http\FormRequest;

class IndexStockItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route checks the StockItem policy.
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
        ];
    }
}
