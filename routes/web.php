<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\PackageSizeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SetupPasswordController;
use App\Http\Controllers\StockItemController;
use App\Http\Controllers\UserController;
use App\Models\Category;
use App\Models\Department;
use App\Models\PackageSize;
use App\Models\Product;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Support\Facades\Route;

// Require sign-in; email verification is not required for now.
Route::middleware(['auth'])->group(function () {

    Route::inertia('/', 'Dashboard')->name('home');

    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    // -------------------------- PACKAGE SIZES ROUTES --------------------------//

    // All package-size management URLs begin with /package-sizes.
    Route::prefix('package-sizes')->group(function () {
        // Check list permission before opening the package sizes list.
        Route::get('/', [PackageSizeController::class, 'index'])
            ->name('package-sizes.index')
            ->can('viewAny', PackageSize::class);

        Route::get('create', [PackageSizeController::class, 'create'])
            ->name('package-sizes.create')
            ->can('create', PackageSize::class);

        // Check creation permission before accepting a package submission.
        Route::post('/', [PackageSizeController::class, 'store'])
            ->name('package-sizes.store')
            ->can('create', PackageSize::class);

        // Protect both the edit page and its submission with the same policy ability.
        Route::get('{packageSize}/edit', [PackageSizeController::class, 'edit'])
            ->whereNumber('packageSize')
            ->name('package-sizes.edit')
            ->can('update', 'packageSize');

        Route::patch('{packageSize}', [PackageSizeController::class, 'update'])
            ->whereNumber('packageSize')
            ->name('package-sizes.update')
            ->can('update', 'packageSize');

        // Definitions may be deleted only while no stock item references them.
        Route::delete('{packageSize}', [PackageSizeController::class, 'destroy'])
            ->whereNumber('packageSize')
            ->name('package-sizes.destroy')
            ->can('delete', 'packageSize');
    });

    // -------------------------- STOCK ITEMS ROUTES --------------------------//

    // All stock-item management URLs begin with /stock-items.
    Route::prefix('stock-items')->group(function () {
        Route::get('/', [StockItemController::class, 'index'])
            ->name('stock-items.index')
            ->can('viewAny', StockItem::class);

        // Check creation permission before displaying the form.
        Route::get('create', [StockItemController::class, 'create'])
            ->name('stock-items.create')
            ->can('create', StockItem::class);

        // Protect submissions independently of the form and its visible buttons.
        Route::post('/', [StockItemController::class, 'store'])
            ->name('stock-items.store')
            ->can('create', StockItem::class);

        Route::get('{stockItem}/edit', [StockItemController::class, 'edit'])
            ->whereNumber('stockItem')
            ->name('stock-items.edit')
            ->can('update', 'stockItem');

        Route::patch('{stockItem}', [StockItemController::class, 'update'])
            ->whereNumber('stockItem')
            ->name('stock-items.update')
            ->can('update', 'stockItem');

        Route::delete('{stockItem}', [StockItemController::class, 'destroy'])
            ->whereNumber('stockItem')
            ->name('stock-items.destroy')
            ->can('delete', 'stockItem');
    });

    // -------------------------- DEPARTMENTS ROUTES --------------------------//

    // All department-management URLs begin with /departments.
    Route::prefix('departments')->group(function () {
        // Open the department list only when its policy allows access.
        Route::get('/', [DepartmentController::class, 'index'])
            ->name('departments.index')
            ->can('viewAny', Department::class);

        // Open the form only when the Department policy allows creation.
        Route::get('create', [DepartmentController::class, 'create'])
            ->name('departments.create')
            ->can('create', Department::class);

        // Save a department only when the policy allows creation.
        Route::post('/', [DepartmentController::class, 'store'])
            ->name('departments.store')
            ->can('create', Department::class);

        // Check update permission before showing the saved department's form.
        Route::get('{department}/edit', [DepartmentController::class, 'edit'])
            ->whereNumber('department')
            ->name('departments.edit')
            ->can('update', 'department');

        // Apply the same permission check when saving a rename.
        Route::patch('{department}', [DepartmentController::class, 'update'])
            ->whereNumber('department')
            ->name('departments.update')
            ->can('update', 'department');
    });

    // -------------------------- CATEGORIES ROUTES --------------------------//

    // All category-management URLs begin with /categories.
    Route::prefix('categories')->group(function () {
        // Check list permission before loading categories or searching them.
        Route::get('/', [CategoryController::class, 'index'])
            ->name('categories.index')
            ->can('viewAny', Category::class);

        // Open the category form only when its policy allows creation.
        Route::get('create', [CategoryController::class, 'create'])
            ->name('categories.create')
            ->can('create', Category::class);

        // Check creation permission again when the form is submitted.
        Route::post('/', [CategoryController::class, 'store'])
            ->name('categories.store')
            ->can('create', Category::class);

        // Check update permission before opening the category's edit form.
        Route::get('{category}/edit', [CategoryController::class, 'edit'])
            ->whereNumber('category')
            ->name('categories.edit')
            ->can('update', 'category');

        // Check the same permission again before saving its new name.
        Route::patch('{category}', [CategoryController::class, 'update'])
            ->whereNumber('category')
            ->name('categories.update')
            ->can('update', 'category');
    });

    // -------------------------- PRODUCTS ROUTES --------------------------//

    // All product-management URLs begin with /products.
    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index'])
            ->name('products.index')
            ->can('viewAny', Product::class);

        // Check creation permission before displaying the page.
        Route::get('create', [ProductController::class, 'create'])
            ->name('products.create')
            ->can('create', Product::class);

        // Apply the same permission before accepting a product save.
        Route::post('/', [ProductController::class, 'store'])
            ->name('products.store')
            ->can('create', Product::class);

        // Check edit permission for the selected product before opening its form.
        Route::get('{product}/edit', [ProductController::class, 'edit'])
            ->whereNumber('product')
            ->name('products.edit')
            ->can('update', 'product');

        // Check the same product permission before accepting saved changes.
        Route::patch('{product}', [ProductController::class, 'update'])
            ->whereNumber('product')
            ->name('products.update')
            ->can('update', 'product');
    });

    // -------------------------- USER ROUTES --------------------------//

    // All user-management URLs begin with /users.
    Route::prefix('users')->group(function () {
        // Open the user-list page.
        Route::get('/', [UserController::class, 'index'])
            ->name('users.index')
            ->can('viewAny', User::class);

        Route::get('create', [UserController::class, 'create'])
            ->name('users.create')
            ->can('create', User::class);

        Route::post('/', [UserController::class, 'store'])
            ->name('users.store')
            ->can('create', User::class);

        // This route handles numeric user IDs only.
        Route::get('{user}', [UserController::class, 'show'])
            ->whereNumber('user')
            ->name('users.show')
            ->can('view', 'user');

        // Open the edit page only when this person may update this account.
        Route::get('{user}/edit', [UserController::class, 'edit'])
            ->whereNumber('user')
            ->name('users.edit')
            ->can('update', 'user');

        // Save changes only when this person may update the selected account.
        Route::patch('{user}', [UserController::class, 'update'])
            ->whereNumber('user')
            ->name('users.update')
            ->can('update', 'user');
    });

    // Both password-setup routes use /setup-password.
    Route::prefix('setup-password')->group(function () {
        // Send the setup page request to its controller.
        Route::get('/', [SetupPasswordController::class, 'show'])
            ->name('password.setup.show');

        // Send the password submission to the controller's store method.
        Route::post('/', [SetupPasswordController::class, 'store'])
            ->name('password.setup.store');
    });

});

require __DIR__.'/settings.php';
