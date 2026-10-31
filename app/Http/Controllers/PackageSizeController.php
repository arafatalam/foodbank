<?php

namespace App\Http\Controllers;

use App\Actions\PackageSizes\CreatePackageSize;
use App\Actions\PackageSizes\ListPackageSizes;
use App\Actions\PackageSizes\UpdatePackageSize;
use App\Http\Requests\PackageSizes\IndexPackageSizesRequest;
use App\Http\Requests\PackageSizes\StorePackageSizeRequest;
use App\Http\Requests\PackageSizes\UpdatePackageSizeRequest;
use App\Models\PackageSize;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PackageSizeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(IndexPackageSizesRequest $request, ListPackageSizes $action): Response
    {
        $filters = ['search' => (string) $request->validated('search', '')];

        // Send only the fields and per-record permissions needed by the table.
        $packageSizes = $action->get($filters)->through(fn (PackageSize $packageSize): array => [
            'id' => $packageSize->id,
            'package_type' => $packageSize->package_type,
            'contents' => (string) $packageSize->contents,
            'contents_unit' => $packageSize->contents_unit,
            'canUpdate' => $request->user()->can('update', $packageSize),
        ]);

        return Inertia::render('package-sizes/Index', [
            'packageSizes' => $packageSizes,
            'search' => $filters['search'],
            'canCreate' => $request->user()->can('create', PackageSize::class),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        // Display the package creation page.
        return Inertia::render('package-sizes/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(
        StorePackageSizeRequest $request,
        CreatePackageSize $action,
    ): RedirectResponse {
        // Save only validated input, using the signed-in person's permissions.
        $action->create($request->packageData(), $request->user());

        // Show success feedback and return to the creation form.
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Package size created successfully.',
        ]);

        return back();
    }

    /**
     * Display the specified resource.
     */
    public function show(PackageSize $packageSize): void
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PackageSize $packageSize): Response
    {
        return Inertia::render('package-sizes/Edit', [
            'packageSize' => $packageSize->only(['id', 'package_type', 'contents', 'contents_unit']),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        UpdatePackageSizeRequest $request,
        PackageSize $packageSize,
        UpdatePackageSize $action,
    ): RedirectResponse {
        $action->update($packageSize, $request->packageData(), $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Package size updated successfully.',
        ]);

        return to_route('package-sizes.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PackageSize $packageSize): void
    {
        //
    }
}
