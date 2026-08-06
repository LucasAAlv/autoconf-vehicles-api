<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

// `EnsureFrontendRequestsAreStateful` (wired via `statefulApi()` in
// `bootstrap/app.php`) only starts a session for requests it recognizes as
// coming from the SPA frontend — that recognition is based on the `Origin`/
// `Referer` header matching a stateful domain, not on the route itself. A
// real browser always sends this header for the SPA, so every request here
// sets it to mirror that.
beforeEach(function () {
    $this->withHeader('Origin', 'http://localhost');
});

it('registers a new user and returns it without the password', function () {
    $response = $this->postJson('/api/auth/register', [
        'name'                  => 'Jane Doe',
        'email'                 => 'jane@example.com',
        'password'              => 'valid-password',
        'password_confirmation' => 'valid-password',
    ]);

    $user = User::query()->where('email', 'jane@example.com')->firstOrFail();

    $response->assertCreated()
        ->assertJsonPath('id', $user->id)
        ->assertJsonPath('name', 'Jane Doe')
        ->assertJsonPath('email', 'jane@example.com')
        ->assertJsonMissingPath('password');

    expect($response->getContent())->not->toContain('valid-password');
});

it('persists the new user with a hashed password', function () {
    $this->postJson('/api/auth/register', [
        'name'                  => 'Jane Doe',
        'email'                 => 'jane@example.com',
        'password'              => 'valid-password',
        'password_confirmation' => 'valid-password',
    ]);

    $user = User::query()->where('email', 'jane@example.com')->firstOrFail();

    expect($user->password)->not->toBe('valid-password')
        ->and(Hash::check('valid-password', $user->password))->toBeTrue();
});

it('defaults is_admin to false and ignores an is_admin field in the payload', function () {
    $response = $this->postJson('/api/auth/register', [
        'name'                  => 'Jane Doe',
        'email'                 => 'jane@example.com',
        'password'              => 'valid-password',
        'password_confirmation' => 'valid-password',
        'is_admin'              => true,
    ]);

    $response->assertCreated();

    $user = User::query()->where('email', 'jane@example.com')->firstOrFail();

    expect($user->is_admin)->toBeFalse()
        ->and(User::query()->whereKey($user->id)->value('is_admin'))->toBeFalse();
});

it('returns a problem+json 422 for a duplicate email', function () {
    $existing = User::factory()->create();

    $response = $this->postJson('/api/auth/register', [
        'name'                  => 'Jane Doe',
        'email'                 => $existing->email,
        'password'              => 'valid-password',
        'password_confirmation' => 'valid-password',
    ]);

    $response->assertProblemJson(status: 422, errorKeys: ['email']);
});

it('returns a problem+json 422 when password confirmation does not match', function () {
    $response = $this->postJson('/api/auth/register', [
        'name'                  => 'Jane Doe',
        'email'                 => 'jane@example.com',
        'password'              => 'valid-password',
        'password_confirmation' => 'a-different-password',
    ]);

    $response->assertProblemJson(status: 422, errorKeys: ['password']);
});

it('returns a problem+json 422 for a password that violates the default password rules', function () {
    $response = $this->postJson('/api/auth/register', [
        'name'                  => 'Jane Doe',
        'email'                 => 'jane@example.com',
        'password'              => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertProblemJson(status: 422, errorKeys: ['password']);
});

it('returns a problem+json 422 when required fields are missing', function () {
    $response = $this->postJson('/api/auth/register', []);

    $response->assertProblemJson(status: 422, errorKeys: ['name', 'email', 'password']);
});
