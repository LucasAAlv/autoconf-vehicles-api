<?php

// M2 (Authentication) hasn't shipped yet, so the only `auth:sanctum`-protected
// route in the app right now is the default Laravel skeleton's `GET /api/user`.
// This test locks in *today's* real behavior of hitting it unauthenticated.
// A future PR (the RFC 7807 renderer work) may change what body/headers come
// back for an AuthenticationException — if it does, this test's assertions
// should change too, making that contract change visible in that diff.
it('returns a problem+json 401 for an unauthenticated request to a protected route', function () {
    $response = $this->getJson('/api/user');

    $response->assertProblemJson(status: 401, title: 'Unauthenticated');
});
