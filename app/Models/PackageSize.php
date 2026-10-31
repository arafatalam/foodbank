<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

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

    public function label(): string
    {
        // Keep the human-readable package identity the same in forms and stock lists.
        $type = ucfirst(str_replace('_', ' ', $this->package_type));
        $contents = rtrim(rtrim((string) $this->contents, '0'), '.');
        $unit = match ($this->contents_unit) {
            'litre' => 'L',
            'item' => 'items',
            default => $this->contents_unit,
        };

        return $type.' '.$contents.' '.$unit;
    }

    public function summaryUnit(): string
    {
        // A package's measurement determines its compatible Stock Management family.
        return match ($this->contents_unit) {
            'g', 'kg' => 'kg',
            'ml', 'litre' => 'litre',
            'item' => 'item',
            default => throw ValidationException::withMessages([
                'package_size_id' => 'Choose a package with a supported measurement unit.',
            ]),
        };
    }

    public function isReferenced(): bool
    {
        // Preserve access to package setup before the pending stock migration is applied.
        $connection = $this->getConnection();
        if (! $connection->getSchemaBuilder()->hasTable('stock_items')) {
            return false;
        }

        // Any stock item protects its package definition, even with zero stock.
        return $connection->table('stock_items')->where('package_size_id', $this->id)->exists();
    }
}
