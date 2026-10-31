<?php

namespace App\Http\Controllers;

use App\Actions\StockItems\CreateStockItem;
use App\Actions\StockItems\DeleteStockItem;
use App\Actions\StockItems\ListStockItems;
use App\Actions\StockItems\UpdateStockItem;
use App\Http\Requests\StockItems\IndexStockItemsRequest;
use App\Http\Requests\StockItems\StoreStockItemRequest;
use App\Http\Requests\StockItems\UpdateStockItemRequest;
use App\Models\PackageSize;
use App\Models\Product;
use App\Models\StockItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(IndexStockItemsRequest $request, ListStockItems $action): Response
    {
        $filters = ['search' => (string) $request->validated('search', '')];

        return Inertia::render('stock-items/Index', [
            'stockItems' => $action->get($filters, $request->user()),
            'search' => $filters['search'],
            'canCreate' => $request->user()->can('create', StockItem::class),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        // Display the Stock item creation page through Inertia.
        return Inertia::render('stock-items/Create', $this->formOptions());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStockItemRequest $request, CreateStockItem $action): RedirectResponse
    {
        $action->create($request->stockItemData(), $request->user());
        $this->flashSuccess('Stock item created successfully.');

        return back();
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(StockItem $stockItem): Response
    {
        return Inertia::render('stock-items/Edit', [
            ...$this->formOptions(),
            'stockItem' => $stockItem->only(['id', 'product_id', 'package_size_id', 'brand']),
            'identityLocked' => $stockItem->hasMovements(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStockItemRequest $request, StockItem $stockItem, UpdateStockItem $action): RedirectResponse
    {
        $action->update($stockItem, $request->stockItemData(), $request->user());
        $this->flashSuccess('Stock item updated successfully.');

        return to_route('stock-items.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, StockItem $stockItem, DeleteStockItem $action): RedirectResponse
    {
        $action->delete($stockItem, $request->user());
        $this->flashSuccess('Unused stock item deleted successfully.');

        return to_route('stock-items.index');
    }

    private function flashSuccess(string $message): void
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);
    }

    /** @return array{products: list<array{id: int, label: string}>, packageSizes: list<array{id: int, label: string}>} */
    private function formOptions(): array
    {
        return ['products' => $this->productOptions(), 'packageSizes' => $this->packageOptions()];
    }

    /** @return list<array{id: int, label: string}> */
    private function productOptions(): array
    {
        // Current hierarchy distinguishes equal Product names in different categories.
        $products = Product::query()->with('category.department')->orderBy('name')->orderBy('id')->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'label' => $product->name.' — '.$product->category->department->name.' / '.$product->category->name,
            ])->all();

        return array_values($products);
    }

    /** @return list<array{id: int, label: string}> */
    private function packageOptions(): array
    {
        $packages = PackageSize::query()->orderBy('package_type')->orderBy('contents_unit')->orderBy('contents')->orderBy('id')->get()
            ->map(fn (PackageSize $package): array => ['id' => $package->id, 'label' => $package->label()])->all();

        return array_values($packages);
    }
}
