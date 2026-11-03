<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    /** @param Request $request */
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        // Do not return to a page remembered before login.
        $request->session()->forget('url.intended');

        // Keep Fortify's JSON response for clients that explicitly request it.
        return $request->wantsJson()
            ? response()->json(['two_factor' => false])
            : to_route('dashboard');
    }
}
