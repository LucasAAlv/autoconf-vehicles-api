<?php

use App\Models\User;

// `EnsureFrontendRequestsAreStateful` (wired via `statefulApi()` in
// `bootstrap/app.php`) only starts a session for requests it recognizes as
// coming from the SPA frontend — that recognition is based on the `Origin`/
// `Referer` header matching a stateful domain, not on the route itself. A
// real browser always sends this header for the SPA, so every request here
// sets it to mirror that.
beforeEach(function () {
    $this->withHeader('Origin', 'http://localhost');
});

it('returns a problem+json 429 with a Retry-After header after exceeding the login rate limit for the same email and IP', function () {
    $email = 'throttle-login@example.com';

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/auth/login', [
            'email'    => $email,
            'password' => 'wrong-password',
        ]);
    }

    $response = $this->postJson('/api/auth/login', [
        'email'    => $email,
        'password' => 'wrong-password',
    ]);

    $response->assertProblemJson(status: 429);
    $response->assertHeader('Retry-After');
});

it('does not throttle a login attempt for a different email from the same IP after exhausting one email\'s limit', function () {
    $exhaustedEmail = 'throttle-login-a@example.com';
    $otherEmail = 'throttle-login-b@example.com';

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/auth/login', [
            'email'    => $exhaustedEmail,
            'password' => 'wrong-password',
        ]);
    }

    $blocked = $this->postJson('/api/auth/login', [
        'email'    => $exhaustedEmail,
        'password' => 'wrong-password',
    ]);
    $blocked->assertProblemJson(status: 429);

    $response = $this->postJson('/api/auth/login', [
        'email'    => $otherEmail,
        'password' => 'wrong-password',
    ]);

    // Still a normal, non-throttled validation failure (unknown email), not
    // a 429: the limiter's budget for `$otherEmail` was never touched.
    $response->assertProblemJson(status: 422, errorKeys: ['email']);
});

it('returns a problem+json 429 with a Retry-After header after exceeding the register rate limit for the same email and IP', function () {
    $email = 'throttle-register@example.com';

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/auth/register', [
            'name'                  => 'Jane Doe',
            'email'                 => $email,
            'password'              => 'valid-password',
            'password_confirmation' => 'valid-password',
        ]);
    }

    $response = $this->postJson('/api/auth/register', [
        'name'                  => 'Jane Doe',
        'email'                 => $email,
        'password'              => 'valid-password',
        'password_confirmation' => 'valid-password',
    ]);

    $response->assertProblemJson(status: 429);
    $response->assertHeader('Retry-After');
});

it('does not throttle a register attempt for a different email from the same IP after exhausting one email\'s limit', function () {
    $exhaustedEmail = 'throttle-register-a@example.com';
    $otherEmail = 'throttle-register-b@example.com';

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/auth/register', [
            'name'                  => 'Jane Doe',
            'email'                 => $exhaustedEmail,
            'password'              => 'valid-password',
            'password_confirmation' => 'valid-password',
        ]);
    }

    $blocked = $this->postJson('/api/auth/register', [
        'name'                  => 'Jane Doe',
        'email'                 => $exhaustedEmail,
        'password'              => 'valid-password',
        'password_confirmation' => 'valid-password',
    ]);
    $blocked->assertProblemJson(status: 429);

    $response = $this->postJson('/api/auth/register', [
        'name'                  => 'Jane Doe',
        'email'                 => $otherEmail,
        'password'              => 'valid-password',
        'password_confirmation' => 'valid-password',
    ]);

    // Still a normal, successful registration, not a 429: the limiter's
    // budget for `$otherEmail` was never touched by `$exhaustedEmail`'s
    // attempts.
    $response->assertCreated();

    expect(User::query()->where('email', $otherEmail)->exists())->toBeTrue();
});
