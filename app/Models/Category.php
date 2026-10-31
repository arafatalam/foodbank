<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    // Allow these fields to be saved together when creating or updating a category.
    protected $fillable = [
        'name',
        'department_id',
    ];

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        // Connect this category to its department using department_id.
        return $this->belongsTo(Department::class);
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        // Find the products directly assigned to this category.
        return $this->hasMany(Product::class);
    }
}
