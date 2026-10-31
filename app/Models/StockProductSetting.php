<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockProductSetting extends Model
{
    // These values are prepared by Stock Management, never accepted from a catalogue form.
    protected $fillable = ['product_id', 'summary_unit'];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function hasMovements(): bool
    {
        $connection = $this->getConnection();

        // Any saved movement permanently fixes the Stock Management measurement family.
        return $connection->getSchemaBuilder()->hasTable('stock_movements')
            && $connection->table('stock_movements')
                ->join('stock_items', 'stock_items.id', '=', 'stock_movements.stock_item_id')
                ->where('stock_items.product_id', $this->product_id)->exists();
    }
}
