<?php

namespace App\Http\Controllers;

use App\Actions\Users\GenerateUsername;
use App\Actions\Users\GetAssignableRoles;
use App\Actions\Users\ListUsers;
use App\Http\Requests\Users\IndexUsersRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(IndexUsersRequest $request, ListUsers $action): Response
    {
        // Collect only the validated search value.
        $filters = $request->safe()->only(['search']);

        // Pass the validated filters to the user-list action.
        $users = $action->get($filters);

        // Send the paginated users to the directory page.
        return Inertia::render('users/Index', [
            'users' => $users,
            'search' => $filters['search'] ?? '',
        ]);
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

        // Record that this administrator-created account is treated as verified.
        $user->email_verified_at = now();
        $user->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'User created successfully.',
        ]);

        // Open the details page for the account just created.
        return to_route('users.show', $user);

    }

    /**
     * Display the specified resource.
     */
    public function show(User $user): Response
    {
        // Collect the account fields this page needs.
        $userDetails = $user->only([
            'first_name',
            'last_name',
            'username',
            'email',
            'phone',
        ]);

        // Turn stored values into readable text for the details page.
        $userDetails['role'] = $user->role->code->label();
        $userDetails['status'] = $user->is_active ? 'Active' : 'Inactive';

        // Send these details to the Vue page through Inertia.
        return Inertia::render('users/Show', [
            'user' => $userDetails,
        ]);
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
