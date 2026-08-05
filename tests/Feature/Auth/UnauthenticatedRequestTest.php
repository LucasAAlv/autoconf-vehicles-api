<?php

// M2 (Authentication) hasn't shipped yet, so the only `auth:sanctum`-protected
// route in the app right now is the default Laravel skeleton's `GET /api/user`.
// This test locks in *today's* real behavior of hitting it unauthenticated.
//
// `title` is `Unauthorized`, not `Unauthenticated`: the renderer now derives
// `title` from the real HTTP reason phrase for 401 (`Response::$statusTexts`)
// instead of a hardcoded string. `detail` stays `Unauthenticated.` — that's
// Laravel's own exception message, unrelated to the reason phrase and left
// unchanged.
it('returns a problem+json 401 for an unauthenticated request to a protected route', function () {
    $response = $this->getJson('/api/user');

    $response->assertProblemJson(status: 401, title: 'Unauthorized', detail: 'Unauthenticated.');
});
