<?php

namespace App\Models;

use App\Enums\RoleCode;
use Illuminate\Database\Eloquent\Model;

/**
 * @property RoleCode $code
 */
class Role extends Model
{
    protected $fillable = [
        'code',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'code' => RoleCode::class,
        ];
    }
}
