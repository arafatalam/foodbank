<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordSetupIsComplete
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Guests need to reach the login page.
        if (! $request->user()) {
            return $next($request);
        }

        // Users who finished setup should not reopen the setup password form.
        if (! $request->user()->must_change_password) {

            if ($request->routeIs('password.setup.show', 'password.setup.store')) {
                return to_route('dashboard');
            }

            // Let them continue to the page they requested.
            return $next($request);
        }

        // An unfinished user can open setup, submit the form, or log out.
        if ($request->routeIs('password.setup.show', 'password.setup.store', 'logout')) {
            return $next($request);
        }


        // If that user opens /dashboard, send them to setup instead.
        return to_route('password.setup.show');
    }
}
