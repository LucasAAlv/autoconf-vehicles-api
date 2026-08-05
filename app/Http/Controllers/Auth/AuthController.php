<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Authenticate a user for the SPA's stateful (cookie) session.
     *
     * `ValidationException::withMessages()` on a bad attempt is deliberate,
     * not just convenient: this app's exception renderer already turns any
     * `ValidationException` into the same `application/problem+json` 422
     * envelope used by Form Request validation, so a credentials failure
     * looks identical to any other validation failure to API consumers
     * without the controller having to build that response by hand.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->credentials())) {
            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }

        // Regenerates the session id so a session fixated before login
        // (e.g. one an attacker forced onto the victim) can't be reused
        // now that it carries an authenticated identity.
        $request->session()->regenerate();

        return response()->json(Auth::user());
    }
}
