<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    protected $fillable = [
        'name',
        'brand',
        'subcategory_id',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        // Read the saved status as a true-or-false value.
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Subcategory, $this>
     */
    public function subcategory(): BelongsTo
    {
        // Find the Subcategory using this product's subcategory_id.
        return $this->belongsTo(Subcategory::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        // Follow product_tag to this product's shared labels.
        return $this->belongsToMany(Tag::class);
    }
}
