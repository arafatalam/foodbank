<?php

namespace App\Http\Controllers;

use App\Actions\Categories\ListCategories;
use App\Http\Requests\Categories\IndexCategoriesRequest;
use App\Http\Requests\Categories\StoreCategoryRequest;
use App\Http\Requests\Categories\UpdateCategoryRequest;
use App\Models\Category;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(IndexCategoriesRequest $request, ListCategories $action): Response
    {
        // Collect only the validated search filter.
        $filters = $request->safe()->only(['search']);

        // Load matching categories together with their departments.
        $categories = $action->get($filters);

        // Send the current page and search text to Vue.
        return Inertia::render('categories/Index', [
            'categories' => $categories,
            'search' => $filters['search'] ?? '',
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        // Load only the saved department fields needed by the dropdown.
        $departments = Department::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        // Send the choices to the Category creation page.
        return Inertia::render('categories/Create', [
            'departments' => $departments,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        // Save only the fields that passed the request's validation.
        Category::create($request->validated());

        // Send success feedback to the screen.
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Category created successfully.',
        ]);

        // Return to the creation form.
        return back();
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Category $category): Response
    {
        // Load the saved category and all department choices for the edit form.
        $category->load('department:id,name');
        $categoryDetails = [
            ...$category->only(['id', 'name']),
            'department' => $category->department->only(['id', 'name']),
        ];

        // Keep the dropdown choices alphabetical, just like category creation.
        $departments = Department::query()->orderBy('name')->get(['id', 'name']);

        return Inertia::render('categories/Edit', [
            'category' => $categoryDetails,
            'departments' => $departments,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        // Save the validated name and selected department on the same category.
        $category->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Category updated successfully.',
        ]);

        return to_route('categories.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        //
    }
}
