<?php

use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\SetupPasswordController;
use App\Http\Controllers\UserController;
use App\Models\Department;
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
