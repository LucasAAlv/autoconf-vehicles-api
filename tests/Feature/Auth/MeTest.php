<?php

use App\Models\User;

// Same rationale as `LoginTest.php`: `EnsureFrontendRequestsAreStateful` only
// starts a stateful session for requests it recognizes as coming from the
// SPA frontend, based on the `Origin`/`Referer` header.
beforeEach(function () {
    $this->withHeader('Origin', 'http://localhost');
});

it('returns the authenticated user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/auth/me');

    $response->assertOk()
        ->assertJsonPath('id', $user->id)
        ->assertJsonPath('name', $user->name)
        ->assertJsonPath('email', $user->email)
        ->assertJsonMissingPath('password');
});

it('returns a problem+json 401 for a guest', function () {
    $response = $this->getJson('/api/auth/me');

    $response->assertProblemJson(status: 401, title: 'Unauthorized', detail: 'Unauthenticated.');
});
