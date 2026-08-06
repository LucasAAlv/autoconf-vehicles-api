<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

// Same rationale as `LoginTest.php`: `EnsureFrontendRequestsAreStateful` only
// starts a stateful session for requests it recognizes as coming from the
// SPA frontend, based on the `Origin`/`Referer` header.
beforeEach(function () {
    $this->withHeader('Origin', 'http://localhost');
});

it('walks through the full register, login, me, and logout flow', function () {
    // A guest has no session yet, so `/auth/me` must reject them.
    $this->getJson('/api/auth/me')
        ->assertProblemJson(status: 401, title: 'Unauthorized', detail: 'Unauthenticated.');

    // Register creates the user and never leaks the plaintext password back
    // in the response.
    $registerResponse = $this->postJson('/api/auth/register', [
        'name'                  => 'Jane Doe',
        'email'                 => 'jane@example.com',
        'password'              => 'valid-password',
        'password_confirmation' => 'valid-password',
    ]);

    $registerResponse->assertCreated()
        ->assertJsonPath('name', 'Jane Doe')
        ->assertJsonPath('email', 'jane@example.com')
        ->assertJsonMissingPath('password');

    // The password stored in the database is hashed, not the plaintext value
    // submitted at registration.
    $user = User::query()->where('email', 'jane@example.com')->firstOrFail();

    expect($user->password)->not->toBe('valid-password')
        ->and(Hash::check('valid-password', $user->password))->toBeTrue();

    // A login attempt with the wrong password for this same, now-registered
    // user fails and does not authenticate.
    $this->postJson('/api/auth/login', [
        'email'    => 'jane@example.com',
        'password' => 'wrong-password',
    ])->assertProblemJson(status: 422, errorKeys: ['email']);

    $this->assertGuest();

    // Logging in with the correct credentials succeeds and starts a session.
    $this->postJson('/api/auth/login', [
        'email'    => 'jane@example.com',
        'password' => 'valid-password',
    ])->assertOk()
        ->assertJsonPath('id', $user->id)
        ->assertJsonPath('email', 'jane@example.com');

    $this->assertAuthenticatedAs($user);

    // With the session now established, `/auth/me` returns the authenticated
    // user.
    $this->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('id', $user->id)
        ->assertJsonPath('name', 'Jane Doe')
        ->assertJsonPath('email', 'jane@example.com');

    // Logout destroys the session.
    $this->postJson('/api/auth/logout')
        ->assertNoContent();

    // Explicitly `web`, not the bare default guard: the `auth:sanctum`
    // middleware this route sits behind calls `Auth::shouldUse('sanctum')`
    // as a side effect of authenticating the request, which flips the
    // manager's default guard for the rest of the test process. `web` is
    // the actual session guard `logout()` acts on, so it is the one whose
    // state proves the session was really cleared.
    $this->assertGuest('web');

    // `auth:sanctum` resolves through a `RequestGuard`, which caches the
    // first non-null user it resolves for the lifetime of the guard
    // instance (see `Illuminate\Auth\RequestGuard::user()`). In a real
    // deployment that instance never outlives a single HTTP request, so
    // this never matters; but this test drives several simulated requests
    // through the same, long-lived application container, so the cached
    // instance from the earlier authenticated `/auth/me` call would
    // otherwise keep answering with the old user even after the session
    // backing it was destroyed. Forgetting the cached guards forces the
    // next request to resolve authentication from scratch, the way a fresh
    // process would.
    Auth::forgetGuards();

    // The session is truly gone, not just cosmetically "logged out":
    // `/auth/me` rejects the same client again.
    $this->getJson('/api/auth/me')
        ->assertProblemJson(status: 401, title: 'Unauthorized', detail: 'Unauthenticated.');
});
