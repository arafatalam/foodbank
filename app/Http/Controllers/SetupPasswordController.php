<?php

namespace App\Http\Controllers;

use App\Actions\Users\SetPersonalPassword;
use App\Http\Requests\Auth\SetPersonalPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SetupPasswordController extends Controller
{
    // Read the signed-in user from this request.
    public function show(Request $request): Response
    {
        // Send only the username to the Vue page.
        return Inertia::render('auth/SetupPassword', [
            'username' => $request->user()->username,
        ]);
    }

    // Save the personal password, finish setup, and require a fresh login.
    public function store(SetPersonalPasswordRequest $request, SetPersonalPassword $action): RedirectResponse
    {

        // Save the checked password for the signed-in account.
        $action->set($request->user(), $request->validated('password'));

        // Sign the user out after saving.
        Auth::logout();

        // Clear the old session and replace its form security token.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Send the user to the login page.
        return to_route('login');
    }
}
