<?php

namespace App\Http\Controllers;

use App\Actions\Products\CreateProduct;
use App\Actions\Products\ListProducts;
use App\Actions\Products\UpdateProduct;
use App\Http\Requests\Products\IndexProductsRequest;
use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Models\Category;
use App\Models\Department;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(IndexProductsRequest $request, ListProducts $action): Response
    {
        $filters = $request->safe()->only(['search', 'department_id', 'category_id']);

        return Inertia::render('products/Index', [
            'products' => $action->get($filters, $request->user()),
            'filters' => $filters,
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'department_id']),
            'canCreate' => $request->user()->can('create', Product::class),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        // Send only the fields needed by the connected choices.
        $departments = Department::query()->orderBy('name')->get(['id', 'name']);
        $categories = Category::query()->orderBy('name')->orderBy('id')->get(['id', 'name', 'department_id']);

        return Inertia::render('products/Create', [
            'departments' => $departments,
            'categories' => $categories,
        ]);
    }

    /**
     * Show the saved product in its editing form.
     */
    public function edit(Product $product): Response
    {
        // Load the saved category so the form can select it.
        $product->load('category');

        // Send only the saved values needed by the editing form.
        $productDetails = [
            ...$product->only(['id', 'name', 'category_id']),
            'department_id' => $product->category->department_id,
        ];

        // Provide the same connected choices as the creation form.
        $departments = Department::query()->orderBy('name')->get(['id', 'name']);
        $categories = Category::query()->orderBy('name')->orderBy('id')->get(['id', 'name', 'department_id']);

        // Pass the saved values and available choices to Vue through Inertia.
        return Inertia::render('products/Edit', [
            'product' => $productDetails,
            'departments' => $departments,
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request, CreateProduct $action): RedirectResponse
    {
        // The request validates input; the action saves the product.
        $action->create($request->productData(), $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Product created successfully.',
        ]);

        return back();
    }

    /**
     * Save changes to the selected product.
     */
    public function update(UpdateProductRequest $request, Product $product, UpdateProduct $action): RedirectResponse
    {
        // The request checks input and permission; the action saves the product changes.
        $action->update($product, $request->productData(), $request->user());

        // Show success feedback only after the action finishes successfully.
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Product updated successfully.',
        ]);

        // Return to the catalogue so the saved changes are visible.
        return to_route('products.index');
    }
}
