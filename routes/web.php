<?php

use App\Http\Controllers\UserController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('users/create', [UserController::class, 'create'])
        ->name('users.create')
        ->can('create', User::class);

    Route::post('users', [UserController::class, 'store'])
        ->name('users.store')
        ->can('create', User::class);

});

require __DIR__.'/settings.php';
