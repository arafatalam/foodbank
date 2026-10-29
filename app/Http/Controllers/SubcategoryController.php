<?php

namespace App\Http\Controllers;

use App\Actions\Subcategories\ListSubcategories;
use App\Http\Requests\Subcategories\IndexSubcategoriesRequest;
use App\Http\Requests\Subcategories\StoreSubcategoryRequest;
use App\Http\Requests\Subcategories\UpdateSubcategoryRequest;
use App\Models\Category;
use App\Models\Department;
use App\Models\Subcategory;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SubcategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(IndexSubcategoriesRequest $request, ListSubcategories $action): Response
    {
        // Pass only the validated search text to the listing action.
        $filters = $request->safe()->only(['search']);
        $subcategories = $action->get($filters, $request->user());

        return Inertia::render('subcategories/Index', [
            'subcategories' => $subcategories,
            'search' => $filters['search'] ?? '',
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        // Send only the fields the two connected dropdowns need.
        $departments = Department::query()->orderBy('name')->get(['id', 'name']);
        $categories = Category::query()->orderBy('name')->orderBy('id')
            ->get(['id', 'name', 'department_id']);

        return Inertia::render('subcategories/Create', [
            'departments' => $departments,
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSubcategoryRequest $request): RedirectResponse
    {
        // Department was checked by validation; store only the direct parent and name.
        Subcategory::create($request->safe()->only(['category_id', 'name']));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Subcategory created successfully.',
        ]);

        return back();
    }

    /**
     * Display the specified resource.
     */
    public function show(Subcategory $subcategory)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Subcategory $subcategory): Response
    {
        // Load the current path so both dropdowns start with the saved selections.
        $subcategory->load('category.department');
        $subcategoryDetails = [
            ...$subcategory->only(['id', 'name']),
            'category' => [
                ...$subcategory->category->only(['id', 'name']),
                'department' => $subcategory->category->department->only(['id', 'name']),
            ],
        ];

        // Match the choices provided by the creation form.
        $departments = Department::query()->orderBy('name')->get(['id', 'name']);
        $categories = Category::query()->orderBy('name')->orderBy('id')->get(['id', 'name', 'department_id']);

        return Inertia::render('subcategories/Edit', [
            'subcategory' => $subcategoryDetails,
            'departments' => $departments,
            'categories' => $categories,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSubcategoryRequest $request, Subcategory $subcategory): RedirectResponse
    {
        // Department is checked for consistency; save the direct category link and name.
        $subcategory->update($request->safe()->only(['category_id', 'name']));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Subcategory updated successfully.',
        ]);

        return to_route('subcategories.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Subcategory $subcategory)
    {
        //
    }
}
