<?php

namespace App\Http\Controllers;

use App\Actions\Departments\ListDepartments;
use App\Http\Requests\Departments\IndexDepartmentsRequest;
use App\Http\Requests\Departments\StoreDepartmentRequest;
use App\Http\Requests\Departments\UpdateDepartmentRequest;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(
        IndexDepartmentsRequest $request,
        ListDepartments $action,
    ): Response {
        // Collect only the validated search filter.
        $filters = $request->safe()->only(['search']);

        // Ask the action for matching departments and page information.
        $departments = $action->get($filters);

        // Send the results and current search text to Vue.
        return Inertia::render('departments/Index', [
            'departments' => $departments,
            'canCreate' => $request->user()->can('create', Department::class),
            'search' => $filters['search'] ?? '',
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('departments/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        // Save only the fields that passed the request's validation.
        Department::create($request->validated());

        // Send success feedback to the screen.
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Department created successfully.',
        ]);

        // Return to the creation form.
        return back();
    }

    /**
     * Display the specified resource.
     */
    public function show(Department $department): void
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Department $department): Response
    {
        // Send only the saved fields needed by the edit form.
        $departmentDetails = $department->only(['id', 'name']);

        return Inertia::render('departments/Edit', [
            'department' => $departmentDetails,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        // Rename the existing record without changing its ID or links.
        $department->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Department updated successfully.',
        ]);

        // Follow Edit User's return-to-list behaviour after saving.
        return to_route('departments.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Department $department): void
    {
        //
    }
}
