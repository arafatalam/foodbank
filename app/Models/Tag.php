<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    // Save the shared name without accepting IDs or unrelated submitted fields.
    protected $fillable = ['name'];

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        // Use the same linking table to find products carrying this tag.
        return $this->belongsToMany(Product::class);
    }
}
