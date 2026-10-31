<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** @property string $contents Exact decimal text supplied by the decimal:3 cast. */
class PackageSize extends Model
{
    // Allow these fields to be saved together when creating or updating a package.
    protected $fillable = [
        'package_type',
        'contents',
        'contents_unit',
    ];

    protected function casts(): array
    {
        // Decimal strings preserve the stored measurement without floating-point rounding.
        return ['contents' => 'decimal:3'];
    }

    public function isReferenced(): bool
    {
        // Stock-item storage is introduced in the next module; none can refer here yet.
        $connection = $this->getConnection();
        if (! $connection->getSchemaBuilder()->hasTable('stock_items')) {
            return false;
        }

        // Any stock item protects its package definition, even with zero stock.
        return $connection->table('stock_items')->where('package_size_id', $this->id)->exists();
    }
}
