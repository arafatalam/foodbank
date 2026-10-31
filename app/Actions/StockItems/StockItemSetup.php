<?php

namespace App\Actions\StockItems;

use App\Models\PackageSize;
use App\Models\Product;
use App\Models\StockItem;
use App\Models\StockProductSetting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class StockItemSetup
{
    /** @return array{brand: ?string, brand_key: string} */
    public function normalizeBrand(?string $brand): array
    {
        // Preserve display case but compare brands without repeated spaces or letter case.
        $display = trim(preg_replace('/\s+/u', ' ', $brand ?? '') ?? '');

        return ['brand' => $display === '' ? null : $display, 'brand_key' => mb_strtolower($display)];
    }

    /** @return Collection<int, Product> */
    public function lockProducts(int $productId, ?StockItem $item = null): Collection
    {
        $ids = array_unique([$productId, $item === null ? $productId : $item->product_id]);

        return Product::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();
    }

    /** @return Collection<int, PackageSize> */
    public function lockPackages(int $packageId, ?StockItem $item = null): Collection
    {
        $ids = array_unique([$packageId, $item === null ? $packageId : $item->package_size_id]);

        // Package updates/deletions take this same row lock before checking references.
        return PackageSize::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();
    }

    /** @param array{product_id: int, package_size_id: int, brand: ?string} $data */
    public function ensureCombinationAvailable(array $data, string $brandKey, ?int $exceptId = null): void
    {
        $query = StockItem::query()->where('product_id', $data['product_id'])
            ->where('package_size_id', $data['package_size_id'])->where('brand_key', $brandKey);
        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['brand' => 'This Product, brand and package size combination already exists.']);
        }
    }

    public function ensureCompatiblePackage(Product $product, PackageSize $package, ?StockItem $item = null): void
    {
        $family = $package->summaryUnit();
        $settings = StockProductSetting::query()->where('product_id', $product->id)->first();
        $summaryUnit = $settings?->summary_unit;
        $others = StockItem::query()->where('product_id', $product->id);
        if ($item !== null) {
            $others->whereKeyNot($item->id);
        }

        // A sole unused item can switch families; other items or any history prevent it.
        if ($summaryUnit !== null && $summaryUnit !== $family
            && ($others->exists() || $settings->hasMovements())) {
            throw ValidationException::withMessages([
                'package_size_id' => 'Choose a package compatible with this Product’s '.$summaryUnit.' totals.',
            ]);
        }
    }

    /** @param Collection<int, Product> $products */
    public function syncProductUnits(Collection $products): void
    {
        foreach ($products as $product) {
            $this->syncProductUnit($product);
        }
    }

    public function syncProductUnit(Product $product): void
    {
        // Caller holds the Product lock; save only Stock Management's setting.
        $settings = StockProductSetting::query()->firstOrNew(['product_id' => $product->id]);
        if ($settings->hasMovements()) {
            return;
        }

        $firstItem = StockItem::query()->where('product_id', $product->id)
            ->with('packageSize')->orderBy('id')->first();
        $settings->summary_unit = $firstItem?->packageSize->summaryUnit();
        $settings->save();
    }
}
