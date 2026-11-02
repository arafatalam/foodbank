<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockEntry extends Model
{
    // Allow these fields to be assigned when creating an entry.
    protected $fillable = [
        'type',
        'created_by',
        'notes',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        // Find the user whose ID is stored in this entry's created_by column.
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function movements(): HasMany
    {
        // Find all movements whose stock_entry_id matches this entry's ID.
        return $this->hasMany(StockMovement::class);
    }
}
