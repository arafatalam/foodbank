<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockItem extends Model
{
    // Comparison keys are internal; only the original brand belongs in page data.
    protected $hidden = ['brand_key'];

    // Allow these fields to be saved when creating or updating a stock item.
    protected $fillable = [
        'product_id',
        'package_size_id',
        'brand',
        'brand_key',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['available_quantity' => 'integer'];
    }

    public function hasMovements(): bool
    {
        // The movement module is built later; no history exists before its table does.
        $connection = $this->getConnection();

        return $connection->getSchemaBuilder()->hasTable('stock_movements')
            && $connection->table('stock_movements')->where('stock_item_id', $this->id)->exists();
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        // Retrieve the Product connected through product_id.
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<PackageSize, $this> */
    public function packageSize(): BelongsTo
    {
        // Retrieve the reusable package definition connected through package_size_id.
        return $this->belongsTo(PackageSize::class);
    }
}
