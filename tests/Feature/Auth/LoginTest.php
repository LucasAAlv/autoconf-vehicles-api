<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

// `EnsureFrontendRequestsAreStateful` (wired via `statefulApi()` in
// `bootstrap/app.php`) only starts a session for requests it recognizes as
// coming from the SPA frontend — that recognition is based on the `Origin`/
// `Referer` header matching a stateful domain, not on the route itself. A
// real browser always sends this header for the SPA, so every request here
// sets it to mirror that.
beforeEach(function () {
    $this->withHeader('Origin', 'http://localhost');
});

it('authenticates a user with valid credentials and returns the authenticated user', function () {
    $user = User::factory()->create([
        'password' => 'correct-password',
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email'    => $user->email,
        'password' => 'correct-password',
    ]);

    $response->assertOk()
        ->assertJsonPath('id', $user->id)
        ->assertJsonPath('name', $user->name)
        ->assertJsonPath('email', $user->email)
        ->assertJsonMissingPath('password');

    $this->assertAuthenticatedAs($user);
});

it('regenerates the session id on login to protect against session fixation', function () {
    $user = User::factory()->create([
        'password' => 'correct-password',
    ]);

    // Warm up a real, cookie-backed session before login, the same way the
    // SPA does today: it always requests the CSRF cookie first. That route
    // sits behind the plain `web` middleware group, so it starts a session
    // unconditionally.
    $warmUp = $this->get('/sanctum/csrf-cookie');

    $sessionCookieName = config('session.cookie');
    $sessionCookie = collect($warmUp->headers->getCookies())
        ->first(fn ($cookie) => $cookie->getName() === $sessionCookieName);

    expect($sessionCookie)->not->toBeNull();

    $previousSessionId = $this->app['session']->getId();

    $response = $this->withUnencryptedCookie($sessionCookieName, $sessionCookie->getValue())
        ->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'correct-password',
        ]);

    $response->assertOk();

    expect($this->app['session']->getId())
        ->not->toBe($previousSessionId);
});

it('returns a problem+json 422 for a non-existent email', function () {
    $response = $this->postJson('/api/auth/login', [
        'email'    => 'nobody@example.com',
        'password' => 'whatever-password',
    ]);

    $response->assertProblemJson(status: 422, errorKeys: ['email']);

    expect(Auth::check())->toBeFalse();
});

it('returns a problem+json 422 for a wrong password', function () {
    $user = User::factory()->create([
        'password' => 'correct-password',
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email'    => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertProblemJson(status: 422, errorKeys: ['email']);

    $this->assertGuest();
});

it('returns a problem+json 422 when email or password is missing', function () {
    $response = $this->postJson('/api/auth/login', [
        'email' => 'nobody@example.com',
    ]);

    $response->assertProblemJson(status: 422, errorKeys: ['password']);
});
