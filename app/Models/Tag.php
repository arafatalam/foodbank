<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    // Save the shared name without accepting IDs or unrelated submitted fields.
    protected $fillable = ['name'];
}
