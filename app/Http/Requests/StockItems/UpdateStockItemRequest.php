<?php

namespace App\Http\Requests\StockItems;

use App\Models\StockItem;

class UpdateStockItemRequest extends StoreStockItemRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $item = $this->route('stockItem');

        return $item instanceof StockItem && ($this->user()?->can('update', $item) ?? false);
    }
}
