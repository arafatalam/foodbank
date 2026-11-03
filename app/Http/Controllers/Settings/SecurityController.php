<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class SecurityController extends Controller
{
    /**
     * Show the user's security settings page.
     */
    public function edit(): Response
    {
        return Inertia::render('settings/Security', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        // Save the password and remove this user's saved logins together.
        DB::transaction(function () use ($user, $request): void {
            // Stop old Remember me cookies from signing this user back in.
            $user->setRememberToken(Str::random(60));
            $user->update(['password' => $request->validated('password')]);

            // The application stores device sessions in the database.
            if (config('session.driver') === 'database') {
                DB::connection(config('session.connection'))
                    ->table(config('session.table', 'sessions'))
                    ->where('user_id', $user->id)
                    ->delete();
            }
        });

        // Sign out this browser and replace its session and form-security token.
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return to_route('login');
    }
}
