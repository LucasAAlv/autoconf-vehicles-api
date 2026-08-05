<?php

use App\Exceptions\Problem;

it('returns the real HTTP reason phrase for a registered status', function () {
    expect(Problem::title(401))->toBe('Unauthorized');
    expect(Problem::title(403))->toBe('Forbidden');
    expect(Problem::title(404))->toBe('Not Found');
    expect(Problem::title(405))->toBe('Method Not Allowed');
    expect(Problem::title(429))->toBe('Too Many Requests');
    expect(Problem::title(500))->toBe('Internal Server Error');
});

it('overrides 419 with the Laravel/CSRF "Page Expired" convention', function () {
    // Symfony's Response::$statusTexts has no entry for 419 — it isn't a
    // registered HTTP status, only a Laravel/CSRF-specific convention.
    expect(Problem::title(419))->toBe('Page Expired');
});

it('falls back to a generic label for an unregistered status', function () {
    expect(Problem::title(999))->toBe('Unknown Status');
});
