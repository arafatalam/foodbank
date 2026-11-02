<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Household extends Model
{
    // Allow these fields to be saved together when creating a household.
    protected $fillable = [
        'first_name',
        'last_name',
    ];
}
