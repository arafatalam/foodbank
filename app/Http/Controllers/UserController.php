<?php

namespace App\Http\Controllers;

use App\Actions\Users\GetAssignableRoles;
use App\Actions\Users\ListUsers;
use App\Actions\Users\SaveUser;
use App\Http\Requests\Users\IndexUsersRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
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

        // Give the list action the filters and the signed-in person.
        $users = $action->get($filters, $request->user());

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
    public function store(StoreUserRequest $request, SaveUser $saveUser): RedirectResponse
    {
        // Generate and save together, retrying a concurrent username conflict.
        $user = $saveUser->save($request->userData());

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
    public function show(Request $request, User $user): Response
    {
        // Collect the account fields this page needs.
        $userDetails = $user->only([
            'id',
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
            'canEdit' => $request->user()->can('update', $user),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, User $user, GetAssignableRoles $action): Response
    {
        // Collect only the saved account fields the edit page needs.
        $userDetails = $user->only([
            'id',
            'first_name',
            'last_name',
            'username',
            'email',
            'phone',
            'role_id',
            'is_active',
        ]);

        // Open Edit.vue and provide its user prop.
        return Inertia::render('users/Edit', [
            'user' => $userDetails,
            'roles' => $action->get($request->user(), forEdit: true),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user, SaveUser $saveUser): RedirectResponse
    {
        // The action retries name conflicts and preserves deactivation safeguards.
        $user = $saveUser->save($request->userData(), $user);

        // Include the resulting username in the success message.
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'User updated successfully. Username: '.$user->username,
        ]);

        // Return to the user list after saving the account.
        return to_route('users.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user): void
    {
        //
    }
}
