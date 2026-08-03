<?php

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
        $problem = function (
            Request $request,
            int $status,
            string $title,
            ?string $detail = null,
            array $extra = [],
        ): JsonResponse {
            $body = [
                'type'   => 'about:blank',
                'title'  => $title,
                'status' => $status,
            ];

            if ($detail !== null) {
                $body['detail'] = $detail;
            }

            $body['instance'] = $request->getRequestUri();

            foreach ($extra as $key => $value) {
                $body[$key] = $value;
            }

            return response()->json($body, $status, [
                'Content-Type' => 'application/problem+json',
            ]);
        };

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($problem): JsonResponse {
            return $problem($request, 401, 'Unauthenticated', $e->getMessage());
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) use ($problem): JsonResponse {
            return $problem($request, 403, 'Forbidden', $e->getMessage());
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) use ($problem): JsonResponse {
            return $problem($request, 404, 'Not Found', 'The requested resource was not found.');
        });

        $exceptions->render(function (ValidationException $e, Request $request) use ($problem): JsonResponse {
            return $problem($request, 422, 'Unprocessable Entity', $e->getMessage(), [
                'errors' => $e->errors(),
            ]);
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) use ($problem): JsonResponse {
            return $problem($request, 429, 'Too Many Requests', $e->getMessage());
        });

        $exceptions->render(function (\Throwable $e, Request $request) use ($problem): JsonResponse {
            $detail = app()->environment('local') ? $e->getMessage() : null;

            return $problem($request, 500, 'Internal Server Error', $detail);
        });
    })->create();
