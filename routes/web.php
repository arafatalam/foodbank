<?php

use App\Http\Controllers\SetupPasswordController;
use App\Http\Controllers\UserController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// Require sign-in; email verification is not required for now.
Route::middleware(['auth'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    // Open the user-list page.
    Route::get('users', [UserController::class, 'index'])
        ->name('users.index')
        ->can('viewAny', User::class);

    Route::get('users/create', [UserController::class, 'create'])
        ->name('users.create')
        ->can('create', User::class);

    Route::post('users', [UserController::class, 'store'])
        ->name('users.store')
        ->can('create', User::class);

    // This route handles numeric user IDs only.
    Route::get('users/{user}', [UserController::class, 'show'])
        ->whereNumber('user')
        ->name('users.show')
        ->can('view', 'user');

    // Send the setup page request to its controller.
    Route::get('setup-password', [SetupPasswordController::class, 'show'])
        ->name('password.setup.show');

    // Send the password submission to the controller's store method.
    Route::post('setup-password', [SetupPasswordController::class, 'store'])
        ->name('password.setup.store');

});

require __DIR__.'/settings.php';
