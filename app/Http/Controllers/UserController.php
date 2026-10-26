<?php

namespace App\Http\Controllers;

use App\Actions\Users\GenerateUsername;
use App\Actions\Users\GetAssignableRoles;
use App\Http\Requests\Users\StoreUserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): void
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request, GetAssignableRoles $action): Response
    {
        return Inertia::render('users/Create', [
            'roles' => $action->get($request->user()),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request, GenerateUsername $generateUsername): RedirectResponse
    {
        $data = $request->validated();

        $data['username'] = $generateUsername->generate(
            $data['first_name'],
            $data['last_name']
        );

        $data['password'] = config('auth.initial_password');

        $data['must_change_password'] = true;

        $user = User::create($data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'User created successfully.',
        ]);

        return to_route('dashboard');

    }

    /**
     * Display the specified resource.
     */
    public function show(User $user): void
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user): void
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user): void
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user): void
    {
        //
    }
}
