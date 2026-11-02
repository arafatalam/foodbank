<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    // Allow these fields to be assigned when creating a movement.
    protected $fillable = [
        'stock_entry_id',
        'stock_item_id',
        'direction',
        'quantity',
        'resulting_balance',
    ];

    // Read quantities and balances as whole numbers in PHP.
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'resulting_balance' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<StockEntry, $this>
     */
    public function stockEntry(): BelongsTo
    {
        // Find the receipt identified by this movement's stock_entry_id.
        return $this->belongsTo(StockEntry::class);
    }

    /**
     * @return BelongsTo<StockItem, $this>
     */
    public function stockItem(): BelongsTo
    {
        // Find the stock item identified by this movement's stock_item_id.
        return $this->belongsTo(StockItem::class);
    }
}
