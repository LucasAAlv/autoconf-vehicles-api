<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

it('returns a generic problem+json 404 for an unmatched route', function () {
    $response = $this->getJson('/api/this-route-does-not-exist');

    $response->assertProblemJson(status: 404, detail: 'O recurso solicitado não foi encontrado.');
});

it('returns a generic problem+json 404 for a real model-not-found, without leaking the model class or id', function () {
    Route::get('/__test/model-not-found', function () {
        throw (new ModelNotFoundException())->setModel(App\Models\User::class, [999999]);
    });

    $response = $this->getJson('/__test/model-not-found');

    $response->assertProblemJson(status: 404, detail: 'O recurso solicitado não foi encontrado.');
    $response->assertDontSee('App\Models\User', escape: false);
    $response->assertDontSee('999999', escape: false);
});

it('returns a problem+json 403 when an AuthorizationException is thrown', function () {
    Route::get('/__test/forbidden', function () {
        throw new AuthorizationException('This action is unauthorized.');
    });

    $response = $this->getJson('/__test/forbidden');

    $response->assertProblemJson(status: 403, title: 'Forbidden', detail: 'This action is unauthorized.');
});

it('returns a problem+json 405 with an Allow header for a method the route does not support', function () {
    Route::get('/__test/method-not-allowed', fn () => response()->noContent());

    $response = $this->postJson('/__test/method-not-allowed');

    $response->assertProblemJson(status: 405, title: 'Method Not Allowed');
    $response->assertHeader('Allow');
    expect($response->headers->get('Allow'))->toContain('GET');
});

it('returns a problem+json 419 with title "Page Expired" for a CSRF token mismatch', function () {
    // ValidateCsrfToken::runningUnitTests() self-disables the real CSRF
    // middleware during the test suite (app()->runningUnitTests() is true),
    // so a genuine CSRF failure can't be triggered through the middleware
    // here. Throwing the same exception the middleware throws exercises the
    // exact renderer path Handler::prepareException() would route a real
    // failure through (TokenMismatchException -> HttpException(419, ...)).
    Route::get('/__test/token-mismatch', function () {
        throw new TokenMismatchException('CSRF token mismatch.');
    });

    $response = $this->getJson('/__test/token-mismatch');

    $response->assertProblemJson(status: 419, title: 'Page Expired', detail: 'CSRF token mismatch.');
});

it('returns a problem+json 429 with a Retry-After header once the rate limit is exceeded', function () {
    Route::get('/__test/throttled', fn () => response()->noContent())
        ->middleware('throttle:1,1');

    $this->getJson('/__test/throttled')->assertNoContent();

    $response = $this->getJson('/__test/throttled');

    $response->assertProblemJson(status: 429, title: 'Too Many Requests');
    $response->assertHeader('Retry-After');
});

it('returns a problem+json 500 with no detail member when the environment is not local', function () {
    Route::get('/__test/boom', function () {
        throw new RuntimeException('something went wrong internally');
    });

    $response = $this->getJson('/__test/boom');

    // APP_ENV=testing (phpunit.xml), not local, so detail must be masked.
    $response->assertProblemJson(status: 500, title: 'Internal Server Error');
    expect($response->json())->not->toHaveKey('detail');
});

it('returns a problem+json 422 with an errors member for a failed validation', function () {
    Route::get('/__test/validate', function (Illuminate\Http\Request $request) {
        Validator::make($request->all(), ['name' => 'required'])->validate();
    });

    $response = $this->getJson('/__test/validate');

    $response->assertProblemJson(status: 422, errorKeys: ['name']);
});

it('returns text/html, not problem+json, for an unmatched web route that does not ask for JSON', function () {
    $response = $this->get('/nope');

    $response->assertStatus(404);
    expect($response->headers->get('Content-Type'))->toContain('text/html');
});
