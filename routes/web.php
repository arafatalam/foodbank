<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SetupPasswordController;
use App\Http\Controllers\SubcategoryController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\UserController;
use App\Models\Category;
use App\Models\Department;
use App\Models\Product;
use App\Models\Subcategory;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\Route;

// Require sign-in; email verification is not required for now.
Route::middleware(['auth'])->group(function () {

    Route::inertia('/', 'Dashboard')->name('home');

    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    // -------------------------- DEPARTMENT ROUTES --------------------------//

    // Open the department list only when its policy allows access.
    Route::get('departments', [DepartmentController::class, 'index'])
        ->name('departments.index')
        ->can('viewAny', Department::class);

    // Open the form only when the Department policy allows creation.
    Route::get('departments/create', [DepartmentController::class, 'create'])
        ->name('departments.create')
        ->can('create', Department::class);

    // Save a department only when the policy allows creation.
    Route::post('departments', [DepartmentController::class, 'store'])
        ->name('departments.store')
        ->can('create', Department::class);

    // Check update permission before showing the saved department's form.
    Route::get('departments/{department}/edit', [DepartmentController::class, 'edit'])
        ->whereNumber('department')
        ->name('departments.edit')
        ->can('update', 'department');

    // Apply the same permission check when saving a rename.
    Route::patch('departments/{department}', [DepartmentController::class, 'update'])
        ->whereNumber('department')
        ->name('departments.update')
        ->can('update', 'department');

    // -------------------------- TAG ROUTES --------------------------//

    // Open the tag list only when its policy allows access.
    Route::get('tags', [TagController::class, 'index'])
        ->name('tags.index')
        ->can('viewAny', Tag::class);

    // Open the form only when the Tag policy allows creation.
    Route::get('tags/create', [TagController::class, 'create'])
        ->name('tags.create')
        ->can('create', Tag::class);

    // Save a tag only when the policy allows creation.
    Route::post('tags', [TagController::class, 'store'])
        ->name('tags.store')
        ->can('create', Tag::class);

    // Check update permission before showing the saved tag's form.
    Route::get('tags/{tag}/edit', [TagController::class, 'edit'])
        ->whereNumber('tag')
        ->name('tags.edit')
        ->can('update', 'tag');

    // Apply the same permission check when saving a rename.
    Route::patch('tags/{tag}', [TagController::class, 'update'])
        ->whereNumber('tag')
        ->name('tags.update')
        ->can('update', 'tag');

    // -------------------------- CATEGORY ROUTES --------------------------//

    // Check list permission before loading categories or searching them.
    Route::get('categories', [CategoryController::class, 'index'])
        ->name('categories.index')
        ->can('viewAny', Category::class);

    // Open the category form only when its policy allows creation.
    Route::get('categories/create', [CategoryController::class, 'create'])
        ->name('categories.create')
        ->can('create', Category::class);

    // Check creation permission again when the form is submitted.
    Route::post('categories', [CategoryController::class, 'store'])
        ->name('categories.store')
        ->can('create', Category::class);

    // Check update permission before opening the category's edit form.
    Route::get('categories/{category}/edit', [CategoryController::class, 'edit'])
        ->whereNumber('category')
        ->name('categories.edit')
        ->can('update', 'category');

    // Check the same permission again before saving its new name.
    Route::patch('categories/{category}', [CategoryController::class, 'update'])
        ->whereNumber('category')
        ->name('categories.update')
        ->can('update', 'category');

    // Check list permission before loading or searching Subcategory records.
    Route::get('subcategories', [SubcategoryController::class, 'index'])
        ->name('subcategories.index')
        ->can('viewAny', Subcategory::class);

    // Check creation permission before loading choices or saving a Subcategory.
    Route::get('subcategories/create', [SubcategoryController::class, 'create'])
        ->name('subcategories.create')
        ->can('create', Subcategory::class);

    Route::post('subcategories', [SubcategoryController::class, 'store'])
        ->name('subcategories.store')
        ->can('create', Subcategory::class);

    // Check rename permission before displaying or saving the edit form.
    Route::get('subcategories/{subcategory}/edit', [SubcategoryController::class, 'edit'])
        ->whereNumber('subcategory')
        ->name('subcategories.edit')
        ->can('update', 'subcategory');

    Route::patch('subcategories/{subcategory}', [SubcategoryController::class, 'update'])
        ->whereNumber('subcategory')
        ->name('subcategories.update')
        ->can('update', 'subcategory');

    // -------------------------- PRODUCT ROUTES --------------------------//

    Route::get('products', [ProductController::class, 'index'])
        ->name('products.index')
        ->can('viewAny', Product::class);

    // Check creation permission before displaying the page.
    Route::get('products/create', [ProductController::class, 'create'])
        ->name('products.create')
        ->can('create', Product::class);

    // Apply the same permission before accepting a product save.
    Route::post('products', [ProductController::class, 'store'])
        ->name('products.store')
        ->can('create', Product::class);

    // Check edit permission for the selected product before opening its form.
    Route::get('products/{product}/edit', [ProductController::class, 'edit'])
        ->whereNumber('product')
        ->name('products.edit')
        ->can('update', 'product');

    // Check the same product permission before accepting saved changes.
    Route::patch('products/{product}', [ProductController::class, 'update'])
        ->whereNumber('product')
        ->name('products.update')
        ->can('update', 'product');

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
