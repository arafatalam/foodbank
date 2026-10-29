<?php

namespace App\Http\Controllers;

use App\Actions\Users\GenerateUsername;
use App\Actions\Users\GetAssignableRoles;
use App\Actions\Users\ListUsers;
use App\Http\Requests\Users\IndexUsersRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
    public function update(UpdateUserRequest $request, User $user, GenerateUsername $generateUsername): RedirectResponse
    {
        // Collect only the fields that passed our validation rules.
        $data = $request->validated();

        // Compare the submitted names with the saved names.
        $nameChanged = $data['first_name'] !== $user->first_name
            || $data['last_name'] !== $user->last_name;

        // Generate a username only when at least one name changed.
        if ($nameChanged) {
            $data['username'] = $generateUsername->generate(
                $data['first_name'],
                $data['last_name'],
                $user
            );
        }

        // Save the account and end its sessions together when deactivating it.
        DB::transaction(function () use ($user, $data): void {
            $deactivating = $user->is_active && ! (bool) $data['is_active'];

            // Make existing Remember me cookies unusable after deactivation.
            if ($deactivating) {
                $user->setRememberToken(Str::random(60));
            }

            $user->update($data);

            // Remove this account's sessions from the configured database store.
            if ($deactivating && config('session.driver') === 'database') {
                DB::connection(config('session.connection'))
                    ->table(config('session.table', 'sessions'))
                    ->where('user_id', $user->id)
                    ->delete();
            }
        });

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
