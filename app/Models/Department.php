<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    // Allow the name to be saved when creating or updating a department.
    protected $fillable = [
        'name',
    ];

    /**
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        // Find categories whose department_id matches this department's id.
        return $this->hasMany(Category::class);
    }
}
