<?php

use App\Exceptions\Problem;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

        // Requests that don't ask for JSON (plain browser navigation to a
        // web route) keep Laravel's default HTML rendering. Every callback
        // below returns null early for those, falling through the render
        // pipeline instead of forcing problem+json onto an HTML client.
        $wantsProblem = fn (Request $request): bool => $request->expectsJson()
            || $request->is('api', 'api/*', 'sanctum/*');

        // Real, reachable exception type — Handler::prepareException() does
        // not rewrite AuthenticationException, so this callback fires as-is.
        $exceptions->render(function (AuthenticationException $e, Request $request) use ($wantsProblem): ?JsonResponse {
            if (! $wantsProblem($request)) {
                return null;
            }

            return Problem::response($request, 401, $e->getMessage());
        });

        // Real, reachable exception type — kept as-is except for using the
        // exception's own status instead of a hardcoded 422.
        $exceptions->render(function (ValidationException $e, Request $request) use ($wantsProblem): ?JsonResponse {
            if (! $wantsProblem($request)) {
                return null;
            }

            return Problem::response($request, $e->status, $e->getMessage(), [
                'errors' => $e->errors(),
            ]);
        });

        // Handler::prepareException() rewrites ModelNotFoundException into
        // this type before any render callback runs, so this one callback
        // now correctly catches both unmatched-route 404s and model-not-found
        // 404s. The detail is deliberately generic: the real Laravel message
        // for a model-not-found ("No query results for model [...] 7") leaks
        // the model class and id, which never belongs in a response body.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($wantsProblem): ?JsonResponse {
            if (! $wantsProblem($request)) {
                return null;
            }

            return Problem::response($request, 404, 'O recurso solicitado não foi encontrado.');
        });

        // Catches everything else that carries a real HTTP status and
        // headers: AccessDeniedHttpException (403, the rewritten form of
        // AuthorizationException), MethodNotAllowedHttpException (405, with
        // an Allow header), the 419 HttpException that
        // Handler::prepareException() builds from a CSRF TokenMismatchException,
        // and ThrottleRequestsException (429, with a Retry-After header).
        // The message is only surfaced for 4xx: a 5xx message could leak
        // internals and is handled by the \Throwable callback below anyway.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($wantsProblem): ?JsonResponse {
            if (! $wantsProblem($request)) {
                return null;
            }

            $status = $e->getStatusCode();
            $detail = $status < 500 ? $e->getMessage() : null;

            return Problem::response($request, $status, $detail, [], $e->getHeaders());
        });

        $exceptions->render(function (\Throwable $e, Request $request) use ($wantsProblem): ?JsonResponse {
            if (! $wantsProblem($request)) {
                return null;
            }

            $detail = app()->environment('local') ? $e->getMessage() : null;

            return Problem::response($request, 500, $detail);
        });
    })->create();
