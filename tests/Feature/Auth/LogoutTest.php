<?php

use App\Models\User;

// Same rationale as `LoginTest.php`: `EnsureFrontendRequestsAreStateful` only
// starts a stateful session for requests it recognizes as coming from the
// SPA frontend, based on the `Origin`/`Referer` header.
beforeEach(function () {
    $this->withHeader('Origin', 'http://localhost');
});

it('logs out the authenticated user, invalidating the session', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->postJson('/api/auth/logout');

    $response->assertNoContent();

    // Explicitly `web`, not the bare default guard: the `auth:sanctum`
    // middleware this route sits behind calls `Auth::shouldUse('sanctum')`
    // as a side effect of authenticating the request, which flips the
    // manager's default guard for the rest of the test process. `web` is
    // the actual session guard `logout()` acts on, so it is the one whose
    // state proves the session was really cleared.
    $this->assertGuest('web');
});

it('returns a problem+json 401 for a guest', function () {
    $response = $this->postJson('/api/auth/logout');

    $response->assertProblemJson(status: 401, title: 'Unauthorized', detail: 'Unauthenticated.');
});
