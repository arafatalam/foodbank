<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    // Allow the name to be saved when creating or updating a department.
    protected $fillable = [
        'name',
    ];
}
