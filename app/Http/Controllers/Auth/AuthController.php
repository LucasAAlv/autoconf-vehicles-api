<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Create a new user account.
     *
     * This does not also start an authenticated session: registration and
     * login are kept as two separate, explicit steps, matching the plain
     * `POST /auth/register` contract this endpoint promises. `User::create()`
     * only ever receives the request's own validated fields, so a payload
     * that also sends `is_admin` (or any other attribute) has no way to
     * influence the created record.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->accountData());

        return response()->json($user, 201);
    }

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

    /**
     * Return the currently authenticated user.
     *
     * This route sits behind `auth:sanctum`, so a guest never reaches this
     * method at all: Sanctum's own guard throws `AuthenticationException`
     * first, which the app's exception renderer already turns into a
     * problem+json 401 (wired in `bootstrap/app.php`).
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    /**
     * End the SPA's authenticated session.
     *
     * The three steps mirror `login()`'s own care around sessions, in
     * reverse: log the guard out, destroy the session data, and rotate the
     * CSRF token, so neither the session id nor the CSRF token an attacker
     * observed before logout stays valid afterwards. `204 No Content` is
     * used rather than `200` because there is no representation to return
     * for a logout.
     */
    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
