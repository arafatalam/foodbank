<?php

namespace App\Http\Requests\StockItems;

use App\Models\StockItem;
use Illuminate\Foundation\Http\FormRequest;

class StoreStockItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', StockItem::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'package_size_id' => ['required', 'integer', 'exists:package_sizes,id'],
            'brand' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array{product_id: int, package_size_id: int, brand: ?string} */
    public function stockItemData(): array
    {
        // Pass only validated choices; never accept balance or brand_key from the browser.
        $brand = $this->validated('brand');

        return [
            'product_id' => (int) $this->validated('product_id'),
            'package_size_id' => (int) $this->validated('package_size_id'),
            'brand' => is_string($brand) ? $brand : null,
        ];
    }
}
