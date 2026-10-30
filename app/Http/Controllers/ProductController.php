<?php

namespace App\Http\Controllers;

use App\Actions\Products\CreateProduct;
use App\Actions\Products\ListProducts;
use App\Http\Requests\Products\IndexProductsRequest;
use App\Http\Requests\Products\StoreProductRequest;
use App\Models\Category;
use App\Models\Department;
use App\Models\Product;
use App\Models\Subcategory;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(IndexProductsRequest $request, ListProducts $action): Response
    {
        $filters = $request->safe()->only(['search', 'department_id', 'category_id', 'subcategory_id', 'tag_ids', 'status']);

        return Inertia::render('products/Index', [
            'products' => $action->get($filters),
            'filters' => $filters,
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'department_id']),
            'subcategories' => Subcategory::query()->orderBy('name')->get(['id', 'name', 'category_id']),
            'tags' => Tag::query()->orderBy('name')->get(['id', 'name']),
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
        $subcategories = Subcategory::query()->orderBy('name')->orderBy('id')->get(['id', 'name', 'category_id']);
        $tags = Tag::query()->orderBy('name')->get(['id', 'name']);

        return Inertia::render('products/Create', [
            'departments' => $departments,
            'categories' => $categories,
            'subcategories' => $subcategories,
            'tags' => $tags,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request, CreateProduct $action): RedirectResponse
    {
        // The request validates input; the action saves the connected records.
        $action->create($request->productData(), $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Product created successfully.',
        ]);

        return back();
    }
}
