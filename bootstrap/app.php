<?php

use App\Exceptions\Problem;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash([
            'password',
            'password_confirmation',
            'current_password',
            'token',
            'api_token',
            'secret',
        ]);

        $exceptions->context(fn () => [
            'url'    => request()->fullUrl(),
            'method' => request()->method(),
            'ip'     => request()->ip(),
            'user'   => request()->user()?->id,
        ]);

        $exceptions->render(function (AuthenticationException $e, Request $request): JsonResponse {
            return Problem::response($request, 401, $e->getMessage());
        });

        $exceptions->render(function (AuthorizationException $e, Request $request): JsonResponse {
            return Problem::response($request, 403, $e->getMessage());
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request): JsonResponse {
            return Problem::response($request, 404, 'The requested resource was not found.');
        });

        $exceptions->render(function (ValidationException $e, Request $request): JsonResponse {
            return Problem::response($request, 422, $e->getMessage(), [
                'errors' => $e->errors(),
            ]);
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request): JsonResponse {
            return Problem::response($request, 429, $e->getMessage());
        });

        $exceptions->render(function (\Throwable $e, Request $request): JsonResponse {
            $detail = app()->environment('local') ? $e->getMessage() : null;

            return Problem::response($request, 500, $detail);
        });
    })->create();
